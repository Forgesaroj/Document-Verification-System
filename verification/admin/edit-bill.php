<?php
/**
 * Edit Bill Page
 */

define('VERIFICATION_PORTAL', true);

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/security.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/date_converter.php';
require_once __DIR__ . '/../includes/nepali_date_helper.php';

// Require login
requireAdminLogin();

$pageTitle = 'Edit Bill';
$errors = [];

// Get bill ID
$billId = (int)($_GET['id'] ?? 0);

if ($billId <= 0) {
    setFlashMessage('error', 'Invalid bill ID.');
    header('Location: manage-bills.php');
    exit;
}

// Fetch bill
try {
    $db = getDB();
    $stmt = $db->prepare("SELECT * FROM ver_bills WHERE id = ? AND status != 'deleted'");
    $stmt->execute([$billId]);
    $bill = $stmt->fetch();

    if (!$bill) {
        setFlashMessage('error', 'Bill not found.');
        header('Location: manage-bills.php');
        exit;
    }
} catch (PDOException $e) {
    error_log("Bill fetch error: " . $e->getMessage());
    setFlashMessage('error', 'Failed to load bill.');
    header('Location: manage-bills.php');
    exit;
}

$formData = [
    'bill_number' => $bill['bill_number'],
    'bill_type' => $bill['bill_type'] ?? 'general',
    'vendor_name' => $bill['vendor_name'] ?? '',
    'pan_number' => $bill['pan_number'] ?? '',
    'is_non_pan' => $bill['is_non_pan'],
    'non_pan_amount' => $bill['non_pan_amount'] ?? '',
    'bill_amount' => $bill['bill_amount'] ?? '',
    'bill_date_bs' => $bill['bill_date_bs'],
    'bill_date_ad' => $bill['bill_date_ad'] ?? '',
    'remarks' => $bill['remarks'] ?? ''
];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid request. Please try again.';
    } else {
        $oldData = $formData;

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

        // Validate BS date
        if (empty($formData['bill_date_bs'])) {
            $errors[] = 'Bill date (BS) is required.';
        } else {
            $adDate = convertBsToAd($formData['bill_date_bs']);
            if ($adDate) {
                $formData['bill_date_ad'] = $adDate;
            } else {
                $errors[] = 'Invalid BS date format.';
            }
        }

        // Validate PAN/Non-PAN
        if ($formData['is_non_pan']) {
            $formData['pan_number'] = '';
            if (empty($formData['non_pan_amount']) || !is_numeric($formData['non_pan_amount'])) {
                $errors[] = 'Non-PAN amount is required.';
            }
        } else {
            if (!empty($formData['pan_number']) && !isValidPAN($formData['pan_number'])) {
                $errors[] = 'Invalid PAN number format.';
            }
        }

        // Check if bill number already exists (for other bills)
        if (empty($errors) && $formData['bill_number'] !== $bill['bill_number']) {
            $stmt = $db->prepare("SELECT id FROM ver_bills WHERE bill_number = ? AND id != ?");
            $stmt->execute([$formData['bill_number'], $billId]);
            if ($stmt->fetch()) {
                $errors[] = 'A bill with this number already exists.';
            }
        }

        // Handle file upload
        $newFilePath = null;
        if (isset($_FILES['bill_file']) && $_FILES['bill_file']['error'] !== UPLOAD_ERR_NO_FILE) {
            $fileValidation = validateUploadedFile($_FILES['bill_file']);
            if (!$fileValidation['valid']) {
                $errors[] = $fileValidation['error'];
            } else {
                $uploadDir = __DIR__ . '/../uploads/bills/';
                $uploadResult = uploadFile($_FILES['bill_file'], $uploadDir);
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
                $sql = "UPDATE ver_bills SET
                        bill_number = ?,
                        bill_type = ?,
                        vendor_name = ?,
                        pan_number = ?,
                        is_non_pan = ?,
                        non_pan_amount = ?,
                        bill_amount = ?,
                        bill_date_bs = ?,
                        bill_date_ad = ?,
                        remarks = ?,
                        updated_by = ?";
                $params = [
                    $formData['bill_number'],
                    $formData['bill_type'],
                    $formData['vendor_name'] ?: null,
                    $formData['pan_number'] ?: null,
                    $formData['is_non_pan'],
                    $formData['is_non_pan'] ? $formData['non_pan_amount'] : null,
                    $formData['bill_amount'] ?: null,
                    $formData['bill_date_bs'],
                    $formData['bill_date_ad'] ?: null,
                    $formData['remarks'] ?: null,
                    $_SESSION['admin_id']
                ];

                if ($newFilePath) {
                    $sql .= ", file_path = ?, file_type = ?";
                    $params[] = $newFilePath;
                    $params[] = strtolower(pathinfo($newFilePath, PATHINFO_EXTENSION));
                }

                $sql .= " WHERE id = ?";
                $params[] = $billId;

                $stmt = $db->prepare($sql);
                $stmt->execute($params);

                // Delete old file if new file uploaded
                if ($newFilePath && $bill['file_path']) {
                    @unlink(__DIR__ . '/../uploads/bills/' . $bill['file_path']);
                }

                // Log activity
                logAdminActivity($_SESSION['admin_id'], 'update_bill', 'ver_bills', $billId, $oldData, $formData);

                setFlashMessage('success', 'Bill updated successfully!');
                header('Location: manage-bills.php');
                exit;

            } catch (PDOException $e) {
                error_log("Bill update error: " . $e->getMessage());
                $errors[] = 'Failed to update bill. Please try again.';
                if ($newFilePath) {
                    @unlink(__DIR__ . '/../uploads/bills/' . $newFilePath);
                }
            }
        }
    }
}

include __DIR__ . '/../includes/admin_header.php';
?>

<div class="max-w-3xl mx-auto">
    <div class="bg-white rounded-lg shadow p-6">
        <h3 class="text-lg font-semibold text-gray-800 mb-6">Edit Bill</h3>

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
                           required>
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
                        <option value="purchase" <?php echo $formData['bill_type'] === 'purchase' ? 'selected' : ''; ?>>Purchase</option>
                        <option value="sales" <?php echo $formData['bill_type'] === 'sales' ? 'selected' : ''; ?>>Sales</option>
                        <option value="expense" <?php echo $formData['bill_type'] === 'expense' ? 'selected' : ''; ?>>Expense</option>
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
                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
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

            <!-- PAN Fields -->
            <div id="pan_fields" class="<?php echo $formData['is_non_pan'] ? 'hidden' : ''; ?>">
                <label for="pan_number" class="block text-sm font-medium text-gray-700 mb-1">
                    PAN Number
                </label>
                <input type="text" id="pan_number" name="pan_number"
                       value="<?php echo htmlspecialchars($formData['pan_number']); ?>"
                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                       maxlength="9">
            </div>

            <!-- Non-PAN Amount -->
            <div id="non_pan_fields" class="<?php echo $formData['is_non_pan'] ? '' : 'hidden'; ?>">
                <label for="non_pan_amount" class="block text-sm font-medium text-gray-700 mb-1">
                    Non-PAN Amount <span class="text-red-500">*</span>
                </label>
                <input type="number" id="non_pan_amount" name="non_pan_amount" step="0.01"
                       value="<?php echo htmlspecialchars($formData['non_pan_amount']); ?>"
                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
            </div>

            <!-- Bill Amount -->
            <div>
                <label for="bill_amount" class="block text-sm font-medium text-gray-700 mb-1">
                    Total Bill Amount
                </label>
                <input type="number" id="bill_amount" name="bill_amount" step="0.01"
                       value="<?php echo htmlspecialchars($formData['bill_amount']); ?>"
                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
            </div>

            <!-- Date Field with Clickable Panel -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">
                    Bill Date <span class="text-red-500">*</span>
                </label>
                <div id="bill_datepicker" class="relative"></div>
            </div>

            <link rel="stylesheet" href="../assets/css/nepali-datepicker.css">
            <script src="../assets/js/nepali-datepicker.js"></script>
            <script>
            document.addEventListener('DOMContentLoaded', function() {
                const initialBsDate = '<?php echo htmlspecialchars($formData['bill_date_bs'] ?: ''); ?>';
                const apiUrl = 'api-dates.php';
                initNepaliDatePicker('bill_datepicker', 'bill_date_bs', 'bill_date_ad', initialBsDate, apiUrl);
            });
            </script>

            <!-- Current File -->
            <div class="bg-gray-50 p-4 rounded-lg">
                <p class="text-sm font-medium text-gray-700 mb-2">Current File</p>
                <div class="flex items-center justify-between">
                    <span class="text-sm text-gray-600">
                        <i class="fas fa-file mr-2"></i>
                        <?php echo htmlspecialchars($bill['file_path']); ?>
                    </span>
                    <a href="../public/view-document.php?type=bill&id=<?php echo $billId; ?>&admin=1"
                       target="_blank"
                       class="text-blue-600 hover:text-blue-800 text-sm">
                        <i class="fas fa-eye mr-1"></i>View
                    </a>
                </div>
            </div>

            <!-- New File Upload -->
            <div>
                <label for="bill_file" class="block text-sm font-medium text-gray-700 mb-1">
                    Replace File (Optional)
                </label>
                <input type="file" id="bill_file" name="bill_file"
                       accept=".pdf,.jpg,.jpeg,.png"
                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                <p class="text-xs text-gray-500 mt-1">Leave empty to keep current file</p>
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
                <a href="manage-bills.php"
                   class="px-6 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50">
                    Cancel
                </a>
                <button type="submit"
                        class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 focus:ring-4 focus:ring-blue-300">
                    <i class="fas fa-save mr-2"></i>Update Bill
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function togglePanFields() {
    const isNonPan = document.getElementById('is_non_pan').checked;
    document.getElementById('pan_fields').classList.toggle('hidden', isNonPan);
    document.getElementById('non_pan_fields').classList.toggle('hidden', !isNonPan);
}
</script>

<?php include __DIR__ . '/../includes/admin_footer.php'; ?>
