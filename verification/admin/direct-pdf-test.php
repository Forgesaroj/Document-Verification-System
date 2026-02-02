<?php
/**
 * Direct PDF Test - Simple test to verify PDF serving works
 */

define('VERIFICATION_PORTAL', true);

// Find the latest test PDF
$uploadDir = __DIR__ . '/../uploads/documents/';
$files = glob($uploadDir . 'test_pdf_*.pdf');

if (empty($files)) {
    die('No test PDF files found. Please run test-pdf.php first.');
}

// Get the most recent one
rsort($files);
$filePath = $files[0];
$fileName = basename($filePath);

// Handle PDF serving FIRST before any output
if (isset($_GET['serve'])) {
    if (!file_exists($filePath)) {
        die('PDF file not found');
    }

    // Clear any previous output
    if (ob_get_level()) {
        ob_end_clean();
    }

    header('Content-Type: application/pdf');

    if ($_GET['serve'] === 'download') {
        header('Content-Disposition: attachment; filename="' . $fileName . '"');
    } else {
        header('Content-Disposition: inline; filename="' . $fileName . '"');
    }

    header('Content-Length: ' . filesize($filePath));
    header('Cache-Control: no-cache, must-revalidate');
    header('Pragma: public');
    header('Expires: 0');

    readfile($filePath);
    exit;
}

// Show test page
?>
<!DOCTYPE html>
<html>
<head>
    <title>Direct PDF Test</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .info { background: #f0f0f0; padding: 15px; margin-bottom: 20px; border-radius: 5px; }
        .buttons { margin: 20px 0; }
        .buttons a { display: inline-block; padding: 10px 20px; margin-right: 10px; background: #2563eb; color: white; text-decoration: none; border-radius: 5px; }
        .buttons a.green { background: #16a34a; }
        iframe { border: 1px solid #ccc; }
    </style>
</head>
<body>
    <h1>Direct PDF Test</h1>

    <div class="info">
        <p><strong>File:</strong> <?php echo htmlspecialchars($fileName); ?></p>
        <p><strong>Path:</strong> <?php echo htmlspecialchars($filePath); ?></p>
        <p><strong>Size:</strong> <?php echo filesize($filePath); ?> bytes</p>
        <p><strong>Exists:</strong> <?php echo file_exists($filePath) ? 'Yes' : 'No'; ?></p>
    </div>

    <div class="buttons">
        <a href="direct-pdf-test.php?serve=inline" target="_blank">Open PDF in New Tab</a>
        <a href="direct-pdf-test.php?serve=download" class="green">Download PDF</a>
    </div>

    <h2>Embedded PDF:</h2>
    <iframe src="direct-pdf-test.php?serve=inline" width="100%" height="800px"></iframe>

    <h2>Using Object Tag:</h2>
    <object data="direct-pdf-test.php?serve=inline" type="application/pdf" width="100%" height="600px">
        <p>Your browser doesn't support embedded PDFs. <a href="direct-pdf-test.php?serve=download">Download the PDF</a></p>
    </object>
</body>
</html>
