<?php
/**
 * Download Document (Admin Only)
 * Secure download handler for generated documents
 */

define('VERIFICATION_PORTAL', true);

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/security.php';
require_once __DIR__ . '/../includes/functions.php';

// Require admin login
requireAdminLogin();

$docId = (int)($_GET['id'] ?? 0);

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

    if (!file_exists($filePath)) {
        setFlashMessage('error', 'Document file not found.');
        header('Location: manage-documents.php');
        exit;
    }

    // Determine MIME type
    $fileExt = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
    $mimeTypes = [
        'pdf' => 'application/pdf',
        'html' => 'text/html',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png'
    ];
    $mimeType = $mimeTypes[$fileExt] ?? 'application/octet-stream';

    // Generate download filename
    $downloadName = preg_replace('/[^a-zA-Z0-9\-_]/', '_', $document['document_number']) . '.' . $fileExt;

    // Log the download
    logAdminActivity($_SESSION['admin_id'], 'download_document', 'ver_documents', $docId, null, [
        'document_number' => $document['document_number'],
        'file_type' => $fileExt
    ]);

    // Send file headers
    header('Content-Type: ' . $mimeType);
    header('Content-Disposition: attachment; filename="' . $downloadName . '"');
    header('Content-Length: ' . filesize($filePath));
    header('Cache-Control: no-cache, no-store, must-revalidate');
    header('Pragma: no-cache');
    header('Expires: 0');

    // Output file
    readfile($filePath);
    exit;

} catch (PDOException $e) {
    error_log("Document download error: " . $e->getMessage());
    setFlashMessage('error', 'Failed to download document.');
    header('Location: manage-documents.php');
    exit;
}
