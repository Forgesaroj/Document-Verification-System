<?php
/**
 * Manage Nepali Date Conversion Data
 * Admin page to view and update the BS/AD date files
 */

define('VERIFICATION_PORTAL', true);

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/security.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/nepali_date_helper.php';

// Require login
requireAdminLogin();

$pageTitle = 'Manage Date Conversion';
$errors = [];
$success = '';

// File paths
$xmlPath = getNepaliDateXmlPath();
$jsonPath = getNepaliDateJsonPath();

// Handle file upload
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid request. Please try again.';
    } else {
        $action = $_POST['action'] ?? '';

        if ($action === 'upload_xml') {
            // Handle XML file upload
            if (isset($_FILES['xml_file']) && $_FILES['xml_file']['error'] === UPLOAD_ERR_OK) {
                $tmpFile = $_FILES['xml_file']['tmp_name'];
                $fileName = $_FILES['xml_file']['name'];

                $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
                if ($ext !== 'xml') {
                    $errors[] = 'Only XML files are allowed.';
                } else {
                    $validation = validateNepaliDateXml($tmpFile);

                    if ($validation['valid']) {
                        // Backup existing file
                        if (file_exists($xmlPath)) {
                            $backupPath = $xmlPath . '.backup.' . date('YmdHis');
                            copy($xmlPath, $backupPath);
                        }

                        if (move_uploaded_file($tmpFile, $xmlPath)) {
                            // Clear cache so new data is loaded
                            clearNepaliDateCache();

                            $success = 'XML file uploaded successfully! ' . $validation['count'] . ' date entries found.';
                            $success .= ' Please convert to JSON for better performance.';

                            logAdminActivity($_SESSION['admin_id'], 'upload_date_xml', 'system', null, null, [
                                'entries' => $validation['count'],
                                'filename' => $fileName
                            ]);
                        } else {
                            $errors[] = 'Failed to save the file.';
                        }
                    } else {
                        $errors[] = $validation['error'];
                    }
                }
            } else {
                $errors[] = 'Please select an XML file to upload.';
            }
        }

        if ($action === 'convert_to_json') {
            // Convert XML to JSON
            try {
                $data = parseNepaliDateXmlFile($xmlPath);

                if (!empty($data['mappings'])) {
                    $jsonData = [
                        'version' => '1.0',
                        'generated' => date('Y-m-d H:i:s'),
                        'source' => 'nepalidate.xml',
                        'range' => $data['range'],
                        'daysPerMonth' => $data['daysPerMonth'],
                        'mappings' => $data['mappings']
                    ];

                    $jsonContent = json_encode($jsonData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
                    file_put_contents($jsonPath, $jsonContent);

                    clearNepaliDateCache();

                    $success = 'Successfully converted ' . count($data['mappings']) . ' date entries to JSON format.';

                    logAdminActivity($_SESSION['admin_id'], 'convert_date_json', 'system', null, null, [
                        'entries' => count($data['mappings'])
                    ]);
                } else {
                    $errors[] = 'No date entries found in XML file.';
                }
            } catch (Exception $e) {
                $errors[] = 'Conversion error: ' . $e->getMessage();
            }
        }
    }
}

// Load current data
$dateData = loadNepaliDateData();
$dateRange = $dateData['range'] ?? [];
$dataSource = $dateData['source'] ?? 'none';

// File status
$xmlExists = file_exists($xmlPath);
$jsonExists = file_exists($jsonPath);

include __DIR__ . '/../includes/admin_header.php';
?>

<div class="max-w-6xl mx-auto">
    <!-- Page Header -->
    <div class="bg-white rounded-lg shadow p-6 mb-6">
        <div class="flex justify-between items-center">
            <div>
                <h3 class="text-lg font-semibold text-gray-800">Nepali Date Conversion Data</h3>
                <p class="text-sm text-gray-600 mt-1">Manage BS (Bikram Sambat) to AD (Gregorian) date conversion data</p>
            </div>
            <div class="flex items-center gap-4">
                <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium <?php echo $dataSource === 'json' ? 'bg-green-100 text-green-800' : ($dataSource === 'xml' ? 'bg-yellow-100 text-yellow-800' : 'bg-red-100 text-red-800'); ?>">
                    <i class="fas fa-database mr-2"></i>
                    Source: <?php echo strtoupper($dataSource); ?>
                </span>
                <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-blue-100 text-blue-800">
                    <i class="fas fa-calendar-alt mr-2"></i>
                    <?php echo number_format($dateData['count'] ?? 0); ?> Entries
                </span>
            </div>
        </div>
    </div>

    <?php if (!empty($errors)): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
            <ul class="list-disc list-inside">
                <?php foreach ($errors as $error): ?>
                    <li><?php echo htmlspecialchars($error); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
            <i class="fas fa-check-circle mr-2"></i><?php echo htmlspecialchars($success); ?>
        </div>
    <?php endif; ?>

    <!-- File Status Cards -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
        <!-- XML File Status -->
        <div class="bg-white rounded-lg shadow p-6">
            <div class="flex items-center justify-between mb-4">
                <h4 class="text-md font-semibold text-gray-800">
                    <i class="fas fa-file-code text-orange-500 mr-2"></i>XML File (Source)
                </h4>
                <?php if ($xmlExists): ?>
                    <span class="px-2 py-1 bg-green-100 text-green-800 text-xs rounded-full">Available</span>
                <?php else: ?>
                    <span class="px-2 py-1 bg-red-100 text-red-800 text-xs rounded-full">Missing</span>
                <?php endif; ?>
            </div>

            <?php if ($xmlExists): ?>
                <div class="space-y-2 text-sm text-gray-600">
                    <p><strong>File:</strong> uploads/nepalidate.xml</p>
                    <p><strong>Size:</strong> <?php echo number_format(filesize($xmlPath) / 1024, 2); ?> KB</p>
                    <p><strong>Modified:</strong> <?php echo date('Y-m-d H:i:s', filemtime($xmlPath)); ?></p>
                </div>
            <?php else: ?>
                <p class="text-gray-500 text-sm">Upload an XML file to get started.</p>
            <?php endif; ?>
        </div>

        <!-- JSON File Status -->
        <div class="bg-white rounded-lg shadow p-6">
            <div class="flex items-center justify-between mb-4">
                <h4 class="text-md font-semibold text-gray-800">
                    <i class="fas fa-file-alt text-blue-500 mr-2"></i>JSON File (Optimized)
                </h4>
                <?php if ($jsonExists): ?>
                    <span class="px-2 py-1 bg-green-100 text-green-800 text-xs rounded-full">Available</span>
                <?php else: ?>
                    <span class="px-2 py-1 bg-yellow-100 text-yellow-800 text-xs rounded-full">Not Generated</span>
                <?php endif; ?>
            </div>

            <?php if ($jsonExists): ?>
                <?php $jsonData = json_decode(file_get_contents($jsonPath), true); ?>
                <div class="space-y-2 text-sm text-gray-600">
                    <p><strong>File:</strong> uploads/nepalidate.json</p>
                    <p><strong>Size:</strong> <?php echo number_format(filesize($jsonPath) / 1024, 2); ?> KB</p>
                    <p><strong>Generated:</strong> <?php echo $jsonData['generated'] ?? 'Unknown'; ?></p>
                    <p><strong>Entries:</strong> <?php echo number_format(count($jsonData['mappings'] ?? [])); ?></p>
                </div>
            <?php else: ?>
                <p class="text-gray-500 text-sm">Convert XML to JSON for faster loading.</p>
            <?php endif; ?>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Date Range Info -->
        <div class="bg-white rounded-lg shadow p-6">
            <h4 class="text-md font-semibold text-gray-800 mb-4">
                <i class="fas fa-info-circle text-blue-600 mr-2"></i>Date Range Information
            </h4>

            <?php if (!empty($dateRange['min_bs'])): ?>
                <div class="space-y-4">
                    <div class="grid grid-cols-2 gap-4">
                        <div class="bg-blue-50 p-4 rounded-lg">
                            <p class="text-sm text-gray-600">Start Date (BS)</p>
                            <p class="text-lg font-semibold text-blue-600"><?php echo htmlspecialchars($dateRange['min_bs']); ?></p>
                        </div>
                        <div class="bg-blue-50 p-4 rounded-lg">
                            <p class="text-sm text-gray-600">End Date (BS)</p>
                            <p class="text-lg font-semibold text-blue-600"><?php echo htmlspecialchars($dateRange['max_bs']); ?></p>
                        </div>
                    </div>

                    <div class="bg-green-50 p-4 rounded-lg">
                        <p class="text-sm text-gray-600">Total Date Entries</p>
                        <p class="text-2xl font-bold text-green-600"><?php echo number_format($dateRange['total_days'] ?? $dateData['count'] ?? 0); ?></p>
                    </div>

                    <!-- Years Available -->
                    <?php if (!empty($dateData['daysPerMonth'])): ?>
                        <div>
                            <p class="text-sm font-medium text-gray-700 mb-2">Years Available:</p>
                            <div class="flex flex-wrap gap-2">
                                <?php
                                $years = array_keys($dateData['daysPerMonth']);
                                sort($years);
                                foreach ($years as $year):
                                ?>
                                    <span class="inline-flex items-center px-2 py-1 rounded bg-blue-100 text-blue-800 text-sm">
                                        <?php echo $year; ?> BS
                                    </span>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="text-center py-8">
                    <i class="fas fa-exclamation-triangle text-yellow-500 text-4xl mb-4"></i>
                    <p class="text-gray-600">No date data available. Please upload an XML file.</p>
                </div>
            <?php endif; ?>
        </div>

        <!-- Actions -->
        <div class="bg-white rounded-lg shadow p-6">
            <h4 class="text-md font-semibold text-gray-800 mb-4">
                <i class="fas fa-tools text-green-600 mr-2"></i>Actions
            </h4>

            <div class="space-y-4">
                <!-- Upload XML -->
                <form method="POST" enctype="multipart/form-data">
                    <?php echo csrfField(); ?>
                    <input type="hidden" name="action" value="upload_xml">

                    <div class="border-2 border-dashed border-gray-300 rounded-lg p-4 text-center mb-4">
                        <i class="fas fa-file-upload text-gray-400 text-2xl mb-2"></i>
                        <p class="text-sm text-gray-600 mb-2">Upload Nepali Date XML</p>
                        <input type="file" name="xml_file" accept=".xml"
                               class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded file:border-0 file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100">
                    </div>

                    <button type="submit"
                            class="w-full px-4 py-2 bg-orange-500 text-white rounded-lg hover:bg-orange-600">
                        <i class="fas fa-upload mr-2"></i>Upload XML File
                    </button>
                </form>

                <!-- Convert to JSON -->
                <?php if ($xmlExists): ?>
                    <form method="POST">
                        <?php echo csrfField(); ?>
                        <input type="hidden" name="action" value="convert_to_json">
                        <button type="submit"
                                class="w-full px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                            <i class="fas fa-sync-alt mr-2"></i>Convert XML to JSON
                        </button>
                    </form>
                    <p class="text-xs text-gray-500 text-center">JSON format loads faster for the date picker</p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Days Per Month Table -->
    <?php if (!empty($dateData['daysPerMonth'])): ?>
        <div class="bg-white rounded-lg shadow p-6 mt-6">
            <h4 class="text-md font-semibold text-gray-800 mb-4">
                <i class="fas fa-table text-purple-600 mr-2"></i>Days Per Month by Year
            </h4>

            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-gray-100">
                        <tr>
                            <th class="px-3 py-2 text-left font-semibold">Year</th>
                            <th class="px-3 py-2 text-center">Bai</th>
                            <th class="px-3 py-2 text-center">Jes</th>
                            <th class="px-3 py-2 text-center">Ash</th>
                            <th class="px-3 py-2 text-center">Shr</th>
                            <th class="px-3 py-2 text-center">Bhd</th>
                            <th class="px-3 py-2 text-center">Asw</th>
                            <th class="px-3 py-2 text-center">Kar</th>
                            <th class="px-3 py-2 text-center">Man</th>
                            <th class="px-3 py-2 text-center">Pou</th>
                            <th class="px-3 py-2 text-center">Mag</th>
                            <th class="px-3 py-2 text-center">Fal</th>
                            <th class="px-3 py-2 text-center">Cha</th>
                            <th class="px-3 py-2 text-center font-semibold">Total</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <?php
                        $years = array_keys($dateData['daysPerMonth']);
                        sort($years);
                        foreach ($years as $year):
                            $months = $dateData['daysPerMonth'][$year];
                            $total = array_sum($months);
                        ?>
                            <tr class="hover:bg-gray-50">
                                <td class="px-3 py-2 font-medium text-blue-600"><?php echo $year; ?></td>
                                <?php for ($m = 1; $m <= 12; $m++): ?>
                                    <td class="px-3 py-2 text-center"><?php echo $months[$m] ?? '-'; ?></td>
                                <?php endfor; ?>
                                <td class="px-3 py-2 text-center font-semibold"><?php echo $total; ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>

    <!-- API Info -->
    <div class="bg-white rounded-lg shadow p-6 mt-6">
        <h4 class="text-md font-semibold text-gray-800 mb-4">
            <i class="fas fa-code text-indigo-600 mr-2"></i>API Endpoints
        </h4>

        <div class="space-y-3 text-sm">
            <div class="bg-gray-50 p-3 rounded">
                <code class="text-indigo-600">api-dates.php?action=mappings</code>
                <span class="text-gray-500 ml-2">- Get all BS to AD mappings</span>
            </div>
            <div class="bg-gray-50 p-3 rounded">
                <code class="text-indigo-600">api-dates.php?action=calendar</code>
                <span class="text-gray-500 ml-2">- Get days per month data</span>
            </div>
            <div class="bg-gray-50 p-3 rounded">
                <code class="text-indigo-600">api-dates.php?action=convert&bs=2082-04-01</code>
                <span class="text-gray-500 ml-2">- Convert BS to AD</span>
            </div>
            <div class="bg-gray-50 p-3 rounded">
                <code class="text-indigo-600">api-dates.php?action=convert&ad=2025-07-17</code>
                <span class="text-gray-500 ml-2">- Convert AD to BS</span>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/admin_footer.php'; ?>
