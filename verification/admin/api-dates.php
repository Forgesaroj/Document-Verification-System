<?php
/**
 * API Endpoint for Nepali Date Conversion Data
 * Returns date mappings in JSON format for JavaScript use
 * Supports both JSON and XML data sources
 */

define('VERIFICATION_PORTAL', true);

require_once __DIR__ . '/../includes/nepali_date_helper.php';

// Set JSON headers
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Cache-Control: public, max-age=3600'); // Cache for 1 hour

$action = $_GET['action'] ?? 'full';

try {
    switch ($action) {
        case 'mappings':
            // Just the date mappings
            $data = loadNepaliDateData();
            echo json_encode([
                'success' => true,
                'source' => $data['source'],
                'data' => $data['mappings'],
                'count' => $data['count']
            ]);
            break;

        case 'range':
            // Just the date range
            $range = getNepaliDateRange();
            echo json_encode([
                'success' => true,
                'data' => $range
            ]);
            break;

        case 'calendar':
            // Calendar data with days per month
            $data = loadNepaliDateData();
            echo json_encode([
                'success' => true,
                'source' => $data['source'],
                'daysPerMonth' => $data['daysPerMonth'],
                'range' => $data['range']
            ]);
            break;

        case 'convert':
            // Convert a specific date
            $bsDate = $_GET['bs'] ?? '';
            $adDate = $_GET['ad'] ?? '';

            if ($bsDate) {
                $converted = convertBsToAdFromXml($bsDate);
                echo json_encode([
                    'success' => $converted !== null,
                    'bs' => $bsDate,
                    'ad' => $converted,
                    'error' => $converted === null ? 'Date not found in database' : null
                ]);
            } elseif ($adDate) {
                $converted = convertAdToBsFromXml($adDate);
                echo json_encode([
                    'success' => $converted !== null,
                    'ad' => $adDate,
                    'bs' => $converted,
                    'error' => $converted === null ? 'Date not found in database' : null
                ]);
            } else {
                echo json_encode([
                    'success' => false,
                    'error' => 'Please provide bs or ad parameter'
                ]);
            }
            break;

        case 'info':
            // Get info about data source
            $data = loadNepaliDateData();
            $jsonPath = getNepaliDateJsonPath();
            $xmlPath = getNepaliDateXmlPath();

            echo json_encode([
                'success' => true,
                'source' => $data['source'],
                'json_exists' => file_exists($jsonPath),
                'xml_exists' => file_exists($xmlPath),
                'range' => $data['range'],
                'count' => $data['count']
            ]);
            break;

        case 'full':
        default:
            // Full data including mappings, days per month, and range
            $data = loadNepaliDateData();
            echo json_encode([
                'success' => true,
                'source' => $data['source'],
                'mappings' => $data['mappings'],
                'daysPerMonth' => $data['daysPerMonth'],
                'range' => $data['range']
            ]);
            break;
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Internal server error'
    ]);
}
