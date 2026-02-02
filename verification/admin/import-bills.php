<?php
/**
 * Import Bills from Excel/CSV
 */

define('VERIFICATION_PORTAL', true);

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/security.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/date_converter.php';
require_once __DIR__ . '/../includes/nepali_date_helper.php';

// Require login
requireAdminLogin();

$pageTitle = 'Import Bills';
$errors = [];
$success = '';
$importResults = null;

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid request. Please try again.';
    } else {
        $action = $_POST['action'] ?? '';

        if ($action === 'import') {
            if (!isset($_FILES['excel_file']) || $_FILES['excel_file']['error'] === UPLOAD_ERR_NO_FILE) {
                $errors[] = 'Please select a file to import.';
            } else {
                $file = $_FILES['excel_file'];
                $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

                if (!in_array($ext, ['csv', 'xls', 'xlsx'])) {
                    $errors[] = 'Invalid file type. Please upload CSV or Excel file.';
                } else {
                    // Process CSV file
                    if ($ext === 'csv') {
                        $importResults = importFromCSV($file['tmp_name']);
                    } else {
                        $errors[] = 'For Excel files (.xls, .xlsx), please save as CSV first and then upload.';
                    }

                    if ($importResults && $importResults['success'] > 0) {
                        $success = "Successfully imported {$importResults['success']} bills. " .
                                   ($importResults['skipped'] > 0 ? "Skipped {$importResults['skipped']} rows." : '');
                    }
                    if ($importResults && !empty($importResults['errors'])) {
                        $errors = array_merge($errors, $importResults['errors']);
                    }
                }
            }
        }
    }
}

function importFromCSV($filePath) {
    $results = [
        'success' => 0,
        'skipped' => 0,
        'errors' => []
    ];

    $handle = fopen($filePath, 'r');
    if (!$handle) {
        $results['errors'][] = 'Failed to open file.';
        return $results;
    }

    // Read header row
    $header = fgetcsv($handle);
    if (!$header) {
        $results['errors'][] = 'File is empty or invalid format.';
        fclose($handle);
        return $results;
    }

    // Normalize header names
    $header = array_map(function($h) {
        return strtolower(trim(str_replace([' ', '-'], '_', $h)));
    }, $header);

    // Required columns
    $requiredColumns = ['bill_number', 'bill_date_bs'];
    $missingColumns = array_diff($requiredColumns, $header);
    if (!empty($missingColumns)) {
        $results['errors'][] = 'Missing required columns: ' . implode(', ', $missingColumns);
        fclose($handle);
        return $results;
    }

    // Get column indices
    $colIndex = array_flip($header);

    try {
        $db = getDB();
        $rowNum = 1;

        while (($row = fgetcsv($handle)) !== false) {
            $rowNum++;

            // Skip empty rows
            if (empty(array_filter($row))) {
                continue;
            }

            // Get values
            $billNumber = trim($row[$colIndex['bill_number']] ?? '');
            $billDateBs = trim($row[$colIndex['bill_date_bs']] ?? '');

            // Skip if required fields are empty
            if (empty($billNumber) || empty($billDateBs)) {
                $results['skipped']++;
                $results['errors'][] = "Row {$rowNum}: Missing bill number or date.";
                continue;
            }

            // Check if bill already exists
            $stmt = $db->prepare("SELECT id FROM ver_bills WHERE bill_number = ?");
            $stmt->execute([$billNumber]);
            if ($stmt->fetch()) {
                $results['skipped']++;
                $results['errors'][] = "Row {$rowNum}: Bill {$billNumber} already exists.";
                continue;
            }

            // Convert BS to AD
            $billDateAd = convertBsToAd($billDateBs);

            // Get optional fields
            $billType = isset($colIndex['bill_type']) ? trim($row[$colIndex['bill_type']] ?? '') : 'general';
            $vendorName = isset($colIndex['vendor_name']) ? trim($row[$colIndex['vendor_name']] ?? '') : null;
            $panNumber = isset($colIndex['pan_number']) ? trim($row[$colIndex['pan_number']] ?? '') : null;
            $isNonPan = isset($colIndex['is_non_pan']) ? (int)($row[$colIndex['is_non_pan']] ?? 0) : 0;
            $nonPanAmount = isset($colIndex['non_pan_amount']) ? floatval($row[$colIndex['non_pan_amount']] ?? 0) : null;
            $billAmount = isset($colIndex['bill_amount']) ? floatval($row[$colIndex['bill_amount']] ?? 0) : null;
            $remarks = isset($colIndex['remarks']) ? trim($row[$colIndex['remarks']] ?? '') : null;

            // Validate PAN if provided
            if (!$isNonPan && !empty($panNumber) && !isValidPAN($panNumber)) {
                $results['errors'][] = "Row {$rowNum}: Invalid PAN number format.";
                $panNumber = null;
            }

            // Insert bill
            $stmt = $db->prepare("
                INSERT INTO ver_bills
                (bill_number, bill_type, vendor_name, pan_number, is_non_pan, non_pan_amount, bill_amount, bill_date_bs, bill_date_ad, remarks, created_by, status)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'active')
            ");

            $stmt->execute([
                $billNumber,
                $billType ?: 'general',
                $vendorName ?: null,
                $panNumber ?: null,
                $isNonPan,
                $isNonPan && $nonPanAmount ? $nonPanAmount : null,
                $billAmount ?: null,
                $billDateBs,
                $billDateAd,
                $remarks ?: null,
                $_SESSION['admin_id']
            ]);

            $results['success']++;
        }

        // Log activity
        logAdminActivity($_SESSION['admin_id'], 'bulk_import_bills', 'ver_bills', null, null, [
            'imported' => $results['success'],
            'skipped' => $results['skipped']
        ]);

    } catch (PDOException $e) {
        error_log("Import error: " . $e->getMessage());
        $results['errors'][] = 'Database error: ' . $e->getMessage();
    }

    fclose($handle);
    return $results;
}

include __DIR__ . '/../includes/admin_header.php';
?>

<div class="max-w-4xl mx-auto">
    <div class="bg-white rounded-lg shadow p-6 mb-6">
        <h3 class="text-lg font-semibold text-gray-800 mb-4">Import Bills from CSV/Excel</h3>
        <p class="text-gray-600 mb-6">Upload a CSV file to bulk import bills. Download the sample file to see the required format.</p>

        <?php if (!empty($errors)): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4 max-h-48 overflow-y-auto">
                <p class="font-medium mb-2">Import Errors:</p>
                <ul class="list-disc list-inside text-sm">
                    <?php foreach (array_slice($errors, 0, 20) as $error): ?>
                        <li><?php echo htmlspecialchars($error); ?></li>
                    <?php endforeach; ?>
                    <?php if (count($errors) > 20): ?>
                        <li>... and <?php echo count($errors) - 20; ?> more errors</li>
                    <?php endif; ?>
                </ul>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
                <i class="fas fa-check-circle mr-2"></i><?php echo htmlspecialchars($success); ?>
            </div>
        <?php endif; ?>

        <!-- Download Sample -->
        <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-6">
            <div class="flex items-center justify-between">
                <div>
                    <h4 class="font-medium text-blue-800">Sample File</h4>
                    <p class="text-sm text-blue-600">Download the sample CSV to see the required format</p>
                </div>
                <a href="sample-bills.csv" download
                   class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                    <i class="fas fa-download mr-2"></i>Download Sample
                </a>
            </div>
        </div>

        <!-- Upload Form -->
        <form method="POST" action="" enctype="multipart/form-data" class="space-y-6">
            <?php echo csrfField(); ?>
            <input type="hidden" name="action" value="import">

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Select CSV File</label>
                <input type="file" name="excel_file" accept=".csv,.xls,.xlsx"
                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                       required>
                <p class="text-xs text-gray-500 mt-1">Supported formats: CSV (recommended), XLS, XLSX</p>
            </div>

            <button type="submit"
                    class="px-6 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 focus:ring-4 focus:ring-green-300">
                <i class="fas fa-upload mr-2"></i>Import Bills
            </button>
        </form>
    </div>

    <!-- Column Reference -->
    <div class="bg-white rounded-lg shadow p-6">
        <h4 class="text-md font-semibold text-gray-800 mb-4">Column Reference</h4>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-2 text-left">Column Name</th>
                        <th class="px-4 py-2 text-left">Required</th>
                        <th class="px-4 py-2 text-left">Description</th>
                        <th class="px-4 py-2 text-left">Example</th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    <tr>
                        <td class="px-4 py-2 font-mono">bill_number</td>
                        <td class="px-4 py-2"><span class="text-red-600">Yes</span></td>
                        <td class="px-4 py-2">Unique bill identifier</td>
                        <td class="px-4 py-2">BILL-2081-001</td>
                    </tr>
                    <tr>
                        <td class="px-4 py-2 font-mono">bill_date_bs</td>
                        <td class="px-4 py-2"><span class="text-red-600">Yes</span></td>
                        <td class="px-4 py-2">Date in BS format</td>
                        <td class="px-4 py-2">2081-10-15</td>
                    </tr>
                    <tr>
                        <td class="px-4 py-2 font-mono">bill_type</td>
                        <td class="px-4 py-2">No</td>
                        <td class="px-4 py-2">Type of bill</td>
                        <td class="px-4 py-2">general, vat, purchase, sales, expense</td>
                    </tr>
                    <tr>
                        <td class="px-4 py-2 font-mono">vendor_name</td>
                        <td class="px-4 py-2">No</td>
                        <td class="px-4 py-2">Vendor/supplier name</td>
                        <td class="px-4 py-2">ABC Supplies</td>
                    </tr>
                    <tr>
                        <td class="px-4 py-2 font-mono">pan_number</td>
                        <td class="px-4 py-2">No</td>
                        <td class="px-4 py-2">9-digit PAN number</td>
                        <td class="px-4 py-2">123456789</td>
                    </tr>
                    <tr>
                        <td class="px-4 py-2 font-mono">is_non_pan</td>
                        <td class="px-4 py-2">No</td>
                        <td class="px-4 py-2">1 for non-PAN, 0 otherwise</td>
                        <td class="px-4 py-2">0 or 1</td>
                    </tr>
                    <tr>
                        <td class="px-4 py-2 font-mono">non_pan_amount</td>
                        <td class="px-4 py-2">No</td>
                        <td class="px-4 py-2">Amount for non-PAN bills</td>
                        <td class="px-4 py-2">5000.00</td>
                    </tr>
                    <tr>
                        <td class="px-4 py-2 font-mono">bill_amount</td>
                        <td class="px-4 py-2">No</td>
                        <td class="px-4 py-2">Total bill amount</td>
                        <td class="px-4 py-2">15000.00</td>
                    </tr>
                    <tr>
                        <td class="px-4 py-2 font-mono">remarks</td>
                        <td class="px-4 py-2">No</td>
                        <td class="px-4 py-2">Additional notes</td>
                        <td class="px-4 py-2">Office supplies</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/admin_footer.php'; ?>
