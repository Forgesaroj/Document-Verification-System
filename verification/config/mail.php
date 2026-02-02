<?php
/**
 * Mail Configuration
 * Uses PHP mail() function for shared hosting compatibility
 */

// Prevent direct access
if (!defined('VERIFICATION_PORTAL')) {
    die('Direct access not permitted');
}

// Mail Configuration
define('MAIL_FROM_EMAIL', 'noreply@example.com');
define('MAIL_FROM_NAME', 'Company Verification');
define('MAIL_REPLY_TO', 'info@example.com');

// OTP Configuration
define('OTP_LENGTH', 6);
define('OTP_EXPIRY_MINUTES', 10);

/**
 * Get SMTP settings from database
 */
function getSMTPSettings() {
    try {
        $db = getDB();
        $stmt = $db->query("SELECT setting_key, setting_value FROM ver_settings WHERE setting_group IN ('smtp', 'otp')");
        $settings = [];
        while ($row = $stmt->fetch()) {
            $settings[$row['setting_key']] = $row['setting_value'];
        }
        return $settings;
    } catch (Exception $e) {
        return [];
    }
}

/**
 * Send email via SMTP
 *
 * @param string $to Recipient email
 * @param string $subject Email subject
 * @param string $message HTML message
 * @return array ['success' => bool, 'error' => string]
 */
function sendEmailViaSMTP($to, $subject, $message) {
    $settings = getSMTPSettings();

    $host = trim($settings['smtp_host'] ?? '');
    $port = intval($settings['smtp_port'] ?? 587);
    $username = $settings['smtp_username'] ?? '';
    $password = $settings['smtp_password'] ?? '';
    $encryption = $settings['smtp_encryption'] ?? 'tls';
    $fromEmail = $settings['smtp_from_email'] ?? MAIL_FROM_EMAIL;
    $fromName = $settings['smtp_from_name'] ?? MAIL_FROM_NAME;

    // If no SMTP configured, fall back to PHP mail()
    if (empty($host)) {
        $headers = [
            'MIME-Version: 1.0',
            'Content-type: text/html; charset=UTF-8',
            'From: ' . $fromName . ' <' . $fromEmail . '>',
            'Reply-To: ' . $fromEmail,
        ];
        $result = @mail($to, $subject, $message, implode("\r\n", $headers));
        return ['success' => $result, 'error' => $result ? '' : 'PHP mail() failed'];
    }

    try {
        $errno = 0;
        $errstr = '';
        $timeout = 30;

        // Determine connection prefix
        $prefix = '';
        if ($encryption === 'ssl' || ($encryption === 'tls' && $port == 465)) {
            $prefix = 'ssl://';
        }

        // Connect
        $socket = @fsockopen($prefix . $host, $port, $errno, $errstr, $timeout);
        if (!$socket) {
            return ['success' => false, 'error' => "Connection failed: $errstr"];
        }

        stream_set_timeout($socket, $timeout);

        // Read greeting
        $response = fgets($socket, 512);
        if (substr($response, 0, 3) != '220') {
            fclose($socket);
            return ['success' => false, 'error' => "Server error: $response"];
        }

        // EHLO
        fwrite($socket, "EHLO " . gethostname() . "\r\n");
        $response = '';
        while ($line = fgets($socket, 512)) {
            $response .= $line;
            if (substr($line, 3, 1) == ' ') break;
        }

        // STARTTLS for TLS on port 587
        if ($encryption === 'tls' && $port != 465) {
            fwrite($socket, "STARTTLS\r\n");
            $response = fgets($socket, 512);
            if (substr($response, 0, 3) == '220') {
                stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
                fwrite($socket, "EHLO " . gethostname() . "\r\n");
                while ($line = fgets($socket, 512)) {
                    if (substr($line, 3, 1) == ' ') break;
                }
            }
        }

        // AUTH LOGIN
        if (!empty($username) && !empty($password)) {
            fwrite($socket, "AUTH LOGIN\r\n");
            $response = fgets($socket, 512);
            if (substr($response, 0, 3) == '334') {
                fwrite($socket, base64_encode($username) . "\r\n");
                $response = fgets($socket, 512);
                if (substr($response, 0, 3) == '334') {
                    fwrite($socket, base64_encode($password) . "\r\n");
                    $response = fgets($socket, 512);
                    if (substr($response, 0, 3) != '235') {
                        fclose($socket);
                        return ['success' => false, 'error' => 'Authentication failed'];
                    }
                }
            }
        }

        // MAIL FROM
        fwrite($socket, "MAIL FROM:<$fromEmail>\r\n");
        $response = fgets($socket, 512);
        if (substr($response, 0, 3) != '250') {
            fclose($socket);
            return ['success' => false, 'error' => "MAIL FROM rejected"];
        }

        // RCPT TO
        fwrite($socket, "RCPT TO:<$to>\r\n");
        $response = fgets($socket, 512);
        if (substr($response, 0, 3) != '250') {
            fclose($socket);
            return ['success' => false, 'error' => "RCPT TO rejected"];
        }

        // DATA
        fwrite($socket, "DATA\r\n");
        $response = fgets($socket, 512);
        if (substr($response, 0, 3) != '354') {
            fclose($socket);
            return ['success' => false, 'error' => "DATA command failed"];
        }

        // Send message
        $headers = "From: $fromName <$fromEmail>\r\n";
        $headers .= "To: $to\r\n";
        $headers .= "Subject: $subject\r\n";
        $headers .= "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
        $headers .= "Date: " . date('r') . "\r\n";

        fwrite($socket, $headers . "\r\n" . $message . "\r\n.\r\n");
        $response = fgets($socket, 512);

        fwrite($socket, "QUIT\r\n");
        fclose($socket);

        if (substr($response, 0, 3) == '250') {
            return ['success' => true, 'error' => ''];
        } else {
            return ['success' => false, 'error' => "Message rejected: $response"];
        }

    } catch (Exception $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

/**
 * Send OTP Email
 *
 * @param string $to Recipient email
 * @param string $otp OTP code
 * @param string $type Verification type (document/bill)
 * @return bool Success status
 */
function sendOTPEmail($to, $otp, $type = 'document') {
    $settings = getSMTPSettings();

    $subject = $settings['otp_email_subject'] ?? "Verification OTP - Company";
    $typeText = ($type === 'bill') ? 'Bill' : 'Document';
    $expiry = $settings['otp_expiry_minutes'] ?? OTP_EXPIRY_MINUTES;

    // Check if custom template exists
    $template = $settings['otp_email_template'] ?? '';

    if (!empty($template)) {
        // Use custom template
        $message = str_replace(
            ['{otp}', '{type}', '{expiry}'],
            [$otp, $typeText, $expiry],
            $template
        );
    } else {
        // Default template
        $message = "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: #1a3a5c; color: white; padding: 20px; text-align: center; }
                .content { padding: 30px; background: #f9fafb; }
                .otp-box { background: #1a3a5c; color: white; font-size: 32px; letter-spacing: 8px;
                           padding: 20px; text-align: center; margin: 20px 0; border-radius: 8px; }
                .footer { padding: 20px; text-align: center; font-size: 12px; color: #666; }
                .warning { color: #dc2626; font-size: 14px; margin-top: 20px; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>Company</h1>
                    <p>{$typeText} Verification Portal</p>
                </div>
                <div class='content'>
                    <h2>Your Verification OTP</h2>
                    <p>You have requested to verify a {$typeText}. Please use the following OTP code:</p>
                    <div class='otp-box'>{$otp}</div>
                    <p><strong>This OTP will expire in {$expiry} minutes.</strong></p>
                    <p class='warning'>
                        If you did not request this verification, please ignore this email.
                        Do not share this OTP with anyone.
                    </p>
                </div>
                <div class='footer'>
                    <p>&copy; " . date('Y') . " Company. All rights reserved.</p>
                    <p>This is an automated message. Please do not reply.</p>
                </div>
            </div>
        </body>
        </html>
        ";
    }

    // Send via SMTP
    $result = sendEmailViaSMTP($to, $subject, $message);

    // Log email attempt
    if (!$result['success']) {
        error_log("Failed to send OTP email to {$to}: " . $result['error']);
    }

    return $result['success'];
}

/**
 * Generate random OTP
 *
 * @param int $length OTP length
 * @return string Generated OTP
 */
function generateOTP($length = null) {
    $length = $length ?? OTP_LENGTH;
    $otp = '';
    for ($i = 0; $i < $length; $i++) {
        $otp .= random_int(0, 9);
    }
    return $otp;
}
