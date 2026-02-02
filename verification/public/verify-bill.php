<?php
/**
 * Public Bill Verification Page
 * Step 1: Enter bill number
 * Step 2: View bill details (no OTP required)
 * Step 3: Download requires OTP verification
 */

define('VERIFICATION_PORTAL', true);

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/security.php';
require_once __DIR__ . '/../config/mail.php';
require_once __DIR__ . '/../includes/functions.php';

// Check if Bills module is enabled
if (!isBillsModuleEnabled()) {
    header('Location: ../index.php');
    exit;
}

$step = (int)($_GET['step'] ?? 1);
$error = '';
$success = '';
$bill = null;

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid request. Please try again.';
    } else {
        $action = $_POST['action'] ?? '';

        // Step 1: Search for bill
        if ($action === 'search') {
            $billNumber = sanitize($_POST['bill_number'] ?? '');
            $billDate = sanitize($_POST['bill_date'] ?? '');

            if (empty($billNumber)) {
                $error = 'Please enter a bill number.';
            } elseif (empty($billDate)) {
                $error = 'Please enter the bill date.';
            } else {
                // Rate limiting
                if (!checkRateLimit('bill_search_' . getClientIP(), 10, 300)) {
                    $error = 'Too many search attempts. Please try again later.';
                } else {
                    try {
                        $db = getDB();
                        // Search by bill number and date (check both BS and AD)
                        $stmt = $db->prepare("
                            SELECT id, bill_number, bill_type, vendor_name, pan_number, is_non_pan,
                                   non_pan_amount, bill_amount, bill_date_bs, bill_date_ad, remarks
                            FROM ver_bills
                            WHERE bill_number = ?
                            AND (bill_date_bs = ? OR bill_date_ad = ?)
                            AND status = 'active'
                        ");
                        $stmt->execute([$billNumber, $billDate, $billDate]);
                        $bill = $stmt->fetch();

                        if ($bill) {
                            $_SESSION['verify_bill_id'] = $bill['id'];
                            $_SESSION['verify_bill_number'] = $bill['bill_number'];
                            header('Location: verify-bill.php?step=2');
                            exit;
                        } else {
                            $error = 'Bill not found. Please check the bill number and date, then try again.';
                            logSecurityEvent('bill_search_not_found', ['bill_number' => $billNumber, 'date' => $billDate]);
                        }
                    } catch (PDOException $e) {
                        error_log("Bill search error: " . $e->getMessage());
                        $error = 'An error occurred. Please try again.';
                    }
                }
            }
        }

        // Step 2: Request Download - Send OTP
        if ($action === 'request_download') {
            $email = sanitize($_POST['email'] ?? '');

            if (empty($email) || !isValidEmail($email)) {
                $error = 'Please enter a valid email address.';
            } elseif (!isset($_SESSION['verify_bill_id'])) {
                header('Location: verify-bill.php');
                exit;
            } else {
                // Rate limiting for OTP
                if (!checkRateLimit('otp_send_' . getClientIP(), 3, 300)) {
                    $error = 'Too many OTP requests. Please wait before trying again.';
                } else {
                    try {
                        $db = getDB();

                        // Generate OTP
                        $otp = generateOTP();

                        // Get OTP expiry from settings or use default
                        $otpExpiry = OTP_EXPIRY_MINUTES;
                        try {
                            $stmtSettings = $db->query("SELECT setting_value FROM ver_settings WHERE setting_key = 'otp_expiry_minutes'");
                            $expirySetting = $stmtSettings->fetch();
                            if ($expirySetting && intval($expirySetting['setting_value']) > 0) {
                                $otpExpiry = intval($expirySetting['setting_value']);
                            }
                        } catch (Exception $e) {}

                        // Save OTP request - use MySQL NOW() + INTERVAL to avoid timezone issues
                        $stmt = $db->prepare("
                            INSERT INTO ver_otp_requests
                            (email, otp_code, verification_type, reference_number, expires_at, ip_address, user_agent)
                            VALUES (?, ?, 'bill', ?, DATE_ADD(NOW(), INTERVAL ? MINUTE), ?, ?)
                        ");
                        $stmt->execute([
                            $email,
                            $otp,
                            $_SESSION['verify_bill_number'],
                            $otpExpiry,
                            getClientIP(),
                            $_SERVER['HTTP_USER_AGENT'] ?? null
                        ]);

                        $otpRequestId = $db->lastInsertId();

                        // Send OTP email
                        if (sendOTPEmail($email, $otp, 'bill')) {
                            $_SESSION['verify_email'] = $email;
                            $_SESSION['verify_otp_id'] = $otpRequestId;
                            $success = 'OTP has been sent to your email address.';
                            header('Location: verify-bill.php?step=3');
                            exit;
                        } else {
                            $error = 'Failed to send OTP. Please try again or contact support.';
                        }
                    } catch (PDOException $e) {
                        error_log("OTP send error: " . $e->getMessage());
                        $error = 'An error occurred. Please try again.';
                    }
                }
            }
            $step = 2;
        }

        // Step 3: Verify OTP for Download
        if ($action === 'verify_otp') {
            $enteredOtp = sanitize($_POST['otp'] ?? '');

            if (empty($enteredOtp)) {
                $error = 'Please enter the OTP.';
            } elseif (!isset($_SESSION['verify_otp_id']) || !isset($_SESSION['verify_bill_id'])) {
                header('Location: verify-bill.php');
                exit;
            } else {
                // Rate limiting for OTP verification
                if (!checkRateLimit('otp_verify_' . getClientIP(), 5, 300)) {
                    $error = 'Too many verification attempts. Please request a new OTP.';
                } else {
                    try {
                        $db = getDB();

                        // Get OTP request
                        $stmt = $db->prepare("
                            SELECT * FROM ver_otp_requests
                            WHERE id = ? AND is_verified = 0 AND expires_at > NOW()
                        ");
                        $stmt->execute([$_SESSION['verify_otp_id']]);
                        $otpRequest = $stmt->fetch();

                        if (!$otpRequest) {
                            $error = 'OTP has expired. Please request a new one.';
                            unset($_SESSION['verify_otp_id']);
                            $step = 2;
                        } elseif ($otpRequest['attempts'] >= 3) {
                            $error = 'Maximum attempts exceeded. Please request a new OTP.';
                            unset($_SESSION['verify_otp_id']);
                            $step = 2;
                        } elseif ($otpRequest['otp_code'] !== $enteredOtp) {
                            // Increment attempts
                            $stmt = $db->prepare("UPDATE ver_otp_requests SET attempts = attempts + 1 WHERE id = ?");
                            $stmt->execute([$_SESSION['verify_otp_id']]);
                            $error = 'Invalid OTP. Please try again.';
                            $step = 3;
                        } else {
                            // OTP verified successfully
                            $stmt = $db->prepare("UPDATE ver_otp_requests SET is_verified = 1, verified_at = NOW() WHERE id = ?");
                            $stmt->execute([$_SESSION['verify_otp_id']]);

                            // Log verification
                            logVerificationActivity(
                                $_SESSION['verify_email'],
                                'bill',
                                $_SESSION['verify_bill_number'],
                                $_SESSION['verify_otp_id']
                            );

                            // Set session for bill viewing/download
                            $_SESSION['verified_bill_access'] = true;
                            $_SESSION['verified_bill_id'] = $_SESSION['verify_bill_id'];
                            $_SESSION['verified_bill_expiry'] = time() + 1800; // 30 minutes

                            // Clean up
                            unset($_SESSION['verify_otp_id']);

                            header('Location: view-document.php?type=bill&id=' . $_SESSION['verify_bill_id']);
                            exit;
                        }
                    } catch (PDOException $e) {
                        error_log("OTP verify error: " . $e->getMessage());
                        $error = 'An error occurred. Please try again.';
                    }
                }
            }
            $step = 3;
        }
    }
}

// Load bill info for step 2 and 3
if ($step >= 2 && isset($_SESSION['verify_bill_id'])) {
    try {
        $db = getDB();
        $stmt = $db->prepare("
            SELECT id, bill_number, bill_type, vendor_name, pan_number, is_non_pan,
                   non_pan_amount, bill_amount, bill_date_bs, bill_date_ad, remarks
            FROM ver_bills
            WHERE id = ? AND status = 'active'
        ");
        $stmt->execute([$_SESSION['verify_bill_id']]);
        $bill = $stmt->fetch();

        if (!$bill) {
            unset($_SESSION['verify_bill_id'], $_SESSION['verify_bill_number']);
            header('Location: verify-bill.php');
            exit;
        }
    } catch (PDOException $e) {
        error_log("Bill fetch error: " . $e->getMessage());
    }
}

$csrfToken = generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify Bill | Company</title>
    <?php if (file_exists(__DIR__ . '/../uploads/favicon.ico')): ?>
    <link rel="icon" type="image/x-icon" href="../uploads/favicon.ico?v=<?php echo filemtime(__DIR__ . '/../uploads/favicon.ico'); ?>">
    <?php endif; ?>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        'primary-red': '#D72828',
                        'primary-red-dark': '#B82020',
                        'primary-blue': '#1E73BE',
                        'primary-dark': '#1A3647',
                        'primary-teal': '#008BB0',
                    }
                }
            }
        }
    </script>
</head>
<body class="bg-gray-100 min-h-screen">
    <!-- Header - Matching Website Design -->
    <header class="bg-primary-red text-white shadow-lg">
        <div class="max-w-6xl mx-auto px-4 py-3">
            <div class="flex items-center justify-between">
                <!-- Logo and Brand -->
                <a href="https://www.example.com" class="flex items-center space-x-3">
                    <!-- Logo -->
                    <div class="w-10 h-10 flex-shrink-0">
                        <?php if (file_exists(__DIR__ . '/../uploads/logo.png')): ?>
                        <img src="../uploads/logo.png?v=<?php echo filemtime(__DIR__ . '/../uploads/logo.png'); ?>" alt="Logo" class="h-10 w-auto">
                        <?php else: ?>
                        <svg viewBox="0 0 100 100" xmlns="http://www.w3.org/2000/svg">
                            <polygon points="50,5 93,27 93,73 50,95 7,73 7,27" fill="#1A3647" stroke="#008BB0" stroke-width="3"/>
                            <polygon points="50,18 78,35 78,65 50,82 22,65 22,35" fill="none" stroke="#008BB0" stroke-width="2"/>
                            <text x="50" y="58" fill="#fff" font-size="28" font-weight="bold" font-family="Arial" text-anchor="middle">T</text>
                        </svg>
                        <?php endif; ?>
                    </div>
                    <div>
                        <h1 class="text-xl font-bold tracking-wide">
                            <span class="text-white">COMPANY</span>
                            <span class="text-primary-teal font-normal">APPAREL</span>
                        </h1>
                        <p class="text-xs text-red-200 tracking-wider">BILL VERIFICATION</p>
                    </div>
                </a>

                <!-- Navigation -->
                <nav class="hidden md:flex items-center space-x-6">
                    <a href="https://www.example.com" class="text-red-100 hover:text-white transition text-sm">
                        <i class="fas fa-globe mr-1"></i> Website
                    </a>
                    <a href="verify-document.php" class="text-red-100 hover:text-white transition text-sm">
                        <i class="fas fa-file-alt mr-1"></i> Verify Document
                    </a>
                    <a href="../index.php" class="flex items-center px-4 py-2 bg-white/10 hover:bg-white/20 rounded-lg transition text-sm">
                        <i class="fas fa-home mr-2"></i> Home
                    </a>
                </nav>

                <!-- Mobile Menu Button -->
                <button class="md:hidden text-white" onclick="document.getElementById('mobileMenu').classList.toggle('hidden')">
                    <i class="fas fa-bars text-xl"></i>
                </button>
            </div>

            <!-- Mobile Menu -->
            <div id="mobileMenu" class="hidden md:hidden mt-4 pb-2 border-t border-red-400 pt-4">
                <div class="flex flex-col space-y-2">
                    <a href="https://www.example.com" class="text-red-100 hover:text-white py-2">
                        <i class="fas fa-globe mr-2"></i> Website
                    </a>
                    <a href="verify-document.php" class="text-red-100 hover:text-white py-2">
                        <i class="fas fa-file-alt mr-2"></i> Verify Document
                    </a>
                    <a href="../index.php" class="text-red-100 hover:text-white py-2">
                        <i class="fas fa-home mr-2"></i> Home
                    </a>
                </div>
            </div>
        </div>
    </header>

    <main class="max-w-2xl mx-auto px-4 py-12">
        <?php if ($error): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6" role="alert">
                <i class="fas fa-exclamation-circle mr-2"></i>
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-6" role="alert">
                <i class="fas fa-check-circle mr-2"></i>
                <?php echo htmlspecialchars($success); ?>
            </div>
        <?php endif; ?>

        <?php if ($step === 1): ?>
            <!-- Step 1: Enter Bill Number -->
            <div class="bg-white rounded-lg shadow-lg p-8">
                <div class="text-center mb-6">
                    <div class="w-16 h-16 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-4">
                        <i class="fas fa-receipt text-green-600 text-2xl"></i>
                    </div>
                    <h2 class="text-2xl font-semibold text-gray-800">Verify Bill</h2>
                    <p class="text-gray-500 mt-2">Enter the bill number to verify its authenticity</p>
                </div>

                <form method="POST" action="" class="space-y-6">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                    <input type="hidden" name="action" value="search">

                    <div>
                        <label for="bill_number" class="block text-sm font-medium text-gray-700 mb-1">
                            Bill Number <span class="text-red-500">*</span>
                        </label>
                        <input type="text" id="bill_number" name="bill_number"
                               class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent text-lg"
                               placeholder="e.g., BILL-2081-001" required autofocus>
                    </div>

                    <!-- Date Field with Clickable Panel -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            Bill Date <span class="text-red-500">*</span>
                        </label>
                        <div id="verify_bill_datepicker" class="relative"></div>
                    </div>

                    <button type="submit"
                            class="w-full bg-green-600 text-white py-3 px-4 rounded-lg hover:bg-green-700 focus:ring-4 focus:ring-green-300 font-medium text-lg transition duration-200">
                        <i class="fas fa-search mr-2"></i>Verify Bill
                    </button>
                </form>

                <link rel="stylesheet" href="../assets/css/nepali-datepicker.css">
                <script src="../assets/js/nepali-datepicker.js"></script>
                <script>
                document.addEventListener('DOMContentLoaded', function() {
                    const apiUrl = '../admin/api-dates.php';
                    // Use 'bill_date' directly as the BS field name so it gets submitted with the form
                    initNepaliDatePicker('verify_bill_datepicker', 'bill_date', 'bill_date_ad', '', apiUrl);
                });
                </script>
            </div>

        <?php elseif ($step === 2 && $bill): ?>
            <!-- Step 2: Bill Verified - Show Details -->
            <div class="bg-white rounded-lg shadow-lg overflow-hidden">
                <!-- Success Header -->
                <div class="bg-green-500 text-white p-6 text-center">
                    <div class="w-16 h-16 bg-white bg-opacity-20 rounded-full flex items-center justify-center mx-auto mb-4">
                        <i class="fas fa-check-circle text-4xl"></i>
                    </div>
                    <h2 class="text-2xl font-bold">Bill Verified Successfully</h2>
                    <p class="text-green-100 mt-2">This bill is authentic and issued by Company</p>
                </div>

                <!-- Bill Details -->
                <div class="p-6">
                    <div class="border-b pb-4 mb-4">
                        <p class="text-sm text-gray-500 mb-1">Bill Number</p>
                        <p class="text-xl font-bold text-gray-800"><?php echo htmlspecialchars($bill['bill_number']); ?></p>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 border-b pb-4 mb-4">
                        <div>
                            <p class="text-sm text-gray-500 mb-1">Bill Type</p>
                            <p class="text-lg font-medium text-gray-800">
                                <?php echo ucfirst(htmlspecialchars($bill['bill_type'] ?? 'General')); ?>
                            </p>
                        </div>
                        <?php if (!empty($bill['vendor_name'])): ?>
                        <div>
                            <p class="text-sm text-gray-500 mb-1">Vendor/Supplier</p>
                            <p class="text-lg font-medium text-gray-800">
                                <?php echo htmlspecialchars($bill['vendor_name']); ?>
                            </p>
                        </div>
                        <?php endif; ?>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 border-b pb-4 mb-4">
                        <?php if ($bill['is_non_pan']): ?>
                        <div>
                            <p class="text-sm text-gray-500 mb-1">Bill Type</p>
                            <p class="text-lg font-medium text-orange-600">
                                <i class="fas fa-exclamation-triangle mr-1"></i> Non-PAN Bill
                            </p>
                        </div>
                        <?php if (!empty($bill['non_pan_amount'])): ?>
                        <div>
                            <p class="text-sm text-gray-500 mb-1">Non-PAN Amount</p>
                            <p class="text-lg font-medium text-gray-800">
                                NPR <?php echo number_format($bill['non_pan_amount'], 2); ?>
                            </p>
                        </div>
                        <?php endif; ?>
                        <?php else: ?>
                        <?php if (!empty($bill['pan_number'])): ?>
                        <div>
                            <p class="text-sm text-gray-500 mb-1">PAN Number</p>
                            <p class="text-lg font-medium text-gray-800">
                                <?php echo htmlspecialchars($bill['pan_number']); ?>
                            </p>
                        </div>
                        <?php endif; ?>
                        <?php if (!empty($bill['bill_amount'])): ?>
                        <div>
                            <p class="text-sm text-gray-500 mb-1">Bill Amount</p>
                            <p class="text-lg font-medium text-gray-800">
                                NPR <?php echo number_format($bill['bill_amount'], 2); ?>
                            </p>
                        </div>
                        <?php endif; ?>
                        <?php endif; ?>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 border-b pb-4 mb-4">
                        <div>
                            <p class="text-sm text-gray-500 mb-1">Date (BS)</p>
                            <p class="text-lg font-medium text-blue-600">
                                <i class="fas fa-calendar-alt mr-2"></i>
                                <?php echo htmlspecialchars($bill['bill_date_bs']); ?>
                            </p>
                        </div>
                        <?php if (!empty($bill['bill_date_ad'])): ?>
                        <div>
                            <p class="text-sm text-gray-500 mb-1">Date (AD)</p>
                            <p class="text-lg font-medium text-green-600">
                                <i class="fas fa-calendar mr-2"></i>
                                <?php echo htmlspecialchars($bill['bill_date_ad']); ?>
                            </p>
                        </div>
                        <?php endif; ?>
                    </div>

                    <?php if (!empty($bill['remarks'])): ?>
                    <div class="border-b pb-4 mb-4">
                        <p class="text-sm text-gray-500 mb-1">Remarks</p>
                        <p class="text-gray-700"><?php echo htmlspecialchars($bill['remarks']); ?></p>
                    </div>
                    <?php endif; ?>

                    <!-- View Section -->
                    <div class="bg-green-50 rounded-lg p-6 mt-6">
                        <h3 class="text-lg font-semibold text-gray-800 mb-4">
                            <i class="fas fa-eye mr-2 text-green-600"></i>View Bill
                        </h3>
                        <p class="text-gray-600 mb-4">
                            To view the full bill, please verify your email with OTP.
                        </p>

                        <form method="POST" action="" class="space-y-4">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                            <input type="hidden" name="action" value="request_download">

                            <div>
                                <label for="email" class="block text-sm font-medium text-gray-700 mb-1">
                                    Your Email Address
                                </label>
                                <input type="email" id="email" name="email"
                                       class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent"
                                       placeholder="your@email.com" required>
                            </div>

                            <button type="submit"
                                    class="w-full bg-green-600 text-white py-3 px-4 rounded-lg hover:bg-green-700 focus:ring-4 focus:ring-green-300 font-medium transition duration-200">
                                <i class="fas fa-paper-plane mr-2"></i>Send OTP & View
                            </button>
                        </form>
                    </div>
                </div>

                <!-- Footer -->
                <div class="bg-gray-50 px-6 py-4 flex justify-between items-center">
                    <a href="verify-bill.php" class="text-gray-500 hover:text-gray-700">
                        <i class="fas fa-arrow-left mr-1"></i> Verify Another
                    </a>
                    <span class="text-sm text-gray-400">
                        <i class="fas fa-shield-alt mr-1"></i> Verified by Company
                    </span>
                </div>
            </div>

        <?php elseif ($step === 3): ?>
            <!-- Step 3: Enter OTP -->
            <div class="bg-white rounded-lg shadow-lg p-8">
                <div class="text-center mb-6">
                    <div class="w-16 h-16 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-4">
                        <i class="fas fa-key text-green-600 text-2xl"></i>
                    </div>
                    <h2 class="text-2xl font-semibold text-gray-800">Enter OTP</h2>
                    <p class="text-gray-500 mt-2">
                        Enter the 6-digit OTP sent to<br>
                        <strong><?php echo htmlspecialchars($_SESSION['verify_email'] ?? ''); ?></strong>
                    </p>
                </div>

                <form method="POST" action="" class="space-y-6">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                    <input type="hidden" name="action" value="verify_otp">

                    <div>
                        <label for="otp" class="block text-sm font-medium text-gray-700 mb-1">
                            OTP Code
                        </label>
                        <input type="text" id="otp" name="otp"
                               class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent text-center text-2xl tracking-widest"
                               placeholder="000000" maxlength="6" pattern="\d{6}" required autofocus>
                    </div>

                    <button type="submit"
                            class="w-full bg-green-600 text-white py-3 px-4 rounded-lg hover:bg-green-700 focus:ring-4 focus:ring-green-300 font-medium transition duration-200">
                        <i class="fas fa-check mr-2"></i>Verify & View
                    </button>
                </form>

                <div class="mt-4 text-center text-sm">
                    <p class="text-gray-500">Didn't receive the OTP?</p>
                    <a href="verify-bill.php?step=2" class="text-green-600 hover:text-green-800">
                        Resend OTP
                    </a>
                </div>

                <div class="mt-4 text-center">
                    <a href="verify-bill.php?step=2" class="text-gray-500 hover:text-gray-700 text-sm">
                        <i class="fas fa-arrow-left mr-1"></i> Back to Bill Details
                    </a>
                </div>
            </div>

        <?php endif; ?>

        <p class="text-center text-gray-400 text-sm mt-6">
            This verification system is maintained by Company.
        </p>
    </main>

    <!-- Footer - Matching Website Design -->
    <footer class="bg-primary-dark text-white mt-12">
        <div class="max-w-6xl mx-auto px-4 py-8">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                <!-- Company Info -->
                <div>
                    <div class="flex items-center space-x-3 mb-4">
                        <div class="w-10 h-10">
                            <svg viewBox="0 0 100 100" xmlns="http://www.w3.org/2000/svg">
                                <polygon points="50,5 93,27 93,73 50,95 7,73 7,27" fill="#1A3647" stroke="#008BB0" stroke-width="3"/>
                                <polygon points="50,18 78,35 78,65 50,82 22,65 22,35" fill="none" stroke="#008BB0" stroke-width="2"/>
                                <text x="50" y="58" fill="#fff" font-size="28" font-weight="bold" font-family="Arial" text-anchor="middle">T</text>
                            </svg>
                        </div>
                        <div>
                            <h3 class="font-bold">COMPANY <span class="text-primary-teal font-normal">APPAREL</span></h3>
                            <p class="text-xs text-gray-400">Merging 33 years of expertise</p>
                        </div>
                    </div>
                    <p class="text-gray-400 text-sm">Document & Bill Verification Portal for authentic document verification.</p>
                </div>

                <!-- Quick Links -->
                <div>
                    <h4 class="font-semibold mb-4 text-primary-teal">Quick Links</h4>
                    <ul class="space-y-2 text-sm text-gray-400">
                        <li><a href="verify-document.php" class="hover:text-white transition"><i class="fas fa-file-alt mr-2"></i>Verify Document</a></li>
                        <li><a href="verify-bill.php" class="hover:text-white transition"><i class="fas fa-receipt mr-2"></i>Verify Bill</a></li>
                        <li><a href="https://www.example.com" class="hover:text-white transition"><i class="fas fa-globe mr-2"></i>Main Website</a></li>
                    </ul>
                </div>

                <!-- Contact Info -->
                <div>
                    <h4 class="font-semibold mb-4 text-primary-teal">Contact Us</h4>
                    <ul class="space-y-2 text-sm text-gray-400">
                        <li><i class="fas fa-phone mr-2 text-primary-teal"></i>+977-9863618347</li>
                        <li><i class="fas fa-phone mr-2 text-primary-teal"></i>+977-9865005120</li>
                        <li><i class="fas fa-envelope mr-2 text-primary-teal"></i>admin@example.com</li>
                        <li><i class="fas fa-map-marker-alt mr-2 text-primary-teal"></i>Gaidakot-6, Nawalparasi(east)</li>
                    </ul>
                </div>
            </div>

            <!-- Social & Copyright -->
            <div class="border-t border-gray-700 mt-8 pt-6 flex flex-col md:flex-row items-center justify-between">
                <div class="flex space-x-4 mb-4 md:mb-0">
                    <a href="https://www.facebook.com/yourpage" target="_blank" class="w-10 h-10 bg-primary-blue rounded-full flex items-center justify-center hover:bg-blue-600 transition">
                        <i class="fab fa-facebook-f"></i>
                    </a>
                    <a href="https://wa.me/9779865005120" target="_blank" class="w-10 h-10 bg-green-500 rounded-full flex items-center justify-center hover:bg-green-600 transition">
                        <i class="fab fa-whatsapp"></i>
                    </a>
                    <a href="mailto:admin@example.com" class="w-10 h-10 bg-primary-red rounded-full flex items-center justify-center hover:bg-red-700 transition">
                        <i class="fas fa-envelope"></i>
                    </a>
                </div>
                <p class="text-gray-500 text-sm">
                    &copy; <?php echo date('Y'); ?> Company. All rights reserved. | PAN: 124441767
                </p>
            </div>
        </div>
    </footer>
</body>
</html>
