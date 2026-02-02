<?php
/**
 * Nepali Date Helper
 * Supports both JSON (preferred) and XML formats for date conversion
 * JSON is faster and more efficient for repeated lookups
 */

if (!defined('VERIFICATION_PORTAL')) {
    die('Direct access not permitted');
}

// Cache for loaded data
$_nepaliDateCache = null;

/**
 * Get the path to the Nepali date JSON file
 */
function getNepaliDateJsonPath() {
    return __DIR__ . '/../uploads/nepalidate.json';
}

/**
 * Get the path to the Nepali date XML file
 */
function getNepaliDateXmlPath() {
    return __DIR__ . '/../uploads/nepalidate.xml';
}

/**
 * Load Nepali date data from JSON or XML
 * Prefers JSON for performance, falls back to XML
 */
function loadNepaliDateData() {
    global $_nepaliDateCache;

    // Return cached data if available
    if ($_nepaliDateCache !== null) {
        return $_nepaliDateCache;
    }

    $jsonPath = getNepaliDateJsonPath();
    $xmlPath = getNepaliDateXmlPath();

    // Try JSON first (faster)
    if (file_exists($jsonPath)) {
        $jsonContent = file_get_contents($jsonPath);
        $data = json_decode($jsonContent, true);

        if ($data && isset($data['mappings'])) {
            $_nepaliDateCache = [
                'source' => 'json',
                'mappings' => $data['mappings'],
                'daysPerMonth' => $data['daysPerMonth'] ?? [],
                'range' => $data['range'] ?? [],
                'count' => count($data['mappings'])
            ];
            return $_nepaliDateCache;
        }
    }

    // Fall back to XML parsing
    if (file_exists($xmlPath)) {
        $_nepaliDateCache = parseNepaliDateXmlFile($xmlPath);
        return $_nepaliDateCache;
    }

    // No data available
    $_nepaliDateCache = [
        'source' => 'none',
        'mappings' => [],
        'daysPerMonth' => [],
        'range' => ['min_bs' => null, 'max_bs' => null, 'total_days' => 0],
        'count' => 0
    ];

    return $_nepaliDateCache;
}

/**
 * Parse Nepali date XML file
 */
function parseNepaliDateXmlFile($xmlPath) {
    $dateMappings = [];
    $daysPerMonth = [];

    try {
        $content = file_get_contents($xmlPath);

        // Parse using regex for Tally format
        preg_match_all(
            '/<UDF:NEPDATEVALUE[^>]*>([^<]+)<\/UDF:NEPDATEVALUE>.*?<UDF:ENGDATEVALUE[^>]*>([^<]+)<\/UDF:ENGDATEVALUE>/s',
            $content,
            $matches,
            PREG_SET_ORDER
        );

        $minBs = null;
        $maxBs = null;

        foreach ($matches as $match) {
            $bsDate = trim($match[1]);
            $adDateStr = trim($match[2]);

            // Convert AD date to standard format
            $adDate = DateTime::createFromFormat('d-M-Y', $adDateStr);
            if (!$adDate) {
                $adDate = DateTime::createFromFormat('j-M-Y', $adDateStr);
            }

            if ($adDate) {
                $dateMappings[$bsDate] = $adDate->format('Y-m-d');

                // Track range
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

        return [
            'source' => 'xml',
            'mappings' => $dateMappings,
            'daysPerMonth' => $daysPerMonth,
            'range' => [
                'min_bs' => $minBs,
                'max_bs' => $maxBs,
                'total_days' => count($dateMappings)
            ],
            'count' => count($dateMappings)
        ];

    } catch (Exception $e) {
        error_log("Nepali date XML parse error: " . $e->getMessage());
        return [
            'source' => 'error',
            'mappings' => [],
            'daysPerMonth' => [],
            'range' => [],
            'count' => 0,
            'error' => $e->getMessage()
        ];
    }
}

/**
 * Get BS date range
 */
function getNepaliDateRange() {
    $data = loadNepaliDateData();
    return [
        'min_bs' => $data['range']['min_bs'] ?? null,
        'max_bs' => $data['range']['max_bs'] ?? null,
        'count' => $data['count'] ?? 0
    ];
}

/**
 * Convert BS date to AD using loaded data
 */
function convertBsToAdFromXml($bsDate) {
    $data = loadNepaliDateData();
    return $data['mappings'][$bsDate] ?? null;
}

/**
 * Convert AD date to BS using loaded data
 */
function convertAdToBsFromXml($adDate) {
    static $reverseMappings = null;

    $data = loadNepaliDateData();

    // Build reverse mapping on first call
    if ($reverseMappings === null) {
        $reverseMappings = array_flip($data['mappings']);
    }

    return $reverseMappings[$adDate] ?? null;
}

/**
 * Get calendar data for date picker
 */
function getCalendarDataFromXml() {
    $data = loadNepaliDateData();
    return [
        'mappings' => $data['mappings'],
        'daysPerMonth' => $data['daysPerMonth'],
        'range' => $data['range']
    ];
}

/**
 * Parse Nepali date XML (for backward compatibility)
 */
function parseNepaliDateXml() {
    $data = loadNepaliDateData();
    return [
        'error' => $data['error'] ?? null,
        'data' => $data['mappings'],
        'count' => $data['count']
    ];
}

/**
 * Export date mappings to JSON
 */
function exportDateMappingsToJson() {
    $data = loadNepaliDateData();
    return json_encode($data['mappings']);
}

/**
 * Validate uploaded XML file
 */
function validateNepaliDateXml($filePath) {
    if (!file_exists($filePath)) {
        return ['valid' => false, 'error' => 'File not found'];
    }

    $content = file_get_contents($filePath);

    if (strpos($content, 'NEPDATEVALUE') === false || strpos($content, 'ENGDATEVALUE') === false) {
        return ['valid' => false, 'error' => 'Invalid XML format. Missing date elements.'];
    }

    preg_match_all(
        '/<UDF:NEPDATEVALUE[^>]*>([^<]+)<\/UDF:NEPDATEVALUE>/s',
        $content,
        $matches
    );

    $count = count($matches[1]);

    if ($count < 100) {
        return ['valid' => false, 'error' => 'XML file contains too few date entries (' . $count . ')'];
    }

    return ['valid' => true, 'count' => $count];
}

/**
 * Clear the date data cache
 */
function clearNepaliDateCache() {
    global $_nepaliDateCache;
    $_nepaliDateCache = null;
}

/**
 * Get data source info
 */
function getNepaliDateSource() {
    $data = loadNepaliDateData();
    return $data['source'];
}
