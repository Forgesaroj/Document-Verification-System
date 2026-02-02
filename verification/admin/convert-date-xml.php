<?php
/**
 * Convert Nepali Date XML to Optimized JSON Format
 * Run this script once to generate the JSON data file
 */

define('VERIFICATION_PORTAL', true);

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/security.php';
require_once __DIR__ . '/../includes/functions.php';

// Require login
requireAdminLogin();

$xmlPath = __DIR__ . '/../uploads/nepalidate.xml';
$jsonPath = __DIR__ . '/../uploads/nepalidate.json';

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['convert'])) {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid request.';
    } else {
        try {
            // Read XML file
            $content = file_get_contents($xmlPath);

            // Parse date mappings
            preg_match_all(
                '/<UDF:NEPDATEVALUE[^>]*>([^<]+)<\/UDF:NEPDATEVALUE>.*?<UDF:ENGDATEVALUE[^>]*>([^<]+)<\/UDF:ENGDATEVALUE>/s',
                $content,
                $matches,
                PREG_SET_ORDER
            );

            $mappings = [];
            $daysPerMonth = [];
            $minBs = null;
            $maxBs = null;

            foreach ($matches as $match) {
                $bsDate = trim($match[1]); // e.g., 2082-04-01
                $adDateStr = trim($match[2]); // e.g., 17-Jul-2025

                // Convert AD date to standard format
                $adDate = DateTime::createFromFormat('d-M-Y', $adDateStr);
                if (!$adDate) {
                    $adDate = DateTime::createFromFormat('j-M-Y', $adDateStr);
                }

                if ($adDate) {
                    $adFormatted = $adDate->format('Y-m-d');
                    $mappings[$bsDate] = $adFormatted;

                    // Track min/max
                    if ($minBs === null || $bsDate < $minBs) $minBs = $bsDate;
                    if ($maxBs === null || $bsDate > $maxBs) $maxBs = $bsDate;

                    // Calculate days per month
                    $parts = explode('-', $bsDate);
                    $year = (int)$parts[0];
                    $month = (int)$parts[1];
                    $day = (int)$parts[2];

                    if (!isset($daysPerMonth[$year])) {
                        $daysPerMonth[$year] = array_fill(1, 12, 0);
                    }
                    if ($day > $daysPerMonth[$year][$month]) {
                        $daysPerMonth[$year][$month] = $day;
                    }
                }
            }

            // Sort mappings by date
            ksort($mappings);
            ksort($daysPerMonth);

            // Create optimized JSON structure
            $jsonData = [
                'version' => '1.0',
                'generated' => date('Y-m-d H:i:s'),
                'source' => 'nepalidate.xml',
                'range' => [
                    'min_bs' => $minBs,
                    'max_bs' => $maxBs,
                    'total_days' => count($mappings)
                ],
                'daysPerMonth' => $daysPerMonth,
                'mappings' => $mappings
            ];

            // Save JSON file
            $jsonContent = json_encode($jsonData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            file_put_contents($jsonPath, $jsonContent);

            $message = "Successfully converted " . count($mappings) . " date entries to JSON format.";
            $message .= "<br>Date range: $minBs to $maxBs";
            $message .= "<br>File saved to: uploads/nepalidate.json";

        } catch (Exception $e) {
            $error = "Error: " . $e->getMessage();
        }
    }
}

// Check existing files
$xmlExists = file_exists($xmlPath);
$jsonExists = file_exists($jsonPath);
$jsonData = null;

if ($jsonExists) {
    $jsonData = json_decode(file_get_contents($jsonPath), true);
}

$pageTitle = 'Convert Date XML to JSON';
include __DIR__ . '/../includes/admin_header.php';
?>

<div class="max-w-4xl mx-auto">
    <div class="bg-white rounded-lg shadow p-6">
        <h3 class="text-lg font-semibold text-gray-800 mb-4">
            <i class="fas fa-exchange-alt text-blue-600 mr-2"></i>Convert Date XML to JSON
        </h3>

        <?php if ($error): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                <?php echo $error; ?>
            </div>
        <?php endif; ?>

        <?php if ($message): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
            <!-- XML File Status -->
            <div class="bg-gray-50 p-4 rounded-lg">
                <h4 class="font-medium text-gray-700 mb-2">
                    <i class="fas fa-file-code text-orange-500 mr-2"></i>Source XML File
                </h4>
                <?php if ($xmlExists): ?>
                    <p class="text-green-600"><i class="fas fa-check-circle mr-1"></i> File exists</p>
                    <p class="text-sm text-gray-500 mt-1">uploads/nepalidate.xml</p>
                    <p class="text-sm text-gray-500">Size: <?php echo number_format(filesize($xmlPath) / 1024, 2); ?> KB</p>
                <?php else: ?>
                    <p class="text-red-600"><i class="fas fa-times-circle mr-1"></i> File not found</p>
                <?php endif; ?>
            </div>

            <!-- JSON File Status -->
            <div class="bg-gray-50 p-4 rounded-lg">
                <h4 class="font-medium text-gray-700 mb-2">
                    <i class="fas fa-file-alt text-blue-500 mr-2"></i>Output JSON File
                </h4>
                <?php if ($jsonExists && $jsonData): ?>
                    <p class="text-green-600"><i class="fas fa-check-circle mr-1"></i> File exists</p>
                    <p class="text-sm text-gray-500 mt-1">uploads/nepalidate.json</p>
                    <p class="text-sm text-gray-500">Size: <?php echo number_format(filesize($jsonPath) / 1024, 2); ?> KB</p>
                    <p class="text-sm text-gray-500">Generated: <?php echo $jsonData['generated'] ?? 'Unknown'; ?></p>
                    <p class="text-sm text-gray-500">Entries: <?php echo number_format($jsonData['range']['total_days'] ?? 0); ?></p>
                <?php else: ?>
                    <p class="text-yellow-600"><i class="fas fa-exclamation-circle mr-1"></i> Not yet generated</p>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($xmlExists): ?>
            <form method="POST" class="mb-6">
                <?php echo csrfField(); ?>
                <button type="submit" name="convert" value="1"
                        class="w-full px-4 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 font-medium">
                    <i class="fas fa-sync-alt mr-2"></i>Convert XML to JSON
                </button>
            </form>
        <?php endif; ?>

        <?php if ($jsonExists && $jsonData): ?>
            <div class="border-t pt-4">
                <h4 class="font-medium text-gray-700 mb-3">JSON Data Summary</h4>

                <div class="bg-gray-100 rounded-lg p-4 mb-4">
                    <p><strong>Date Range:</strong> <?php echo $jsonData['range']['min_bs']; ?> to <?php echo $jsonData['range']['max_bs']; ?></p>
                    <p><strong>Total Days:</strong> <?php echo number_format($jsonData['range']['total_days']); ?></p>
                    <p><strong>Years Covered:</strong> <?php echo count($jsonData['daysPerMonth']); ?></p>
                </div>

                <h5 class="font-medium text-gray-700 mb-2">Days Per Month by Year:</h5>
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="bg-gray-200">
                            <tr>
                                <th class="px-2 py-1">Year</th>
                                <?php for ($m = 1; $m <= 12; $m++): ?>
                                    <th class="px-2 py-1"><?php echo $m; ?></th>
                                <?php endfor; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($jsonData['daysPerMonth'] as $year => $months): ?>
                                <tr class="border-b">
                                    <td class="px-2 py-1 font-medium"><?php echo $year; ?></td>
                                    <?php for ($m = 1; $m <= 12; $m++): ?>
                                        <td class="px-2 py-1 text-center"><?php echo $months[$m] ?? '-'; ?></td>
                                    <?php endfor; ?>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>

        <div class="mt-6">
            <a href="manage-dates.php" class="text-blue-600 hover:text-blue-800">
                <i class="fas fa-arrow-left mr-1"></i> Back to Date Management
            </a>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/admin_footer.php'; ?>
