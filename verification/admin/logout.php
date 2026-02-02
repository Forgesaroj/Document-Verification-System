<?php
/**
 * Admin Logout
 */

define('VERIFICATION_PORTAL', true);

require_once __DIR__ . '/../config/security.php';

// Log logout event
if (isset($_SESSION['admin_id'])) {
    require_once __DIR__ . '/../config/db.php';
    require_once __DIR__ . '/../includes/functions.php';

    logSecurityEvent('logout', ['admin_id' => $_SESSION['admin_id']]);
}

// Clear session
$_SESSION = [];

// Destroy session cookie
if (isset($_COOKIE[session_name()])) {
    setcookie(session_name(), '', time() - 3600, '/');
}

// Destroy session
session_destroy();

// Redirect to login
header('Location: login.php');
exit;
