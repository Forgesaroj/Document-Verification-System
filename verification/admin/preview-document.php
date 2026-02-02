<?php
/**
 * Preview Generated Document
 */

define('VERIFICATION_PORTAL', true);

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/security.php';
require_once __DIR__ . '/../includes/functions.php';

// Require login
requireAdminLogin();

$docId = (int)($_GET['id'] ?? 0);
$streamPdf = isset($_GET['stream']);

if ($docId <= 0) {
    header('Location: manage-documents.php');
    exit;
}

try {
    $db = getDB();
    $stmt = $db->prepare("SELECT * FROM ver_documents WHERE id = ? AND status != 'deleted'");
    $stmt->execute([$docId]);
    $document = $stmt->fetch();

    if (!$document) {
        setFlashMessage('error', 'Document not found.');
        header('Location: manage-documents.php');
        exit;
    }

    $filePath = __DIR__ . '/../uploads/documents/' . $document['file_path'];

    // Stream PDF directly if requested
    if ($streamPdf && file_exists($filePath)) {
        $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        $mimeTypes = [
            'pdf' => 'application/pdf',
            'html' => 'text/html',
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png'
        ];
        $mimeType = $mimeTypes[$ext] ?? 'application/octet-stream';

        header('Content-Type: ' . $mimeType);
        header('Content-Disposition: inline; filename="' . basename($filePath) . '"');
        header('Content-Length: ' . filesize($filePath));
        header('Cache-Control: private, max-age=0, must-revalidate');
        header('Pragma: public');
        readfile($filePath);
        exit;
    }

    // Check if it's an HTML document
    if ($document['file_type'] === 'html') {
        if (file_exists($filePath)) {
            // Add print button at top
            $htmlContent = file_get_contents($filePath);

            // Inject print/download buttons
            $buttons = <<<HTML
<div class="no-print" style="position: fixed; top: 10px; right: 10px; z-index: 9999; display: flex; gap: 10px;">
    <button onclick="window.print()" style="padding: 10px 20px; background: #2563eb; color: white; border: none; border-radius: 5px; cursor: pointer; font-size: 14px;">
        <i class="fas fa-print"></i> Print / Save as PDF
    </button>
    <a href="manage-documents.php" style="padding: 10px 20px; background: #6b7280; color: white; border: none; border-radius: 5px; cursor: pointer; font-size: 14px; text-decoration: none;">
        <i class="fas fa-arrow-left"></i> Back
    </a>
</div>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
HTML;

            // Insert buttons after body tag
            $htmlContent = str_replace('<body>', '<body>' . $buttons, $htmlContent);

            echo $htmlContent;
            exit;
        }
    }

    // Check if it's a PDF document
    if ($document['file_type'] === 'pdf' && file_exists($filePath)) {
        // For PDF, display an embedded viewer with admin controls
        $fileName = basename($filePath);
        $docNumber = htmlspecialchars($document['document_number']);
        $docTitle = htmlspecialchars($document['document_title']);
        ?>
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Preview: <?php echo $docNumber; ?> | Company</title>
            <script src="https://cdn.tailwindcss.com"></script>
            <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
            <style>
                @media print {
                    .no-print { display: none !important; }
                }
            </style>
        </head>
        <body class="bg-gray-100">
            <!-- Admin Controls -->
            <div class="no-print bg-white shadow-sm border-b">
                <div class="max-w-6xl mx-auto px-4 py-3 flex items-center justify-between">
                    <div>
                        <h1 class="text-lg font-semibold text-gray-800"><?php echo $docTitle; ?></h1>
                        <p class="text-sm text-gray-500">Reference: <?php echo $docNumber; ?></p>
                    </div>
                    <div class="flex items-center gap-3">
                        <a href="download-document.php?id=<?php echo $docId; ?>"
                           class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 text-sm">
                            <i class="fas fa-download mr-2"></i>Download PDF
                        </a>
                        <button onclick="window.print()"
                                class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 text-sm">
                            <i class="fas fa-print mr-2"></i>Print
                        </button>
                        <a href="manage-documents.php"
                           class="px-4 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700 text-sm">
                            <i class="fas fa-arrow-left mr-2"></i>Back
                        </a>
                    </div>
                </div>
            </div>

            <!-- PDF Viewer -->
            <div class="max-w-5xl mx-auto my-6 shadow-lg bg-white">
                <iframe
                    src="preview-document.php?id=<?php echo $docId; ?>&stream=1"
                    width="100%"
                    height="800"
                    style="min-height: 90vh;"
                    frameborder="0"
                ></iframe>
            </div>
        </body>
        </html>
        <?php
        exit;
    }

    // For other file types, redirect to view
    header('Location: ../public/view-document.php?type=document&id=' . $docId . '&admin=1');
    exit;

} catch (PDOException $e) {
    error_log("Document preview error: " . $e->getMessage());
    setFlashMessage('error', 'Failed to load document.');
    header('Location: manage-documents.php');
    exit;
}
