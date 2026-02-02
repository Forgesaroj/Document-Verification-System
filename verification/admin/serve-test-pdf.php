<?php
/**
 * Serve Test PDF files
 */

define('VERIFICATION_PORTAL', true);

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/security.php';
require_once __DIR__ . '/../includes/functions.php';

// Require admin login
requireAdminLogin();

$fileName = $_GET['file'] ?? '';
$download = isset($_GET['download']);

// Validate filename (only allow test PDFs)
if (empty($fileName) || !preg_match('/^test_pdf_\d+\.pdf$/', $fileName)) {
    die('Invalid file');
}

$filePath = __DIR__ . '/../uploads/documents/' . $fileName;

if (!file_exists($filePath)) {
    die('File not found');
}

// Send headers
header('Content-Type: application/pdf');
if ($download) {
    header('Content-Disposition: attachment; filename="' . $fileName . '"');
} else {
    header('Content-Disposition: inline; filename="' . $fileName . '"');
}
header('Content-Length: ' . filesize($filePath));
header('Cache-Control: private, max-age=0, must-revalidate');
header('Pragma: public');

readfile($filePath);
exit;
