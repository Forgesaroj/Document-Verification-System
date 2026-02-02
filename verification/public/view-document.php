<?php
/**
 * Secure Document/Bill Viewer
 * - View-only mode (no download)
 * - Watermark overlay
 * - Right-click disabled
 * - Print disabled
 */

define('VERIFICATION_PORTAL', true);

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/security.php';
require_once __DIR__ . '/../includes/functions.php';

$type = sanitize($_GET['type'] ?? 'document'); // document or bill
$id = (int)($_GET['id'] ?? 0);
$isAdmin = isset($_GET['admin']) && isAdminLoggedIn();
$streamFile = isset($_GET['stream']);

// Validate type
if (!in_array($type, ['document', 'bill'])) {
    die('Invalid request.');
}

// Check access
$hasAccess = false;

if ($isAdmin) {
    // Admin has access
    $hasAccess = true;
} else {
    // Check verified session
    if ($type === 'document') {
        if (isset($_SESSION['verified_doc_access']) &&
            $_SESSION['verified_doc_id'] == $id &&
            $_SESSION['verified_doc_expiry'] > time()) {
            $hasAccess = true;
        }
    } else {
        if (isset($_SESSION['verified_bill_access']) &&
            $_SESSION['verified_bill_id'] == $id &&
            $_SESSION['verified_bill_expiry'] > time()) {
            $hasAccess = true;
        }
    }
}

if (!$hasAccess) {
    header('Location: verify-' . $type . '.php');
    exit;
}

// Fetch record
try {
    $db = getDB();

    if ($type === 'document') {
        $stmt = $db->prepare("SELECT * FROM ver_documents WHERE id = ? AND status = 'active'");
    } else {
        $stmt = $db->prepare("SELECT * FROM ver_bills WHERE id = ? AND status = 'active'");
    }

    $stmt->execute([$id]);
    $record = $stmt->fetch();

    if (!$record) {
        die('Record not found.');
    }

} catch (PDOException $e) {
    error_log("View document error: " . $e->getMessage());
    die('An error occurred.');
}

// Get file path
$uploadDir = $type === 'document' ? 'documents' : 'bills';
$hasFile = !empty($record['file_path']);
$filePath = $hasFile ? __DIR__ . '/../uploads/' . $uploadDir . '/' . $record['file_path'] : null;

if ($hasFile && !file_exists($filePath)) {
    $hasFile = false;
}

$fileExt = $hasFile ? strtolower(pathinfo($filePath, PATHINFO_EXTENSION)) : '';
$mimeTypes = [
    'pdf' => 'application/pdf',
    'jpg' => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'png' => 'image/png',
    'html' => 'text/html'
];

$mimeType = $mimeTypes[$fileExt] ?? 'application/octet-stream';

// If streaming file directly (for PDF embed or image src)
if ($streamFile && $hasFile) {
    // For HTML files, output directly
    if ($fileExt === 'html') {
        header('Content-Type: text/html; charset=UTF-8');
        readfile($filePath);
        exit;
    }

    // Security headers to prevent download
    header('Content-Type: ' . $mimeType);
    header('Content-Disposition: inline');
    header('Content-Length: ' . filesize($filePath));
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    header('X-Content-Type-Options: nosniff');

    // Prevent framing
    header('X-Frame-Options: SAMEORIGIN');

    readfile($filePath);
    exit;
}

// Display viewer page
$referenceNumber = $type === 'document' ? $record['document_number'] : $record['bill_number'];
$dateBS = $type === 'document' ? $record['document_date_bs'] : $record['bill_date_bs'];
$issuedBy = $type === 'document' ? $record['issued_by'] : ($record['vendor_name'] ?? 'N/A');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View <?php echo ucfirst($type); ?> | Company</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Disable text selection */
        .no-select {
            -webkit-user-select: none;
            -moz-user-select: none;
            -ms-user-select: none;
            user-select: none;
        }

        /* Watermark overlay */
        .watermark-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            pointer-events: none;
            z-index: 100;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
        }

        .watermark-text {
            position: absolute;
            font-size: 24px;
            color: rgba(0, 0, 0, 0.08);
            font-weight: bold;
            white-space: nowrap;
            transform: rotate(-30deg);
            pointer-events: none;
        }

        /* PDF/Image container */
        .document-container {
            position: relative;
            width: 100%;
            max-width: 900px;
            margin: 0 auto;
            background: white;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        .document-container iframe,
        .document-container img {
            width: 100%;
            height: auto;
            display: block;
        }

        .document-container iframe {
            height: 80vh;
            min-height: 600px;
        }

        /* Print prevention */
        @media print {
            body * {
                display: none !important;
            }
            body::after {
                content: "Printing is disabled for this document.";
                display: block !important;
                font-size: 24px;
                text-align: center;
                padding: 50px;
            }
        }
    </style>
</head>
<body class="bg-gray-200 min-h-screen no-select" oncontextmenu="return false;" ondragstart="return false;">
    <!-- Watermark Overlay -->
    <div class="watermark-overlay">
        <?php
        // Generate multiple watermarks
        $watermarkText = "Verified via Company - View Only";
        for ($i = 0; $i < 50; $i++) {
            $top = rand(0, 100);
            $left = rand(0, 100);
            echo '<span class="watermark-text" style="top: ' . $top . '%; left: ' . $left . '%;">' . htmlspecialchars($watermarkText) . '</span>';
        }
        ?>
    </div>

    <!-- Header -->
    <header class="bg-white shadow-sm relative z-50">
        <div class="max-w-6xl mx-auto px-4 py-4">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-xl font-bold text-gray-800">Company</h1>
                    <p class="text-sm text-gray-500"><?php echo ucfirst($type); ?> Verification</p>
                </div>
                <div class="flex items-center space-x-4">
                    <?php if ($isAdmin): ?>
                        <a href="../admin/manage-<?php echo $type; ?>s.php" class="text-blue-600 hover:text-blue-800">
                            <i class="fas fa-arrow-left mr-1"></i> Back to Admin
                        </a>
                    <?php else: ?>
                        <a href="../index.php" class="text-blue-600 hover:text-blue-800">
                            <i class="fas fa-home mr-1"></i> Home
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </header>

    <main class="max-w-6xl mx-auto px-4 py-8 relative z-10">
        <!-- Document Info -->
        <div class="bg-white rounded-lg shadow-lg p-6 mb-6">
            <div class="flex items-center justify-between flex-wrap gap-4">
                <div>
                    <div class="flex items-center mb-2">
                        <span class="px-3 py-1 bg-green-100 text-green-800 rounded-full text-sm font-medium">
                            <i class="fas fa-check-circle mr-1"></i> Verified
                        </span>
                    </div>
                    <h2 class="text-2xl font-bold text-gray-800">
                        <?php echo ucfirst($type); ?> #<?php echo htmlspecialchars($referenceNumber); ?>
                    </h2>
                    <div class="text-gray-600 mt-1">
                        <?php if ($type === 'document'): ?>
                            <p>Issued By: <?php echo htmlspecialchars($issuedBy); ?></p>
                            <p>Date (BS): <?php echo htmlspecialchars($dateBS); ?></p>
                        <?php else: ?>
                            <p>Vendor: <?php echo htmlspecialchars($issuedBy); ?></p>
                            <p>Bill Date (BS): <?php echo htmlspecialchars($dateBS); ?></p>
                            <?php if (!$record['is_non_pan'] && $record['pan_number']): ?>
                                <p>PAN: <?php echo htmlspecialchars($record['pan_number']); ?></p>
                            <?php elseif ($record['is_non_pan']): ?>
                                <p>Type: Non-PAN Bill</p>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="text-right">
                    <p class="text-sm text-gray-500">
                        <i class="fas fa-info-circle mr-1"></i>
                        View-only mode. Downloading is disabled.
                    </p>
                    <?php if (!$isAdmin): ?>
                        <p class="text-xs text-gray-400 mt-1">
                            Session expires in <?php
                            $expiry = $type === 'document' ? $_SESSION['verified_doc_expiry'] : $_SESSION['verified_bill_expiry'];
                            $remaining = max(0, $expiry - time());
                            echo floor($remaining / 60) . ' minutes';
                            ?>
                        </p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Document Viewer -->
        <div class="document-container rounded-lg overflow-hidden">
            <?php if (!$hasFile): ?>
                <!-- No File - Show Info Only -->
                <div class="p-8 bg-gray-50 text-center">
                    <i class="fas fa-file-invoice text-gray-400 text-6xl mb-4"></i>
                    <h3 class="text-xl font-semibold text-gray-700 mb-2">Document Verified</h3>
                    <p class="text-gray-600">This <?php echo $type; ?> has been verified but does not have an attached file.</p>
                    <p class="text-gray-500 mt-2">The <?php echo $type; ?> details are shown above.</p>
                </div>
            <?php elseif ($fileExt === 'html'): ?>
                <!-- HTML Document Viewer -->
                <iframe
                    src="view-document.php?type=<?php echo $type; ?>&id=<?php echo $id; ?>&stream=1<?php echo $isAdmin ? '&admin=1' : ''; ?>"
                    class="w-full"
                    style="height: 100vh; min-height: 800px;"
                    frameborder="0"
                ></iframe>
            <?php elseif ($fileExt === 'pdf'): ?>
                <!-- PDF Viewer -->
                <iframe
                    src="view-document.php?type=<?php echo $type; ?>&id=<?php echo $id; ?>&stream=1<?php echo $isAdmin ? '&admin=1' : ''; ?>#toolbar=0&navpanes=0&scrollbar=0"
                    class="w-full"
                    frameborder="0"
                ></iframe>
            <?php else: ?>
                <!-- Image Viewer -->
                <div class="p-4 bg-gray-100 text-center">
                    <img
                        src="view-document.php?type=<?php echo $type; ?>&id=<?php echo $id; ?>&stream=1<?php echo $isAdmin ? '&admin=1' : ''; ?>"
                        alt="<?php echo ucfirst($type); ?> Image"
                        class="max-w-full h-auto mx-auto"
                        draggable="false"
                    >
                </div>
            <?php endif; ?>
        </div>

        <!-- Verification Info -->
        <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4 mt-6">
            <div class="flex items-start">
                <i class="fas fa-shield-alt text-yellow-600 text-xl mr-3 mt-1"></i>
                <div>
                    <h3 class="font-medium text-yellow-800">Verification Notice</h3>
                    <p class="text-sm text-yellow-700 mt-1">
                        This <?php echo $type; ?> has been verified through Company's official verification portal.
                        The document is displayed in view-only mode with security measures in place.
                        Downloading, printing, or copying is not permitted.
                    </p>
                </div>
            </div>
        </div>
    </main>

    <!-- Footer -->
    <footer class="bg-white border-t mt-8 relative z-50">
        <div class="max-w-6xl mx-auto px-4 py-6 text-center text-sm text-gray-500">
            <p class="mb-2">
                <i class="fas fa-lock mr-1"></i>
                This document is protected by Company Verification System
            </p>
            <p>&copy; <?php echo date('Y'); ?> Company. All rights reserved.</p>
        </div>
    </footer>

    <script>
        // Disable keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            // Ctrl+S, Ctrl+P, Ctrl+Shift+I, F12
            if (
                (e.ctrlKey && (e.key === 's' || e.key === 'p' || e.key === 'u')) ||
                (e.ctrlKey && e.shiftKey && e.key === 'I') ||
                e.key === 'F12'
            ) {
                e.preventDefault();
                return false;
            }
        });

        // Disable right-click
        document.addEventListener('contextmenu', function(e) {
            e.preventDefault();
            return false;
        });

        // Disable drag
        document.addEventListener('dragstart', function(e) {
            e.preventDefault();
            return false;
        });

        // Warn on print attempt
        window.onbeforeprint = function() {
            alert('Printing is disabled for this document.');
            return false;
        };

        // Additional: Disable copy
        document.addEventListener('copy', function(e) {
            e.preventDefault();
            return false;
        });

        console.log('%cStop!', 'color: red; font-size: 50px; font-weight: bold;');
        console.log('%cThis is a security-protected document viewer.', 'font-size: 16px;');
    </script>
</body>
</html>
