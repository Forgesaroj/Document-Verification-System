<?php
/**
 * Add Bill Page
 */

define('VERIFICATION_PORTAL', true);

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/security.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/date_converter.php';
require_once __DIR__ . '/../includes/nepali_date_helper.php';

// Require login
requireAdminLogin();

$pageTitle = 'Add Bill';
$errors = [];
$formData = [
    'bill_number' => '',
    'bill_type' => 'general',
    'vendor_name' => '',
    'pan_number' => '',
    'is_non_pan' => 0,
    'non_pan_amount' => '',
    'bill_amount' => '',
    'bill_date_bs' => '',
    'bill_date_ad' => '',
    'remarks' => ''
];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid request. Please try again.';
    } else {
        // Collect form data
        $formData = [
            'bill_number' => sanitize($_POST['bill_number'] ?? ''),
            'bill_type' => sanitize($_POST['bill_type'] ?? 'general'),
            'vendor_name' => sanitize($_POST['vendor_name'] ?? ''),
            'pan_number' => sanitize($_POST['pan_number'] ?? ''),
            'is_non_pan' => isset($_POST['is_non_pan']) ? 1 : 0,
            'non_pan_amount' => sanitize($_POST['non_pan_amount'] ?? ''),
            'bill_amount' => sanitize($_POST['bill_amount'] ?? ''),
            'bill_date_bs' => sanitize($_POST['bill_date_bs'] ?? ''),
            'bill_date_ad' => sanitize($_POST['bill_date_ad'] ?? ''),
            'remarks' => sanitize($_POST['remarks'] ?? '')
        ];

        // Validate required fields
        if (empty($formData['bill_number'])) {
            $errors[] = 'Bill number is required.';
        } elseif (!isValidBillNumber($formData['bill_number'])) {
            $errors[] = 'Invalid bill number format.';
        }

        // Validate BS date (required)
        if (empty($formData['bill_date_bs'])) {
            $errors[] = 'Bill date (BS) is required.';
        } else {
            // Convert BS to AD
            $adDate = convertBsToAd($formData['bill_date_bs']);
            if ($adDate) {
                $formData['bill_date_ad'] = $adDate;
            } else {
                $errors[] = 'Invalid BS date format. Use YYYY-MM-DD.';
            }
        }

        // Validate PAN/Non-PAN
        if ($formData['is_non_pan']) {
            $formData['pan_number'] = ''; // Clear PAN if non-PAN
            if (empty($formData['non_pan_amount']) || !is_numeric($formData['non_pan_amount'])) {
                $errors[] = 'Non-PAN amount is required and must be a number.';
            }
        } else {
            if (!empty($formData['pan_number']) && !isValidPAN($formData['pan_number'])) {
                $errors[] = 'Invalid PAN number format (must be 9 digits).';
            }
        }

        // Validate file upload (optional)
        $hasFile = isset($_FILES['bill_file']) && $_FILES['bill_file']['error'] !== UPLOAD_ERR_NO_FILE;
        if ($hasFile) {
            $fileValidation = validateUploadedFile($_FILES['bill_file']);
            if (!$fileValidation['valid']) {
                $errors[] = $fileValidation['error'];
            }
        }

        // Check if bill number already exists
        if (empty($errors)) {
            try {
                $db = getDB();
                $stmt = $db->prepare("SELECT id FROM ver_bills WHERE bill_number = ?");
                $stmt->execute([$formData['bill_number']]);
                if ($stmt->fetch()) {
                    $errors[] = 'A bill with this number already exists.';
                }
            } catch (PDOException $e) {
                error_log("Bill check error: " . $e->getMessage());
                $errors[] = 'Database error. Please try again.';
            }
        }

        // Upload file and insert record
        if (empty($errors)) {
            $filePath = null;
            $fileType = null;

            // Handle file upload if provided
            if ($hasFile) {
                $uploadDir = __DIR__ . '/../uploads/bills/';

                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }

                $uploadResult = uploadFile($_FILES['bill_file'], $uploadDir);

                if ($uploadResult['success']) {
                    $filePath = $uploadResult['path'];
                    $fileType = strtolower(pathinfo($_FILES['bill_file']['name'], PATHINFO_EXTENSION));
                } else {
                    $errors[] = $uploadResult['error'];
                }
            }

            if (empty($errors)) {
                try {
                    $db = getDB();
                    $stmt = $db->prepare("
                        INSERT INTO ver_bills
                        (bill_number, bill_type, vendor_name, pan_number, is_non_pan, non_pan_amount, bill_amount, bill_date_bs, bill_date_ad, file_path, file_type, remarks, created_by, status)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'active')
                    ");

                    $stmt->execute([
                        $formData['bill_number'],
                        $formData['bill_type'],
                        $formData['vendor_name'] ?: null,
                        $formData['pan_number'] ?: null,
                        $formData['is_non_pan'],
                        $formData['is_non_pan'] ? $formData['non_pan_amount'] : null,
                        $formData['bill_amount'] ?: null,
                        $formData['bill_date_bs'],
                        $formData['bill_date_ad'] ?: null,
                        $filePath,
                        $fileType,
                        $formData['remarks'] ?: null,
                        $_SESSION['admin_id']
                    ]);

                    $billId = $db->lastInsertId();

                    // Log activity
                    logAdminActivity($_SESSION['admin_id'], 'create_bill', 'ver_bills', $billId, null, $formData);

                    setFlashMessage('success', 'Bill added successfully!');
                    header('Location: manage-bills.php');
                    exit;

                } catch (PDOException $e) {
                    error_log("Bill insert error: " . $e->getMessage());
                    $errors[] = 'Failed to save bill: ' . $e->getMessage();
                    if ($filePath) {
                        @unlink($uploadDir . $filePath);
                    }
                }
            }
        }
    }
}

include __DIR__ . '/../includes/admin_header.php';
?>

<div class="max-w-3xl mx-auto">
    <div class="bg-white rounded-lg shadow p-6">
        <h3 class="text-lg font-semibold text-gray-800 mb-6">Add New Bill</h3>

        <?php if (!empty($errors)): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                <ul class="list-disc list-inside">
                    <?php foreach ($errors as $error): ?>
                        <li><?php echo htmlspecialchars($error); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <form method="POST" action="" enctype="multipart/form-data" class="space-y-6">
            <?php echo csrfField(); ?>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <!-- Bill Number -->
                <div>
                    <label for="bill_number" class="block text-sm font-medium text-gray-700 mb-1">
                        Bill Number <span class="text-red-500">*</span>
                    </label>
                    <input type="text" id="bill_number" name="bill_number"
                           value="<?php echo htmlspecialchars($formData['bill_number']); ?>"
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                           placeholder="e.g., BILL-2081-001" required>
                </div>

                <!-- Bill Type -->
                <div>
                    <label for="bill_type" class="block text-sm font-medium text-gray-700 mb-1">
                        Bill Type
                    </label>
                    <select id="bill_type" name="bill_type"
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        <option value="general" <?php echo $formData['bill_type'] === 'general' ? 'selected' : ''; ?>>General</option>
                        <option value="vat" <?php echo $formData['bill_type'] === 'vat' ? 'selected' : ''; ?>>VAT Bill</option>
                        <option value="purchase" <?php echo $formData['bill_type'] === 'purchase' ? 'selected' : ''; ?>>Purchase Bill</option>
                        <option value="sales" <?php echo $formData['bill_type'] === 'sales' ? 'selected' : ''; ?>>Sales Bill</option>
                        <option value="expense" <?php echo $formData['bill_type'] === 'expense' ? 'selected' : ''; ?>>Expense Bill</option>
                    </select>
                </div>
            </div>

            <!-- Vendor Name -->
            <div>
                <label for="vendor_name" class="block text-sm font-medium text-gray-700 mb-1">
                    Vendor/Supplier Name
                </label>
                <input type="text" id="vendor_name" name="vendor_name"
                       value="<?php echo htmlspecialchars($formData['vendor_name']); ?>"
                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                       placeholder="Name of vendor or supplier">
            </div>

            <!-- Non-PAN Checkbox -->
            <div class="bg-gray-50 p-4 rounded-lg">
                <label class="flex items-center">
                    <input type="checkbox" id="is_non_pan" name="is_non_pan" value="1"
                           <?php echo $formData['is_non_pan'] ? 'checked' : ''; ?>
                           class="rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                           onchange="togglePanFields()">
                    <span class="ml-2 text-sm font-medium text-gray-700">This is a Non-PAN Bill</span>
                </label>
            </div>

            <!-- PAN Fields (shown when not non-PAN) -->
            <div id="pan_fields" class="<?php echo $formData['is_non_pan'] ? 'hidden' : ''; ?>">
                <label for="pan_number" class="block text-sm font-medium text-gray-700 mb-1">
                    PAN Number
                </label>
                <input type="text" id="pan_number" name="pan_number"
                       value="<?php echo htmlspecialchars($formData['pan_number']); ?>"
                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                       placeholder="9-digit PAN number" maxlength="9">
            </div>

            <!-- Non-PAN Amount (shown when non-PAN) -->
            <div id="non_pan_fields" class="<?php echo $formData['is_non_pan'] ? '' : 'hidden'; ?>">
                <label for="non_pan_amount" class="block text-sm font-medium text-gray-700 mb-1">
                    Non-PAN Amount <span class="text-red-500">*</span>
                </label>
                <input type="number" id="non_pan_amount" name="non_pan_amount" step="0.01"
                       value="<?php echo htmlspecialchars($formData['non_pan_amount']); ?>"
                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                       placeholder="Amount in NPR">
            </div>

            <!-- Bill Amount -->
            <div>
                <label for="bill_amount" class="block text-sm font-medium text-gray-700 mb-1">
                    Total Bill Amount
                </label>
                <input type="number" id="bill_amount" name="bill_amount" step="0.01"
                       value="<?php echo htmlspecialchars($formData['bill_amount']); ?>"
                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                       placeholder="Total amount in NPR">
            </div>

            <!-- Date Field with Clickable Panel -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">
                    Bill Date <span class="text-red-500">*</span>
                </label>
                <div id="add_bill_datepicker" class="relative"></div>
            </div>

            <link rel="stylesheet" href="../assets/css/nepali-datepicker.css">
            <script src="../assets/js/nepali-datepicker.js"></script>
            <script>
            document.addEventListener('DOMContentLoaded', function() {
                const initialBsDate = '<?php echo htmlspecialchars($formData['bill_date_bs'] ?: ''); ?>';
                const apiUrl = 'api-dates.php';
                initNepaliDatePicker('add_bill_datepicker', 'bill_date_bs', 'bill_date_ad', initialBsDate, apiUrl);
            });
            </script>

            <!-- File Upload (Optional) -->
            <div>
                <label for="bill_file" class="block text-sm font-medium text-gray-700 mb-1">
                    Bill File (Optional)
                </label>
                <input type="file" id="bill_file" name="bill_file"
                       accept=".pdf,.jpg,.jpeg,.png"
                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                <p class="text-xs text-gray-500 mt-1">Allowed: PDF, JPG, PNG. Max size: 5MB. File upload is optional.</p>
            </div>

            <!-- Remarks -->
            <div>
                <label for="remarks" class="block text-sm font-medium text-gray-700 mb-1">
                    Remarks (Optional)
                </label>
                <textarea id="remarks" name="remarks" rows="3"
                          class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                          placeholder="Additional notes..."><?php echo htmlspecialchars($formData['remarks']); ?></textarea>
            </div>

            <!-- Submit Button -->
            <div class="flex justify-end space-x-4">
                <a href="manage-bills.php"
                   class="px-6 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50">
                    Cancel
                </a>
                <button type="submit"
                        class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 focus:ring-4 focus:ring-blue-300">
                    <i class="fas fa-save mr-2"></i>Save Bill
                </button>
            </div>
        </form>
    </div>
</div>

<script>
// PAN Fields Toggle
function togglePanFields() {
    const isNonPan = document.getElementById('is_non_pan').checked;
    document.getElementById('pan_fields').classList.toggle('hidden', isNonPan);
    document.getElementById('non_pan_fields').classList.toggle('hidden', !isNonPan);
    if (isNonPan) {
        document.getElementById('pan_number').value = '';
    } else {
        document.getElementById('non_pan_amount').value = '';
    }
}
</script>

<?php include __DIR__ . '/../includes/admin_footer.php'; ?>
