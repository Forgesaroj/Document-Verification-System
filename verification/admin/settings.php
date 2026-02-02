<?php
/**
 * Admin Settings Page
 * Manage SMTP and OTP settings
 */

define('VERIFICATION_PORTAL', true);

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/security.php';
require_once __DIR__ . '/../includes/functions.php';

// Require login
requireAdminLogin();

$pageTitle = 'Settings';
$errors = [];
$success = '';

// Get current settings
function getSettings() {
    try {
        $db = getDB();
        $stmt = $db->query("SELECT setting_key, setting_value, setting_type, setting_group, description FROM ver_settings ORDER BY setting_group, id");
        $settings = [];
        while ($row = $stmt->fetch()) {
            $settings[$row['setting_key']] = $row;
        }
        return $settings;
    } catch (PDOException $e) {
        return [];
    }
}

// Save settings
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid request. Please try again.';
    } else {
        $action = $_POST['action'] ?? '';

        if ($action === 'save_smtp') {
            try {
                $db = getDB();
                $smtpFields = [
                    'smtp_host' => ['type' => 'text', 'group' => 'smtp', 'desc' => 'SMTP Server Host'],
                    'smtp_port' => ['type' => 'number', 'group' => 'smtp', 'desc' => 'SMTP Port'],
                    'smtp_username' => ['type' => 'text', 'group' => 'smtp', 'desc' => 'SMTP Username'],
                    'smtp_password' => ['type' => 'password', 'group' => 'smtp', 'desc' => 'SMTP Password'],
                    'smtp_encryption' => ['type' => 'text', 'group' => 'smtp', 'desc' => 'SMTP Encryption'],
                    'smtp_from_email' => ['type' => 'email', 'group' => 'smtp', 'desc' => 'From Email Address'],
                    'smtp_from_name' => ['type' => 'text', 'group' => 'smtp', 'desc' => 'From Name']
                ];

                foreach ($smtpFields as $field => $meta) {
                    $value = $_POST[$field] ?? '';
                    // Don't update password if empty (keep existing)
                    if ($field === 'smtp_password' && empty($value)) {
                        continue;
                    }
                    // Use INSERT ... ON DUPLICATE KEY UPDATE to handle both insert and update
                    $stmt = $db->prepare("
                        INSERT INTO ver_settings (setting_key, setting_value, setting_type, setting_group, description, updated_by, updated_at)
                        VALUES (?, ?, ?, ?, ?, ?, NOW())
                        ON DUPLICATE KEY UPDATE
                            setting_value = VALUES(setting_value),
                            updated_by = VALUES(updated_by),
                            updated_at = NOW()
                    ");
                    $stmt->execute([$field, $value, $meta['type'], $meta['group'], $meta['desc'], $_SESSION['admin_id']]);
                }

                logAdminActivity($_SESSION['admin_id'], 'update_smtp_settings', 'ver_settings', null, null, ['fields' => array_keys($smtpFields)]);
                $success = 'SMTP settings saved successfully!';
            } catch (PDOException $e) {
                error_log("Settings save error: " . $e->getMessage());
                $errors[] = 'Failed to save settings: ' . $e->getMessage();
            }
        }

        if ($action === 'save_otp') {
            try {
                $db = getDB();
                $otpFields = [
                    'otp_length' => ['type' => 'number', 'group' => 'otp', 'desc' => 'OTP Code Length'],
                    'otp_expiry_minutes' => ['type' => 'number', 'group' => 'otp', 'desc' => 'OTP Expiry (Minutes)'],
                    'otp_email_subject' => ['type' => 'text', 'group' => 'otp', 'desc' => 'OTP Email Subject'],
                    'otp_email_template' => ['type' => 'textarea', 'group' => 'otp', 'desc' => 'OTP Email Template (HTML)']
                ];

                foreach ($otpFields as $field => $meta) {
                    $value = $_POST[$field] ?? '';
                    // Use INSERT ... ON DUPLICATE KEY UPDATE to handle both insert and update
                    $stmt = $db->prepare("
                        INSERT INTO ver_settings (setting_key, setting_value, setting_type, setting_group, description, updated_by, updated_at)
                        VALUES (?, ?, ?, ?, ?, ?, NOW())
                        ON DUPLICATE KEY UPDATE
                            setting_value = VALUES(setting_value),
                            updated_by = VALUES(updated_by),
                            updated_at = NOW()
                    ");
                    $stmt->execute([$field, $value, $meta['type'], $meta['group'], $meta['desc'], $_SESSION['admin_id']]);
                }

                logAdminActivity($_SESSION['admin_id'], 'update_otp_settings', 'ver_settings', null, null, ['fields' => array_keys($otpFields)]);
                $success = 'OTP settings saved successfully!';
            } catch (PDOException $e) {
                error_log("Settings save error: " . $e->getMessage());
                $errors[] = 'Failed to save settings: ' . $e->getMessage();
            }
        }

        if ($action === 'test_smtp') {
            $testEmail = sanitize($_POST['test_email'] ?? '');
            if (empty($testEmail) || !isValidEmail($testEmail)) {
                $errors[] = 'Please enter a valid test email address.';
            } else {
                // Test SMTP
                $settings = getSettings();
                $testResult = testSMTPConnection($settings, $testEmail);
                if ($testResult['success']) {
                    $success = 'Test email sent successfully! Check your inbox.';
                } else {
                    $errors[] = 'SMTP test failed: ' . $testResult['error'];
                }
            }
        }

        if ($action === 'save_modules') {
            try {
                $db = getDB();
                $billsEnabled = isset($_POST['bills_module_enabled']) ? '1' : '0';

                $stmt = $db->prepare("
                    INSERT INTO ver_settings (setting_key, setting_value, setting_type, setting_group, description, updated_by, updated_at)
                    VALUES (?, ?, ?, ?, ?, ?, NOW())
                    ON DUPLICATE KEY UPDATE
                        setting_value = VALUES(setting_value),
                        updated_by = VALUES(updated_by),
                        updated_at = NOW()
                ");
                $stmt->execute(['bills_module_enabled', $billsEnabled, 'boolean', 'modules', 'Enable/Disable Bills Verification Module', $_SESSION['admin_id']]);

                logAdminActivity($_SESSION['admin_id'], 'update_module_settings', 'ver_settings', null, null, ['bills_module_enabled' => $billsEnabled]);
                $success = 'Module settings saved successfully!';
            } catch (PDOException $e) {
                error_log("Settings save error: " . $e->getMessage());
                $errors[] = 'Failed to save settings: ' . $e->getMessage();
            }
        }
    }
}

function testSMTPConnection($settings, $testEmail) {
    $host = trim($settings['smtp_host']['setting_value'] ?? '');
    $port = intval($settings['smtp_port']['setting_value'] ?? 587);
    $username = $settings['smtp_username']['setting_value'] ?? '';
    $password = $settings['smtp_password']['setting_value'] ?? '';
    $encryption = $settings['smtp_encryption']['setting_value'] ?? 'tls';
    $fromEmail = $settings['smtp_from_email']['setting_value'] ?? 'noreply@example.com';
    $fromName = $settings['smtp_from_name']['setting_value'] ?? 'Company';

    // Validate settings
    if (empty($host)) {
        return ['success' => false, 'error' => 'SMTP host is not configured. Please save your SMTP settings first.'];
    }

    if ($port <= 0 || $port > 65535) {
        return ['success' => false, 'error' => 'Invalid SMTP port: ' . $port];
    }

    if (empty($fromEmail)) {
        return ['success' => false, 'error' => 'From email is not configured.'];
    }

    // Try using PHPMailer if available
    $phpmailerPath = __DIR__ . '/../vendor/phpmailer/PHPMailer.php';
    if (file_exists($phpmailerPath)) {
        require_once $phpmailerPath;
    }

    // Use native SMTP via fsockopen
    try {
        $errno = 0;
        $errstr = '';
        $timeout = 30;

        // Determine connection prefix based on encryption
        $prefix = '';
        if ($encryption === 'ssl') {
            $prefix = 'ssl://';
        } elseif ($encryption === 'tls' && $port == 465) {
            $prefix = 'ssl://';
        }

        // Connect to SMTP server
        $connectTo = $prefix . $host;
        $socket = @fsockopen($connectTo, $port, $errno, $errstr, $timeout);

        if (!$socket) {
            $errorMsg = "Cannot connect to $connectTo:$port";
            if (!empty($errstr)) {
                $errorMsg .= " - $errstr";
            }
            if ($errno) {
                $errorMsg .= " (Error $errno)";
            }
            $errorMsg .= ". Check: 1) Host/port are correct, 2) Server is reachable, 3) Firewall allows outbound connection.";
            return ['success' => false, 'error' => $errorMsg];
        }

        // Set timeout for socket operations
        stream_set_timeout($socket, $timeout);

        // Read initial response
        $response = fgets($socket, 512);
        if (substr($response, 0, 3) != '220') {
            fclose($socket);
            return ['success' => false, 'error' => "SMTP Error: $response"];
        }

        // Send EHLO
        fwrite($socket, "EHLO " . gethostname() . "\r\n");
        $response = '';
        while ($line = fgets($socket, 512)) {
            $response .= $line;
            if (substr($line, 3, 1) == ' ') break;
        }

        // Start TLS if needed
        if ($encryption === 'tls' && $port != 465) {
            fwrite($socket, "STARTTLS\r\n");
            $response = fgets($socket, 512);
            if (substr($response, 0, 3) != '220') {
                fclose($socket);
                return ['success' => false, 'error' => "STARTTLS failed: $response"];
            }

            // Enable TLS on the socket
            $crypto = stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
            if (!$crypto) {
                fclose($socket);
                return ['success' => false, 'error' => "TLS encryption failed"];
            }

            // Re-send EHLO after STARTTLS
            fwrite($socket, "EHLO " . gethostname() . "\r\n");
            $response = '';
            while ($line = fgets($socket, 512)) {
                $response .= $line;
                if (substr($line, 3, 1) == ' ') break;
            }
        }

        // Authenticate if credentials provided
        if (!empty($username) && !empty($password)) {
            fwrite($socket, "AUTH LOGIN\r\n");
            $response = fgets($socket, 512);
            if (substr($response, 0, 3) != '334') {
                fclose($socket);
                return ['success' => false, 'error' => "AUTH LOGIN failed: $response"];
            }

            fwrite($socket, base64_encode($username) . "\r\n");
            $response = fgets($socket, 512);
            if (substr($response, 0, 3) != '334') {
                fclose($socket);
                return ['success' => false, 'error' => "Username rejected: $response"];
            }

            fwrite($socket, base64_encode($password) . "\r\n");
            $response = fgets($socket, 512);
            if (substr($response, 0, 3) != '235') {
                fclose($socket);
                return ['success' => false, 'error' => "Authentication failed: $response"];
            }
        }

        // MAIL FROM
        fwrite($socket, "MAIL FROM:<$fromEmail>\r\n");
        $response = fgets($socket, 512);
        if (substr($response, 0, 3) != '250') {
            fclose($socket);
            return ['success' => false, 'error' => "MAIL FROM rejected: $response"];
        }

        // RCPT TO
        fwrite($socket, "RCPT TO:<$testEmail>\r\n");
        $response = fgets($socket, 512);
        if (substr($response, 0, 3) != '250') {
            fclose($socket);
            return ['success' => false, 'error' => "RCPT TO rejected: $response"];
        }

        // DATA
        fwrite($socket, "DATA\r\n");
        $response = fgets($socket, 512);
        if (substr($response, 0, 3) != '354') {
            fclose($socket);
            return ['success' => false, 'error' => "DATA command failed: $response"];
        }

        // Compose email
        $subject = 'SMTP Test - Company Verification';
        $message = '<html><body>';
        $message .= '<div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px;">';
        $message .= '<h2 style="color: #1a3a5c;">SMTP Test Successful!</h2>';
        $message .= '<p>Your SMTP settings are working correctly.</p>';
        $message .= '<p><strong>Server:</strong> ' . htmlspecialchars($host) . ':' . $port . '</p>';
        $message .= '<p><strong>Encryption:</strong> ' . strtoupper($encryption) . '</p>';
        $message .= '<p><strong>Time:</strong> ' . date('Y-m-d H:i:s') . '</p>';
        $message .= '<hr style="border: none; border-top: 1px solid #eee; margin: 20px 0;">';
        $message .= '<p style="color: #666; font-size: 12px;">Company Verification Portal</p>';
        $message .= '</div></body></html>';

        $headers = "From: $fromName <$fromEmail>\r\n";
        $headers .= "To: $testEmail\r\n";
        $headers .= "Subject: $subject\r\n";
        $headers .= "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
        $headers .= "Date: " . date('r') . "\r\n";

        $email = $headers . "\r\n" . $message . "\r\n.\r\n";
        fwrite($socket, $email);

        $response = fgets($socket, 512);
        if (substr($response, 0, 3) != '250') {
            fclose($socket);
            return ['success' => false, 'error' => "Message rejected: $response"];
        }

        // QUIT
        fwrite($socket, "QUIT\r\n");
        fclose($socket);

        return ['success' => true];

    } catch (Exception $e) {
        return ['success' => false, 'error' => 'Exception: ' . $e->getMessage()];
    }
}

$settings = getSettings();

include __DIR__ . '/../includes/admin_header.php';
?>

<div class="max-w-4xl mx-auto">
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

    <!-- Tabs -->
    <div class="bg-white rounded-lg shadow mb-6">
        <div class="border-b">
            <nav class="flex -mb-px">
                <button onclick="showTab('modules')" id="tab-modules"
                        class="tab-btn px-6 py-4 border-b-2 border-blue-500 text-blue-600 font-medium">
                    <i class="fas fa-cubes mr-2"></i>Modules
                </button>
                <button onclick="showTab('smtp')" id="tab-smtp"
                        class="tab-btn px-6 py-4 border-b-2 border-transparent text-gray-500 hover:text-gray-700">
                    <i class="fas fa-envelope mr-2"></i>SMTP Settings
                </button>
                <button onclick="showTab('otp')" id="tab-otp"
                        class="tab-btn px-6 py-4 border-b-2 border-transparent text-gray-500 hover:text-gray-700">
                    <i class="fas fa-key mr-2"></i>OTP Settings
                </button>
            </nav>
        </div>

        <!-- Modules Tab -->
        <div id="content-modules" class="tab-content p-6">
            <h3 class="text-lg font-semibold text-gray-800 mb-4">Module Settings</h3>
            <p class="text-gray-600 mb-6">Enable or disable verification modules on the portal.</p>

            <form method="POST" action="" class="space-y-6">
                <?php echo csrfField(); ?>
                <input type="hidden" name="action" value="save_modules">

                <!-- Bills Module -->
                <div class="bg-gray-50 rounded-lg p-6">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center space-x-4">
                            <div class="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center">
                                <i class="fas fa-receipt text-green-600 text-xl"></i>
                            </div>
                            <div>
                                <h4 class="text-lg font-medium text-gray-800">Bills Verification Module</h4>
                                <p class="text-sm text-gray-500">Allow users to verify bill authenticity on the portal</p>
                            </div>
                        </div>
                        <label class="relative inline-flex items-center cursor-pointer">
                            <input type="checkbox" name="bills_module_enabled" value="1" class="sr-only peer"
                                   <?php echo ($settings['bills_module_enabled']['setting_value'] ?? '1') === '1' ? 'checked' : ''; ?>>
                            <div class="w-14 h-7 bg-gray-300 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-green-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-0.5 after:left-[4px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-6 after:w-6 after:transition-all peer-checked:bg-green-600"></div>
                        </label>
                    </div>
                    <div class="mt-4 pl-16">
                        <p class="text-xs text-gray-400">
                            <i class="fas fa-info-circle mr-1"></i>
                            When disabled, the Bills verification option will be hidden from the public portal.
                        </p>
                    </div>
                </div>

                <!-- Documents Module (Always Enabled) -->
                <div class="bg-gray-50 rounded-lg p-6 opacity-75">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center space-x-4">
                            <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center">
                                <i class="fas fa-file-alt text-blue-600 text-xl"></i>
                            </div>
                            <div>
                                <h4 class="text-lg font-medium text-gray-800">Documents Verification Module</h4>
                                <p class="text-sm text-gray-500">Allow users to verify document authenticity on the portal</p>
                            </div>
                        </div>
                        <div class="px-3 py-1 bg-blue-100 text-blue-700 rounded-full text-sm font-medium">
                            <i class="fas fa-lock mr-1"></i> Always On
                        </div>
                    </div>
                    <div class="mt-4 pl-16">
                        <p class="text-xs text-gray-400">
                            <i class="fas fa-info-circle mr-1"></i>
                            The Documents verification module is the core feature and cannot be disabled.
                        </p>
                    </div>
                </div>

                <div class="flex items-center justify-between pt-4 border-t">
                    <button type="submit"
                            class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 focus:ring-4 focus:ring-blue-300">
                        <i class="fas fa-save mr-2"></i>Save Module Settings
                    </button>
                </div>
            </form>
        </div>

        <!-- SMTP Settings Tab -->
        <div id="content-smtp" class="tab-content p-6 hidden">
            <h3 class="text-lg font-semibold text-gray-800 mb-4">SMTP Configuration</h3>
            <p class="text-gray-600 mb-6">Configure email server settings for sending OTP emails.</p>

            <form method="POST" action="" class="space-y-6">
                <?php echo csrfField(); ?>
                <input type="hidden" name="action" value="save_smtp">

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">SMTP Host</label>
                        <input type="text" name="smtp_host"
                               value="<?php echo htmlspecialchars($settings['smtp_host']['setting_value'] ?? ''); ?>"
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                               placeholder="smtp.example.com">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">SMTP Port</label>
                        <input type="number" name="smtp_port"
                               value="<?php echo htmlspecialchars($settings['smtp_port']['setting_value'] ?? '587'); ?>"
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                               placeholder="587">
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">SMTP Username</label>
                        <input type="text" name="smtp_username"
                               value="<?php echo htmlspecialchars($settings['smtp_username']['setting_value'] ?? ''); ?>"
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                               placeholder="your@email.com">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">SMTP Password</label>
                        <input type="password" name="smtp_password"
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                               placeholder="Leave empty to keep current">
                        <p class="text-xs text-gray-500 mt-1">Leave empty to keep existing password</p>
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Encryption</label>
                    <select name="smtp_encryption"
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                        <option value="tls" <?php echo ($settings['smtp_encryption']['setting_value'] ?? '') === 'tls' ? 'selected' : ''; ?>>TLS</option>
                        <option value="ssl" <?php echo ($settings['smtp_encryption']['setting_value'] ?? '') === 'ssl' ? 'selected' : ''; ?>>SSL</option>
                        <option value="none" <?php echo ($settings['smtp_encryption']['setting_value'] ?? '') === 'none' ? 'selected' : ''; ?>>None</option>
                    </select>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">From Email</label>
                        <input type="email" name="smtp_from_email"
                               value="<?php echo htmlspecialchars($settings['smtp_from_email']['setting_value'] ?? ''); ?>"
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                               placeholder="noreply@yourcompany.com">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">From Name</label>
                        <input type="text" name="smtp_from_name"
                               value="<?php echo htmlspecialchars($settings['smtp_from_name']['setting_value'] ?? ''); ?>"
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                               placeholder="Company">
                    </div>
                </div>

                <div class="flex items-center justify-between pt-4 border-t">
                    <button type="submit"
                            class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 focus:ring-4 focus:ring-blue-300">
                        <i class="fas fa-save mr-2"></i>Save SMTP Settings
                    </button>
                </div>
            </form>

            <!-- Test SMTP -->
            <div class="mt-8 pt-6 border-t">
                <h4 class="text-md font-semibold text-gray-800 mb-4">Test SMTP Connection</h4>
                <form method="POST" action="" class="flex items-end gap-4">
                    <?php echo csrfField(); ?>
                    <input type="hidden" name="action" value="test_smtp">
                    <div class="flex-1">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Test Email Address</label>
                        <input type="email" name="test_email"
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                               placeholder="your@email.com" required>
                    </div>
                    <button type="submit"
                            class="px-6 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 focus:ring-4 focus:ring-green-300">
                        <i class="fas fa-paper-plane mr-2"></i>Send Test
                    </button>
                </form>
            </div>
        </div>

        <!-- OTP Settings Tab -->
        <div id="content-otp" class="tab-content p-6 hidden">
            <h3 class="text-lg font-semibold text-gray-800 mb-4">OTP Configuration</h3>
            <p class="text-gray-600 mb-6">Configure OTP generation and email template settings.</p>

            <form method="POST" action="" class="space-y-6">
                <?php echo csrfField(); ?>
                <input type="hidden" name="action" value="save_otp">

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">OTP Length</label>
                        <input type="number" name="otp_length" min="4" max="8"
                               value="<?php echo htmlspecialchars($settings['otp_length']['setting_value'] ?? '6'); ?>"
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Expiry (Minutes)</label>
                        <input type="number" name="otp_expiry_minutes" min="1" max="60"
                               value="<?php echo htmlspecialchars($settings['otp_expiry_minutes']['setting_value'] ?? '10'); ?>"
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Email Subject</label>
                    <input type="text" name="otp_email_subject"
                           value="<?php echo htmlspecialchars($settings['otp_email_subject']['setting_value'] ?? ''); ?>"
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                           placeholder="Your Verification OTP">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Email Template (HTML)</label>
                    <textarea name="otp_email_template" rows="12"
                              class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 font-mono text-sm"><?php echo htmlspecialchars($settings['otp_email_template']['setting_value'] ?? ''); ?></textarea>
                    <p class="text-xs text-gray-500 mt-1">
                        Available placeholders: <code>{otp}</code>, <code>{type}</code>, <code>{expiry}</code>
                    </p>
                </div>

                <!-- Preview -->
                <div class="bg-gray-50 rounded-lg p-4">
                    <h4 class="text-sm font-medium text-gray-700 mb-2">Preview</h4>
                    <div id="template-preview" class="border bg-white p-4 rounded">
                        <!-- Preview will be shown here -->
                    </div>
                </div>

                <div class="flex items-center justify-between pt-4 border-t">
                    <button type="submit"
                            class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 focus:ring-4 focus:ring-blue-300">
                        <i class="fas fa-save mr-2"></i>Save OTP Settings
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function showTab(tab) {
    // Hide all content
    document.querySelectorAll('.tab-content').forEach(el => el.classList.add('hidden'));
    // Reset all tabs
    document.querySelectorAll('.tab-btn').forEach(el => {
        el.classList.remove('border-blue-500', 'text-blue-600');
        el.classList.add('border-transparent', 'text-gray-500');
    });
    // Show selected content
    document.getElementById('content-' + tab).classList.remove('hidden');
    // Highlight selected tab
    const activeTab = document.getElementById('tab-' + tab);
    activeTab.classList.remove('border-transparent', 'text-gray-500');
    activeTab.classList.add('border-blue-500', 'text-blue-600');
}

// Template preview
const templateTextarea = document.querySelector('textarea[name="otp_email_template"]');
const previewDiv = document.getElementById('template-preview');

function updatePreview() {
    if (templateTextarea && previewDiv) {
        let html = templateTextarea.value;
        html = html.replace(/{otp}/g, '123456');
        html = html.replace(/{type}/g, 'document');
        html = html.replace(/{expiry}/g, '10');
        previewDiv.innerHTML = html;
    }
}

if (templateTextarea) {
    templateTextarea.addEventListener('input', updatePreview);
    updatePreview();
}
</script>

<?php include __DIR__ . '/../includes/admin_footer.php'; ?>
