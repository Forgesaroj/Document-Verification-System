<?php
/**
 * Add Document Page
 */

define('VERIFICATION_PORTAL', true);

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/security.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/date_converter.php';
require_once __DIR__ . '/../includes/nepali_date_helper.php';

// Require login
requireAdminLogin();

$pageTitle = 'Add Document';
$errors = [];
$formData = [
    'document_number' => '',
    'document_title' => '',
    'issued_by' => '',
    'issued_to' => '',
    'document_date_bs' => '',
    'document_date_ad' => '',
    'remarks' => ''
];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF token
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid request. Please try again.';
    } else {
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

        // Validate date - at least one is required
        if (empty($formData['document_date_bs']) && empty($formData['document_date_ad'])) {
            $errors[] = 'Either BS or AD date is required.';
        }

        // Convert dates
        if (!empty($formData['document_date_bs']) && empty($formData['document_date_ad'])) {
            $adDate = convertBsToAd($formData['document_date_bs']);
            if ($adDate) {
                $formData['document_date_ad'] = $adDate;
            } else {
                $errors[] = 'Invalid BS date format. Use YYYY-MM-DD.';
            }
        } elseif (!empty($formData['document_date_ad']) && empty($formData['document_date_bs'])) {
            $bsDate = convertAdToBs($formData['document_date_ad']);
            if ($bsDate) {
                $formData['document_date_bs'] = $bsDate;
            } else {
                $errors[] = 'Invalid AD date format. Use YYYY-MM-DD.';
            }
        }

        // Validate file upload
        if (!isset($_FILES['document_file']) || $_FILES['document_file']['error'] === UPLOAD_ERR_NO_FILE) {
            $errors[] = 'Document file is required.';
        } else {
            $fileValidation = validateUploadedFile($_FILES['document_file']);
            if (!$fileValidation['valid']) {
                $errors[] = $fileValidation['error'];
            }
        }

        // Check if document number already exists
        if (empty($errors)) {
            try {
                $db = getDB();
                $stmt = $db->prepare("SELECT id FROM ver_documents WHERE document_number = ?");
                $stmt->execute([$formData['document_number']]);
                if ($stmt->fetch()) {
                    $errors[] = 'A document with this number already exists.';
                }
            } catch (PDOException $e) {
                error_log("Document check error: " . $e->getMessage());
                $errors[] = 'Database error. Please try again.';
            }
        }

        // Upload file and insert record
        if (empty($errors)) {
            $uploadDir = __DIR__ . '/../uploads/documents/';

            // Ensure directory exists
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }

            $uploadResult = uploadFile($_FILES['document_file'], $uploadDir);

            if ($uploadResult['success']) {
                try {
                    $db = getDB();
                    $stmt = $db->prepare("
                        INSERT INTO ver_documents
                        (document_number, document_title, issued_by, issued_to, document_date_bs, document_date_ad, file_path, file_type, remarks, created_by, status)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'active')
                    ");

                    $fileType = strtolower(pathinfo($_FILES['document_file']['name'], PATHINFO_EXTENSION));

                    $stmt->execute([
                        $formData['document_number'],
                        $formData['document_title'] ?: null,
                        $formData['issued_by'],
                        $formData['issued_to'] ?: null,
                        $formData['document_date_bs'],
                        $formData['document_date_ad'],
                        $uploadResult['path'],
                        $fileType,
                        $formData['remarks'] ?: null,
                        $_SESSION['admin_id']
                    ]);

                    $docId = $db->lastInsertId();

                    // Log activity
                    logAdminActivity($_SESSION['admin_id'], 'create_document', 'ver_documents', $docId, null, $formData);

                    setFlashMessage('success', 'Document added successfully!');
                    header('Location: manage-documents.php');
                    exit;

                } catch (PDOException $e) {
                    error_log("Document insert error: " . $e->getMessage());
                    $errors[] = 'Failed to save document. Please try again.';
                    // Delete uploaded file on error
                    @unlink($uploadDir . $uploadResult['path']);
                }
            } else {
                $errors[] = $uploadResult['error'];
            }
        }
    }
}

include __DIR__ . '/../includes/admin_header.php';
?>

<div class="max-w-3xl mx-auto">
    <div class="bg-white rounded-lg shadow p-6">
        <h3 class="text-lg font-semibold text-gray-800 mb-6">Add New Document</h3>

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
                       placeholder="e.g., DOC-2081-001" required>
                <p class="text-xs text-gray-500 mt-1">Alphanumeric, hyphens, underscores, and forward slashes allowed</p>
            </div>

            <!-- Document Title -->
            <div>
                <label for="document_title" class="block text-sm font-medium text-gray-700 mb-1">
                    Document Title (Optional)
                </label>
                <input type="text" id="document_title" name="document_title"
                       value="<?php echo htmlspecialchars($formData['document_title']); ?>"
                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                       placeholder="Brief title or description">
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
                           placeholder="Authority/Person name" required>
                </div>

                <!-- Issued To -->
                <div>
                    <label for="issued_to" class="block text-sm font-medium text-gray-700 mb-1">
                        Issued To (Optional)
                    </label>
                    <input type="text" id="issued_to" name="issued_to"
                           value="<?php echo htmlspecialchars($formData['issued_to']); ?>"
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                           placeholder="Recipient name">
                </div>
            </div>

            <!-- Date Field with Clickable Panel -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">
                    Document Date <span class="text-red-500">*</span>
                </label>
                <div id="document_datepicker" class="relative"></div>
            </div>

            <!-- File Upload -->
            <div>
                <label for="document_file" class="block text-sm font-medium text-gray-700 mb-1">
                    Document File <span class="text-red-500">*</span>
                </label>
                <input type="file" id="document_file" name="document_file"
                       accept=".pdf,.jpg,.jpeg,.png"
                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                       required>
                <p class="text-xs text-gray-500 mt-1">Allowed: PDF, JPG, PNG. Max size: 5MB</p>
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
                <a href="manage-documents.php"
                   class="px-6 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50">
                    Cancel
                </a>
                <button type="submit"
                        class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 focus:ring-4 focus:ring-blue-300">
                    <i class="fas fa-save mr-2"></i>Save Document
                </button>
            </div>
        </form>
    </div>
</div>

<link rel="stylesheet" href="../assets/css/nepali-datepicker.css">
<script src="../assets/js/nepali-datepicker.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Initialize date picker with API URL for XML data
    const initialBsDate = '<?php echo htmlspecialchars($formData['document_date_bs'] ?: ''); ?>';
    const apiUrl = 'api-dates.php';
    initNepaliDatePicker('document_datepicker', 'document_date_bs', 'document_date_ad', initialBsDate, apiUrl);
});
</script>

<?php include __DIR__ . '/../includes/admin_footer.php'; ?>
