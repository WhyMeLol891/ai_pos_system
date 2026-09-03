<?php
/**
 * User Logout Handler
 * AI Camera POS System
 */

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/auth.php';

if (is_logged_in()) {
    log_audit('LOGOUT', "User {$_SESSION['user_username']} logged out");
}

// Clear all session data
$_SESSION = [];

// Destroy session cookie
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

session_destroy();

// Start fresh session for flash message
session_start();
set_flash('info', 'You have been successfully logged out.');

header('Location: ' . base_url('login.php'));
exit;
