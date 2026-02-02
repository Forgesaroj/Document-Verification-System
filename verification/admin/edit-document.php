<?php
/**
 * Edit Document Page
 */

define('VERIFICATION_PORTAL', true);

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/security.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/date_converter.php';
require_once __DIR__ . '/../includes/nepali_date_helper.php';

// Require login
requireAdminLogin();

$pageTitle = 'Edit Document';
$errors = [];

// Get document ID
$docId = (int)($_GET['id'] ?? 0);

if ($docId <= 0) {
    setFlashMessage('error', 'Invalid document ID.');
    header('Location: manage-documents.php');
    exit;
}

// Fetch document
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
} catch (PDOException $e) {
    error_log("Document fetch error: " . $e->getMessage());
    setFlashMessage('error', 'Failed to load document.');
    header('Location: manage-documents.php');
    exit;
}

$formData = [
    'document_number' => $document['document_number'],
    'document_title' => $document['document_title'] ?? '',
    'issued_by' => $document['issued_by'],
    'issued_to' => $document['issued_to'] ?? '',
    'document_date_bs' => $document['document_date_bs'],
    'document_date_ad' => $document['document_date_ad'],
    'remarks' => $document['remarks'] ?? ''
];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid request. Please try again.';
    } else {
        $oldData = $formData;

        // Collect form data
        $formData = [
            'document_number' => sanitize($_POST['document_number'] ?? ''),
            'document_title' => sanitize($_POST['document_title'] ?? ''),
            'issued_by' => sanitize($_POST['issued_by'] ?? ''),
            'issued_to' => sanitize($_POST['issued_to'] ?? ''),
            'document_date_bs' => sanitize($_POST['document_date_bs'] ?? ''),
            'document_date_ad' => sanitize($_POST['document_date_ad'] ?? ''),
            'remarks' => sanitize($_POST['remarks'] ?? '')
        ];

        // Validate required fields
        if (empty($formData['document_number'])) {
            $errors[] = 'Document number is required.';
        } elseif (!isValidDocumentNumber($formData['document_number'])) {
            $errors[] = 'Invalid document number format.';
        }

        if (empty($formData['issued_by'])) {
            $errors[] = 'Issued by is required.';
        }

        // Validate date
        if (empty($formData['document_date_bs']) && empty($formData['document_date_ad'])) {
            $errors[] = 'Either BS or AD date is required.';
        }

        // Convert dates
        if (!empty($formData['document_date_bs']) && empty($formData['document_date_ad'])) {
            $adDate = convertBsToAd($formData['document_date_bs']);
            if ($adDate) {
                $formData['document_date_ad'] = $adDate;
            } else {
                $errors[] = 'Invalid BS date format.';
            }
        } elseif (!empty($formData['document_date_ad']) && empty($formData['document_date_bs'])) {
            $bsDate = convertAdToBs($formData['document_date_ad']);
            if ($bsDate) {
                $formData['document_date_bs'] = $bsDate;
            } else {
                $errors[] = 'Invalid AD date format.';
            }
        }

        // Check if document number already exists (for other documents)
        if (empty($errors) && $formData['document_number'] !== $document['document_number']) {
            $stmt = $db->prepare("SELECT id FROM ver_documents WHERE document_number = ? AND id != ?");
            $stmt->execute([$formData['document_number'], $docId]);
            if ($stmt->fetch()) {
                $errors[] = 'A document with this number already exists.';
            }
        }

        // Handle file upload if new file provided
        $newFilePath = null;
        if (isset($_FILES['document_file']) && $_FILES['document_file']['error'] !== UPLOAD_ERR_NO_FILE) {
            $fileValidation = validateUploadedFile($_FILES['document_file']);
            if (!$fileValidation['valid']) {
                $errors[] = $fileValidation['error'];
            } else {
                $uploadDir = __DIR__ . '/../uploads/documents/';
                $uploadResult = uploadFile($_FILES['document_file'], $uploadDir);
                if ($uploadResult['success']) {
                    $newFilePath = $uploadResult['path'];
                } else {
                    $errors[] = $uploadResult['error'];
                }
            }
        }

        // Update record
        if (empty($errors)) {
            try {
                $sql = "UPDATE ver_documents SET
                        document_number = ?,
                        document_title = ?,
                        issued_by = ?,
                        issued_to = ?,
                        document_date_bs = ?,
                        document_date_ad = ?,
                        remarks = ?,
                        updated_by = ?";
                $params = [
                    $formData['document_number'],
                    $formData['document_title'] ?: null,
                    $formData['issued_by'],
                    $formData['issued_to'] ?: null,
                    $formData['document_date_bs'],
                    $formData['document_date_ad'],
                    $formData['remarks'] ?: null,
                    $_SESSION['admin_id']
                ];

                if ($newFilePath) {
                    $sql .= ", file_path = ?, file_type = ?";
                    $params[] = $newFilePath;
                    $params[] = strtolower(pathinfo($newFilePath, PATHINFO_EXTENSION));
                }

                $sql .= " WHERE id = ?";
                $params[] = $docId;

                $stmt = $db->prepare($sql);
                $stmt->execute($params);

                // Delete old file if new file uploaded
                if ($newFilePath && $document['file_path']) {
                    $oldFile = __DIR__ . '/../uploads/documents/' . $document['file_path'];
                    @unlink($oldFile);
                }

                // Log activity
                logAdminActivity($_SESSION['admin_id'], 'update_document', 'ver_documents', $docId, $oldData, $formData);

                setFlashMessage('success', 'Document updated successfully!');
                header('Location: manage-documents.php');
                exit;

            } catch (PDOException $e) {
                error_log("Document update error: " . $e->getMessage());
                $errors[] = 'Failed to update document. Please try again.';
                // Delete uploaded file on error
                if ($newFilePath) {
                    @unlink(__DIR__ . '/../uploads/documents/' . $newFilePath);
                }
            }
        }
    }
}

include __DIR__ . '/../includes/admin_header.php';
?>

<div class="max-w-3xl mx-auto">
    <div class="bg-white rounded-lg shadow p-6">
        <h3 class="text-lg font-semibold text-gray-800 mb-6">Edit Document</h3>

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

            <!-- Document Number -->
            <div>
                <label for="document_number" class="block text-sm font-medium text-gray-700 mb-1">
                    Document Number <span class="text-red-500">*</span>
                </label>
                <input type="text" id="document_number" name="document_number"
                       value="<?php echo htmlspecialchars($formData['document_number']); ?>"
                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                       required>
            </div>

            <!-- Document Title -->
            <div>
                <label for="document_title" class="block text-sm font-medium text-gray-700 mb-1">
                    Document Title (Optional)
                </label>
                <input type="text" id="document_title" name="document_title"
                       value="<?php echo htmlspecialchars($formData['document_title']); ?>"
                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <!-- Issued By -->
                <div>
                    <label for="issued_by" class="block text-sm font-medium text-gray-700 mb-1">
                        Issued By <span class="text-red-500">*</span>
                    </label>
                    <input type="text" id="issued_by" name="issued_by"
                           value="<?php echo htmlspecialchars($formData['issued_by']); ?>"
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                           required>
                </div>

                <!-- Issued To -->
                <div>
                    <label for="issued_to" class="block text-sm font-medium text-gray-700 mb-1">
                        Issued To (Optional)
                    </label>
                    <input type="text" id="issued_to" name="issued_to"
                           value="<?php echo htmlspecialchars($formData['issued_to']); ?>"
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                </div>
            </div>

            <!-- Date Field with Clickable Panel -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">
                    Document Date <span class="text-red-500">*</span>
                </label>
                <div id="document_datepicker" class="relative"></div>
            </div>

            <link rel="stylesheet" href="../assets/css/nepali-datepicker.css">
            <script src="../assets/js/nepali-datepicker.js"></script>
            <script>
            document.addEventListener('DOMContentLoaded', function() {
                const initialBsDate = '<?php echo htmlspecialchars($formData['document_date_bs'] ?: ''); ?>';
                const apiUrl = 'api-dates.php';
                initNepaliDatePicker('document_datepicker', 'document_date_bs', 'document_date_ad', initialBsDate, apiUrl);
            });
            </script>

            <!-- Current File -->
            <div class="bg-gray-50 p-4 rounded-lg">
                <p class="text-sm font-medium text-gray-700 mb-2">Current File</p>
                <div class="flex items-center justify-between">
                    <span class="text-sm text-gray-600">
                        <i class="fas fa-file mr-2"></i>
                        <?php echo htmlspecialchars($document['file_path']); ?>
                    </span>
                    <a href="../public/view-document.php?type=document&id=<?php echo $docId; ?>&admin=1"
                       target="_blank"
                       class="text-blue-600 hover:text-blue-800 text-sm">
                        <i class="fas fa-eye mr-1"></i>View
                    </a>
                </div>
            </div>

            <!-- New File Upload -->
            <div>
                <label for="document_file" class="block text-sm font-medium text-gray-700 mb-1">
                    Replace File (Optional)
                </label>
                <input type="file" id="document_file" name="document_file"
                       accept=".pdf,.jpg,.jpeg,.png"
                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                <p class="text-xs text-gray-500 mt-1">Leave empty to keep current file. Allowed: PDF, JPG, PNG. Max: 5MB</p>
            </div>

            <!-- Remarks -->
            <div>
                <label for="remarks" class="block text-sm font-medium text-gray-700 mb-1">
                    Remarks (Optional)
                </label>
                <textarea id="remarks" name="remarks" rows="3"
                          class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"><?php echo htmlspecialchars($formData['remarks']); ?></textarea>
            </div>

            <!-- Submit Button -->
            <div class="flex justify-end space-x-4">
                <a href="manage-documents.php"
                   class="px-6 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50">
                    Cancel
                </a>
                <button type="submit"
                        class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 focus:ring-4 focus:ring-blue-300">
                    <i class="fas fa-save mr-2"></i>Update Document
                </button>
            </div>
        </form>
    </div>
</div>

<?php include __DIR__ . '/../includes/admin_footer.php'; ?>
