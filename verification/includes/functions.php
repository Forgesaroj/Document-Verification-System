<?php
/**
 * Helper Functions
 * Common utility functions for the verification portal
 */

// Prevent direct access
if (!defined('VERIFICATION_PORTAL')) {
    die('Direct access not permitted');
}

/**
 * Create flash message
 *
 * @param string $type Message type (success, error, warning, info)
 * @param string $message Message content
 */
function setFlashMessage($type, $message) {
    $_SESSION['flash'] = [
        'type' => $type,
        'message' => $message
    ];
}

/**
 * Get and clear flash message
 *
 * @return array|null Flash message data
 */
function getFlashMessage() {
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

/**
 * Display flash message HTML
 *
 * @return string HTML for flash message
 */
function displayFlashMessage() {
    $flash = getFlashMessage();
    if (!$flash) {
        return '';
    }

    $typeClasses = [
        'success' => 'bg-green-100 border-green-400 text-green-700',
        'error' => 'bg-red-100 border-red-400 text-red-700',
        'warning' => 'bg-yellow-100 border-yellow-400 text-yellow-700',
        'info' => 'bg-blue-100 border-blue-400 text-blue-700'
    ];

    $class = $typeClasses[$flash['type']] ?? $typeClasses['info'];

    return '<div class="' . $class . ' px-4 py-3 rounded border mb-4" role="alert">
        <span class="block sm:inline">' . htmlspecialchars($flash['message']) . '</span>
    </div>';
}

/**
 * Format date for display
 *
 * @param string $date Date string
 * @param string $format Output format
 * @return string Formatted date
 */
function formatDate($date, $format = 'Y-m-d') {
    if (empty($date)) {
        return '-';
    }
    try {
        $dt = new DateTime($date);
        return $dt->format($format);
    } catch (Exception $e) {
        return $date;
    }
}

/**
 * Format file size for display
 *
 * @param int $bytes Size in bytes
 * @return string Formatted size
 */
function formatFileSize($bytes) {
    $units = ['B', 'KB', 'MB', 'GB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= pow(1024, $pow);
    return round($bytes, 2) . ' ' . $units[$pow];
}

/**
 * Truncate text with ellipsis
 *
 * @param string $text Text to truncate
 * @param int $length Maximum length
 * @return string Truncated text
 */
function truncateText($text, $length = 50) {
    if (strlen($text) <= $length) {
        return $text;
    }
    return substr($text, 0, $length - 3) . '...';
}

/**
 * Generate pagination HTML
 *
 * @param int $currentPage Current page number
 * @param int $totalPages Total number of pages
 * @param string $baseUrl Base URL for pagination links
 * @return string Pagination HTML
 */
function generatePagination($currentPage, $totalPages, $baseUrl) {
    if ($totalPages <= 1) {
        return '';
    }

    $html = '<nav class="flex justify-center mt-6"><ul class="flex space-x-2">';

    // Previous button
    if ($currentPage > 1) {
        $html .= '<li><a href="' . $baseUrl . '&page=' . ($currentPage - 1) . '" class="px-3 py-2 bg-gray-200 rounded hover:bg-gray-300">&laquo; Prev</a></li>';
    }

    // Page numbers
    $start = max(1, $currentPage - 2);
    $end = min($totalPages, $currentPage + 2);

    if ($start > 1) {
        $html .= '<li><a href="' . $baseUrl . '&page=1" class="px-3 py-2 bg-gray-200 rounded hover:bg-gray-300">1</a></li>';
        if ($start > 2) {
            $html .= '<li><span class="px-3 py-2">...</span></li>';
        }
    }

    for ($i = $start; $i <= $end; $i++) {
        $activeClass = ($i === $currentPage) ? 'bg-blue-600 text-white' : 'bg-gray-200 hover:bg-gray-300';
        $html .= '<li><a href="' . $baseUrl . '&page=' . $i . '" class="px-3 py-2 rounded ' . $activeClass . '">' . $i . '</a></li>';
    }

    if ($end < $totalPages) {
        if ($end < $totalPages - 1) {
            $html .= '<li><span class="px-3 py-2">...</span></li>';
        }
        $html .= '<li><a href="' . $baseUrl . '&page=' . $totalPages . '" class="px-3 py-2 bg-gray-200 rounded hover:bg-gray-300">' . $totalPages . '</a></li>';
    }

    // Next button
    if ($currentPage < $totalPages) {
        $html .= '<li><a href="' . $baseUrl . '&page=' . ($currentPage + 1) . '" class="px-3 py-2 bg-gray-200 rounded hover:bg-gray-300">Next &raquo;</a></li>';
    }

    $html .= '</ul></nav>';
    return $html;
}

/**
 * Get status badge HTML
 *
 * @param string $status Status value
 * @return string HTML badge
 */
function getStatusBadge($status) {
    $badges = [
        'active' => '<span class="px-2 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-800">Active</span>',
        'inactive' => '<span class="px-2 py-1 text-xs font-semibold rounded-full bg-yellow-100 text-yellow-800">Inactive</span>',
        'deleted' => '<span class="px-2 py-1 text-xs font-semibold rounded-full bg-red-100 text-red-800">Deleted</span>'
    ];
    return $badges[$status] ?? '<span class="px-2 py-1 text-xs font-semibold rounded-full bg-gray-100 text-gray-800">' . ucfirst($status) . '</span>';
}

/**
 * Log admin activity
 *
 * @param int $adminId Admin user ID
 * @param string $action Action performed
 * @param string|null $tableName Table affected
 * @param int|null $recordId Record ID affected
 * @param array|null $oldValues Previous values
 * @param array|null $newValues New values
 */
function logAdminActivity($adminId, $action, $tableName = null, $recordId = null, $oldValues = null, $newValues = null) {
    try {
        $db = getDB();
        $stmt = $db->prepare("
            INSERT INTO ver_admin_logs (admin_id, action, table_name, record_id, old_values, new_values, ip_address, user_agent)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $adminId,
            $action,
            $tableName,
            $recordId,
            $oldValues ? json_encode($oldValues) : null,
            $newValues ? json_encode($newValues) : null,
            getClientIP(),
            $_SERVER['HTTP_USER_AGENT'] ?? null
        ]);
    } catch (Exception $e) {
        error_log("Failed to log admin activity: " . $e->getMessage());
    }
}

/**
 * Log verification activity
 *
 * @param string $email User email
 * @param string $type Verification type (document/bill)
 * @param string $referenceNumber Document/Bill number
 * @param int|null $otpRequestId Related OTP request
 */
function logVerificationActivity($email, $type, $referenceNumber, $otpRequestId = null) {
    try {
        $db = getDB();
        $stmt = $db->prepare("
            INSERT INTO ver_verification_logs (email, verification_type, reference_number, ip_address, user_agent, otp_request_id)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $email,
            $type,
            $referenceNumber,
            getClientIP(),
            $_SERVER['HTTP_USER_AGENT'] ?? null,
            $otpRequestId
        ]);
    } catch (Exception $e) {
        error_log("Failed to log verification activity: " . $e->getMessage());
    }
}

/**
 * Get admin name by ID
 *
 * @param int $adminId Admin ID
 * @return string Admin name or 'Unknown'
 */
function getAdminName($adminId) {
    try {
        $db = getDB();
        $stmt = $db->prepare("SELECT name FROM admins WHERE id = ?");
        $stmt->execute([$adminId]);
        $result = $stmt->fetch();
        return $result ? $result['name'] : 'Unknown';
    } catch (Exception $e) {
        return 'Unknown';
    }
}

/**
 * Validate document number format
 *
 * @param string $docNumber Document number
 * @return bool
 */
function isValidDocumentNumber($docNumber) {
    // Allow alphanumeric, hyphens, underscores, forward slashes
    return preg_match('/^[A-Za-z0-9\-_\/]+$/', $docNumber);
}

/**
 * Validate bill number format
 *
 * @param string $billNumber Bill number
 * @return bool
 */
function isValidBillNumber($billNumber) {
    // Allow alphanumeric, hyphens, underscores, forward slashes
    return preg_match('/^[A-Za-z0-9\-_\/]+$/', $billNumber);
}

/**
 * Validate PAN number format (Nepal)
 *
 * @param string $pan PAN number
 * @return bool
 */
function isValidPAN($pan) {
    // Nepal PAN is typically 9 digits
    return preg_match('/^\d{9}$/', $pan);
}

/**
 * Get base URL for the application
 *
 * @return string Base URL
 */
function getBaseUrl() {
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'];
    $path = dirname($_SERVER['SCRIPT_NAME']);
    return $protocol . '://' . $host . rtrim($path, '/');
}

/**
 * Upload file securely
 *
 * @param array $file $_FILES array element
 * @param string $destination Destination directory
 * @return array ['success' => bool, 'path' => string|null, 'error' => string|null]
 */
function uploadFile($file, $destination) {
    // Validate file
    $validation = validateUploadedFile($file);
    if (!$validation['valid']) {
        return ['success' => false, 'path' => null, 'error' => $validation['error']];
    }

    // Generate secure filename
    $filename = generateSecureFilename($file['name']);
    $fullPath = rtrim($destination, '/') . '/' . $filename;

    // Move file
    if (!move_uploaded_file($file['tmp_name'], $fullPath)) {
        return ['success' => false, 'path' => null, 'error' => 'Failed to move uploaded file'];
    }

    return ['success' => true, 'path' => $filename, 'error' => null];
}

/**
 * Delete file safely
 *
 * @param string $filePath Path to file
 * @return bool Success status
 */
function deleteFile($filePath) {
    if (empty($filePath) || !file_exists($filePath)) {
        return true;
    }
    return @unlink($filePath);
}

/**
 * Check if current request is AJAX
 *
 * @return bool
 */
function isAjaxRequest() {
    return !empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
           strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
}

/**
 * Send JSON response
 *
 * @param array $data Response data
 * @param int $statusCode HTTP status code
 */
function jsonResponse($data, $statusCode = 200) {
    http_response_code($statusCode);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

/**
 * Get a setting value from the database
 *
 * @param string $key Setting key
 * @param mixed $default Default value if setting not found
 * @return mixed Setting value or default
 */
function getSettingValue($key, $default = null) {
    static $cache = [];

    if (isset($cache[$key])) {
        return $cache[$key];
    }

    try {
        $db = getDB();
        $stmt = $db->prepare("SELECT setting_value FROM ver_settings WHERE setting_key = ?");
        $stmt->execute([$key]);
        $result = $stmt->fetch();

        if ($result) {
            $cache[$key] = $result['setting_value'];
            return $result['setting_value'];
        }
    } catch (Exception $e) {
        error_log("Failed to get setting: " . $e->getMessage());
    }

    return $default;
}

/**
 * Check if Bills module is enabled
 *
 * @return bool
 */
function isBillsModuleEnabled() {
    return getSettingValue('bills_module_enabled', '1') === '1';
}

/**
 * Get page content for public pages
 *
 * @param bool $refresh Force refresh from database
 * @return array Content values
 */
function getPageContent($refresh = false) {
    static $content = null;

    if ($content !== null && !$refresh) {
        return $content;
    }

    // Default content
    $content = [
        'hero_title' => 'Document & Bill Verification',
        'hero_subtitle' => 'Verify the authenticity of documents and bills issued by Company through our secure online verification system.',
        'hero_tagline' => 'MERGING 33 YEARS OF EXPERTISE WITH MODERN PRECISION',
        'company_name' => 'COMPANY',
        'company_name_suffix' => 'APPAREL',
        'company_tagline' => 'Merging 33 years of expertise',
        'contact_email' => 'admin@example.com',
        'contact_phone1' => '+977-9863618347',
        'contact_phone2' => '+977-9865005120',
        'contact_address' => 'Gaidakot-6, Nawalparasi(east)',
        'whatsapp_number' => '9779865005120',
        'facebook_url' => 'https://www.facebook.com/yourpage',
        'website_url' => 'https://www.example.com',
        'pan_number' => '124441767',
        'footer_text' => 'Document & Bill Verification Portal for authentic document verification.'
    ];

    try {
        $db = getDB();
        $stmt = $db->query("SELECT setting_key, setting_value FROM ver_settings WHERE setting_group = 'page_content'");
        while ($row = $stmt->fetch()) {
            $key = str_replace('content_', '', $row['setting_key']);
            if (isset($content[$key])) {
                $content[$key] = $row['setting_value'];
            }
        }
    } catch (Exception $e) {
        // Use defaults if database unavailable
    }

    return $content;
}
