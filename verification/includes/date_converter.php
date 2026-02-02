<?php
/**
 * Nepali Date Converter
 * Converts dates between Bikram Sambat (BS) and Anno Domini (AD)
 */

// Prevent direct access
if (!defined('VERIFICATION_PORTAL')) {
    die('Direct access not permitted');
}

class NepaliDateConverter {
    // Nepali months data for conversion (BS years 2000-2090)
    private static $bsMonthDays = [
        2000 => [30, 32, 31, 32, 31, 30, 30, 30, 29, 30, 29, 31],
        2001 => [31, 31, 32, 31, 31, 31, 30, 29, 30, 29, 30, 30],
        2002 => [31, 31, 32, 32, 31, 30, 30, 29, 30, 29, 30, 30],
        2003 => [31, 32, 31, 32, 31, 30, 30, 30, 29, 29, 30, 31],
        2004 => [30, 32, 31, 32, 31, 30, 30, 30, 29, 30, 29, 31],
        2005 => [31, 31, 32, 31, 31, 31, 30, 29, 30, 29, 30, 30],
        2006 => [31, 31, 32, 32, 31, 30, 30, 29, 30, 29, 30, 30],
        2007 => [31, 32, 31, 32, 31, 30, 30, 30, 29, 29, 30, 31],
        2008 => [31, 31, 31, 32, 31, 31, 29, 30, 30, 29, 29, 31],
        2009 => [31, 31, 32, 31, 31, 31, 30, 29, 30, 29, 30, 30],
        2010 => [31, 31, 32, 32, 31, 30, 30, 29, 30, 29, 30, 30],
        2011 => [31, 32, 31, 32, 31, 30, 30, 30, 29, 29, 30, 31],
        2012 => [31, 31, 31, 32, 31, 31, 29, 30, 30, 29, 30, 30],
        2013 => [31, 31, 32, 31, 31, 31, 30, 29, 30, 29, 30, 30],
        2014 => [31, 31, 32, 32, 31, 30, 30, 29, 30, 29, 30, 30],
        2015 => [31, 32, 31, 32, 31, 30, 30, 30, 29, 29, 30, 31],
        2016 => [31, 31, 31, 32, 31, 31, 29, 30, 30, 29, 30, 30],
        2017 => [31, 31, 32, 31, 31, 31, 30, 29, 30, 29, 30, 30],
        2018 => [31, 32, 31, 32, 31, 30, 30, 29, 30, 29, 30, 30],
        2019 => [31, 32, 31, 32, 31, 30, 30, 30, 29, 30, 29, 31],
        2020 => [31, 31, 31, 32, 31, 31, 30, 29, 30, 29, 30, 30],
        2021 => [31, 31, 32, 31, 31, 31, 30, 29, 30, 29, 30, 30],
        2022 => [31, 32, 31, 32, 31, 30, 30, 30, 29, 29, 30, 30],
        2023 => [31, 32, 31, 32, 31, 30, 30, 30, 29, 30, 29, 31],
        2024 => [31, 31, 31, 32, 31, 31, 30, 29, 30, 29, 30, 30],
        2025 => [31, 31, 32, 31, 31, 31, 30, 29, 30, 29, 30, 30],
        2026 => [31, 32, 31, 32, 31, 30, 30, 30, 29, 29, 30, 31],
        2027 => [30, 32, 31, 32, 31, 30, 30, 30, 29, 30, 29, 31],
        2028 => [31, 31, 32, 31, 31, 31, 30, 29, 30, 29, 30, 30],
        2029 => [31, 31, 32, 31, 32, 30, 30, 29, 30, 29, 30, 30],
        2030 => [31, 32, 31, 32, 31, 30, 30, 30, 29, 29, 30, 31],
        2031 => [30, 32, 31, 32, 31, 30, 30, 30, 29, 30, 29, 31],
        2032 => [31, 31, 32, 31, 31, 31, 30, 29, 30, 29, 30, 30],
        2033 => [31, 31, 32, 32, 31, 30, 30, 29, 30, 29, 30, 30],
        2034 => [31, 32, 31, 32, 31, 30, 30, 30, 29, 29, 30, 31],
        2035 => [30, 32, 31, 32, 31, 31, 29, 30, 30, 29, 29, 31],
        2036 => [31, 31, 32, 31, 31, 31, 30, 29, 30, 29, 30, 30],
        2037 => [31, 31, 32, 32, 31, 30, 30, 29, 30, 29, 30, 30],
        2038 => [31, 32, 31, 32, 31, 30, 30, 30, 29, 29, 30, 31],
        2039 => [31, 31, 31, 32, 31, 31, 29, 30, 30, 29, 30, 30],
        2040 => [31, 31, 32, 31, 31, 31, 30, 29, 30, 29, 30, 30],
        2041 => [31, 31, 32, 32, 31, 30, 30, 29, 30, 29, 30, 30],
        2042 => [31, 32, 31, 32, 31, 30, 30, 30, 29, 29, 30, 31],
        2043 => [31, 31, 31, 32, 31, 31, 29, 30, 30, 29, 30, 30],
        2044 => [31, 31, 32, 31, 31, 31, 30, 29, 30, 29, 30, 30],
        2045 => [31, 32, 31, 32, 31, 30, 30, 29, 30, 29, 30, 30],
        2046 => [31, 32, 31, 32, 31, 30, 30, 30, 29, 29, 30, 31],
        2047 => [31, 31, 31, 32, 31, 31, 30, 29, 30, 29, 30, 30],
        2048 => [31, 31, 32, 31, 31, 31, 30, 29, 30, 29, 30, 30],
        2049 => [31, 32, 31, 32, 31, 30, 30, 30, 29, 29, 30, 30],
        2050 => [31, 32, 31, 32, 31, 30, 30, 30, 29, 30, 29, 31],
        2051 => [31, 31, 31, 32, 31, 31, 30, 29, 30, 29, 30, 30],
        2052 => [31, 31, 32, 31, 31, 31, 30, 29, 30, 29, 30, 30],
        2053 => [31, 32, 31, 32, 31, 30, 30, 30, 29, 29, 30, 30],
        2054 => [31, 32, 31, 32, 31, 30, 30, 30, 29, 30, 29, 31],
        2055 => [31, 31, 32, 31, 31, 31, 30, 29, 30, 29, 30, 30],
        2056 => [31, 31, 32, 31, 32, 30, 30, 29, 30, 29, 30, 30],
        2057 => [31, 32, 31, 32, 31, 30, 30, 30, 29, 29, 30, 31],
        2058 => [30, 32, 31, 32, 31, 30, 30, 30, 29, 30, 29, 31],
        2059 => [31, 31, 32, 31, 31, 31, 30, 29, 30, 29, 30, 30],
        2060 => [31, 31, 32, 32, 31, 30, 30, 29, 30, 29, 30, 30],
        2061 => [31, 32, 31, 32, 31, 30, 30, 30, 29, 29, 30, 31],
        2062 => [30, 32, 31, 32, 31, 31, 29, 30, 29, 30, 29, 31],
        2063 => [31, 31, 32, 31, 31, 31, 30, 29, 30, 29, 30, 30],
        2064 => [31, 31, 32, 32, 31, 30, 30, 29, 30, 29, 30, 30],
        2065 => [31, 32, 31, 32, 31, 30, 30, 30, 29, 29, 30, 31],
        2066 => [31, 31, 31, 32, 31, 31, 29, 30, 30, 29, 29, 31],
        2067 => [31, 31, 32, 31, 31, 31, 30, 29, 30, 29, 30, 30],
        2068 => [31, 31, 32, 32, 31, 30, 30, 29, 30, 29, 30, 30],
        2069 => [31, 32, 31, 32, 31, 30, 30, 30, 29, 29, 30, 31],
        2070 => [31, 31, 31, 32, 31, 31, 29, 30, 30, 29, 30, 30],
        2071 => [31, 31, 32, 31, 31, 31, 30, 29, 30, 29, 30, 30],
        2072 => [31, 32, 31, 32, 31, 30, 30, 29, 30, 29, 30, 30],
        2073 => [31, 32, 31, 32, 31, 30, 30, 30, 29, 29, 30, 31],
        2074 => [31, 31, 31, 32, 31, 31, 30, 29, 30, 29, 30, 30],
        2075 => [31, 31, 32, 31, 31, 31, 30, 29, 30, 29, 30, 30],
        2076 => [31, 32, 31, 32, 31, 30, 30, 30, 29, 29, 30, 30],
        2077 => [31, 32, 31, 32, 31, 30, 30, 30, 29, 30, 29, 31],
        2078 => [31, 31, 31, 32, 31, 31, 30, 29, 30, 29, 30, 30],
        2079 => [31, 31, 32, 31, 31, 31, 30, 29, 30, 29, 30, 30],
        2080 => [31, 32, 31, 32, 31, 30, 30, 30, 29, 29, 30, 30],
        2081 => [31, 32, 31, 32, 31, 30, 30, 30, 29, 30, 29, 31],
        2082 => [31, 31, 32, 31, 31, 31, 30, 29, 30, 29, 30, 30],
        2083 => [31, 31, 32, 31, 31, 31, 30, 29, 30, 29, 30, 30],
        2084 => [31, 32, 31, 32, 31, 30, 30, 30, 29, 29, 30, 31],
        2085 => [30, 32, 31, 32, 31, 30, 30, 30, 29, 30, 29, 31],
        2086 => [31, 31, 32, 31, 31, 31, 30, 29, 30, 29, 30, 30],
        2087 => [31, 31, 32, 32, 31, 30, 30, 29, 30, 29, 30, 30],
        2088 => [31, 32, 31, 32, 31, 30, 30, 30, 29, 29, 30, 31],
        2089 => [30, 32, 31, 32, 31, 31, 29, 30, 29, 30, 29, 31],
        2090 => [31, 31, 32, 31, 31, 31, 30, 29, 30, 29, 30, 30],
    ];

    // Reference date: BS 2000/01/01 = AD 1943/04/14
    private static $refBsYear = 2000;
    private static $refBsMonth = 1;
    private static $refBsDay = 1;
    private static $refAdYear = 1943;
    private static $refAdMonth = 4;
    private static $refAdDay = 14;

    /**
     * Convert BS date to AD date
     *
     * @param int $bsYear BS year
     * @param int $bsMonth BS month (1-12)
     * @param int $bsDay BS day
     * @return array|null ['year' => int, 'month' => int, 'day' => int] or null on error
     */
    public static function bsToAd($bsYear, $bsMonth, $bsDay) {
        // Validate input
        if (!self::isValidBsDate($bsYear, $bsMonth, $bsDay)) {
            return null;
        }

        // Calculate total days from reference
        $totalDays = 0;

        // Add days for complete years
        for ($y = self::$refBsYear; $y < $bsYear; $y++) {
            if (isset(self::$bsMonthDays[$y])) {
                $totalDays += array_sum(self::$bsMonthDays[$y]);
            }
        }

        // Add days for complete months in current year
        if (isset(self::$bsMonthDays[$bsYear])) {
            for ($m = 0; $m < $bsMonth - 1; $m++) {
                $totalDays += self::$bsMonthDays[$bsYear][$m];
            }
        }

        // Add remaining days
        $totalDays += $bsDay - 1;

        // Convert to AD by adding days to reference date
        $adDate = new DateTime(self::$refAdYear . '-' . self::$refAdMonth . '-' . self::$refAdDay);
        $adDate->add(new DateInterval('P' . $totalDays . 'D'));

        return [
            'year' => (int)$adDate->format('Y'),
            'month' => (int)$adDate->format('m'),
            'day' => (int)$adDate->format('d')
        ];
    }

    /**
     * Convert AD date to BS date
     *
     * @param int $adYear AD year
     * @param int $adMonth AD month (1-12)
     * @param int $adDay AD day
     * @return array|null ['year' => int, 'month' => int, 'day' => int] or null on error
     */
    public static function adToBs($adYear, $adMonth, $adDay) {
        // Create date objects
        $refDate = new DateTime(self::$refAdYear . '-' . self::$refAdMonth . '-' . self::$refAdDay);
        $inputDate = new DateTime($adYear . '-' . $adMonth . '-' . $adDay);

        // Check if date is before reference
        if ($inputDate < $refDate) {
            return null;
        }

        // Calculate days difference
        $diff = $refDate->diff($inputDate);
        $totalDays = $diff->days;

        // Start from reference BS date
        $bsYear = self::$refBsYear;
        $bsMonth = 1;
        $bsDay = 1;

        // Count through days
        while ($totalDays > 0) {
            if (!isset(self::$bsMonthDays[$bsYear])) {
                return null;
            }

            $daysInMonth = self::$bsMonthDays[$bsYear][$bsMonth - 1];

            if ($bsDay + $totalDays <= $daysInMonth) {
                $bsDay += $totalDays;
                $totalDays = 0;
            } else {
                $totalDays -= ($daysInMonth - $bsDay + 1);
                $bsMonth++;
                $bsDay = 1;

                if ($bsMonth > 12) {
                    $bsMonth = 1;
                    $bsYear++;
                }
            }
        }

        return [
            'year' => $bsYear,
            'month' => $bsMonth,
            'day' => $bsDay
        ];
    }

    /**
     * Validate BS date
     *
     * @param int $year BS year
     * @param int $month BS month
     * @param int $day BS day
     * @return bool
     */
    public static function isValidBsDate($year, $month, $day) {
        if (!isset(self::$bsMonthDays[$year])) {
            return false;
        }

        if ($month < 1 || $month > 12) {
            return false;
        }

        if ($day < 1 || $day > self::$bsMonthDays[$year][$month - 1]) {
            return false;
        }

        return true;
    }

    /**
     * Format BS date as string
     *
     * @param int $year Year
     * @param int $month Month
     * @param int $day Day
     * @param string $separator Separator character
     * @return string Formatted date
     */
    public static function formatBsDate($year, $month, $day, $separator = '-') {
        return sprintf('%04d%s%02d%s%02d', $year, $separator, $month, $separator, $day);
    }

    /**
     * Parse BS date string
     *
     * @param string $dateStr Date string (YYYY-MM-DD or YYYY/MM/DD)
     * @return array|null ['year' => int, 'month' => int, 'day' => int]
     */
    public static function parseBsDate($dateStr) {
        // Support both - and / separators
        $dateStr = str_replace('/', '-', $dateStr);
        $parts = explode('-', $dateStr);

        if (count($parts) !== 3) {
            return null;
        }

        $year = (int)$parts[0];
        $month = (int)$parts[1];
        $day = (int)$parts[2];

        if (!self::isValidBsDate($year, $month, $day)) {
            return null;
        }

        return ['year' => $year, 'month' => $month, 'day' => $day];
    }

    /**
     * Get days in BS month
     *
     * @param int $year BS year
     * @param int $month BS month
     * @return int|null Days in month
     */
    public static function getDaysInBsMonth($year, $month) {
        if (!isset(self::$bsMonthDays[$year]) || $month < 1 || $month > 12) {
            return null;
        }
        return self::$bsMonthDays[$year][$month - 1];
    }

    /**
     * Get Nepali month name
     *
     * @param int $month Month number (1-12)
     * @return string Month name
     */
    public static function getNepaliMonthName($month) {
        $months = [
            1 => 'Baisakh',
            2 => 'Jestha',
            3 => 'Ashadh',
            4 => 'Shrawan',
            5 => 'Bhadra',
            6 => 'Ashwin',
            7 => 'Kartik',
            8 => 'Mangsir',
            9 => 'Poush',
            10 => 'Magh',
            11 => 'Falgun',
            12 => 'Chaitra'
        ];
        return $months[$month] ?? '';
    }
}

/**
 * Helper function to convert BS to AD
 * Uses XML data if available, falls back to calculation
 *
 * @param string $bsDate BS date string (YYYY-MM-DD)
 * @return string|null AD date string (YYYY-MM-DD) or null on error
 */
function convertBsToAd($bsDate) {
    // Try XML data first if nepali_date_helper is loaded
    if (function_exists('convertBsToAdFromXml')) {
        $xmlResult = convertBsToAdFromXml($bsDate);
        if ($xmlResult) {
            return $xmlResult;
        }
    }

    // Fallback to calculation
    $parsed = NepaliDateConverter::parseBsDate($bsDate);
    if (!$parsed) {
        return null;
    }

    $result = NepaliDateConverter::bsToAd($parsed['year'], $parsed['month'], $parsed['day']);
    if (!$result) {
        return null;
    }

    return sprintf('%04d-%02d-%02d', $result['year'], $result['month'], $result['day']);
}

/**
 * Helper function to convert AD to BS
 * Uses XML data if available, falls back to calculation
 *
 * @param string $adDate AD date string (YYYY-MM-DD)
 * @return string|null BS date string (YYYY-MM-DD) or null on error
 */
function convertAdToBs($adDate) {
    // Try XML data first if nepali_date_helper is loaded
    if (function_exists('convertAdToBsFromXml')) {
        $xmlResult = convertAdToBsFromXml($adDate);
        if ($xmlResult) {
            return $xmlResult;
        }
    }

    // Fallback to calculation
    $parts = explode('-', $adDate);
    if (count($parts) !== 3) {
        return null;
    }

    $result = NepaliDateConverter::adToBs((int)$parts[0], (int)$parts[1], (int)$parts[2]);
    if (!$result) {
        return null;
    }

    return NepaliDateConverter::formatBsDate($result['year'], $result['month'], $result['day']);
}
