<?php
/**
 * Authentication & Access Control Helpers
 * AI Camera POS System
 */

require_once __DIR__ . '/config.php';

/**
 * Check if a user is logged in
 */
function is_logged_in(): bool {
    return !empty($_SESSION['user_id']) && !empty($_SESSION['user_role']);
}

/**
 * Get current logged in user array
 */
function current_user(): ?array {
    if (!is_logged_in()) {
        return null;
    }
    return [
        'id'        => $_SESSION['user_id'],
        'username'  => $_SESSION['user_username'] ?? '',
        'full_name' => $_SESSION['user_fullname'] ?? '',
        'role'      => $_SESSION['user_role'] ?? '',
    ];
}

/**
 * Check if logged in user is an Admin
 */
function is_admin(): bool {
    return is_logged_in() && ($_SESSION['user_role'] === 'admin');
}

/**
 * Check if logged in user is a Cashier
 */
function is_cashier(): bool {
    return is_logged_in() && ($_SESSION['user_role'] === 'cashier');
}

/**
 * Require login, redirect to login page if not logged in
 */
function require_login(): void {
    if (!is_logged_in()) {
        set_flash('warning', 'Please login to access this page.');
        header('Location: ' . base_url('login.php'));
        exit;
    }
}

/**
 * Require Admin role, redirect with warning if unauthorized
 */
function require_admin(): void {
    require_login();
    if (!is_admin()) {
        set_flash('danger', 'Access denied. Administrator privileges required.');
        header('Location: ' . base_url('pos/index.php'));
        exit;
    }
}

/**
 * Log audit events
 */
function log_audit(string $action, string $details = ''): void {
    try {
        $pdo = get_db_connection();
        $userId = $_SESSION['user_id'] ?? null;
        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';

        $stmt = $pdo->prepare("
            INSERT INTO audit_logs (user_id, action, details, ip_address)
            VALUES (:user_id, :action, :details, :ip)
        ");
        $stmt->execute([
            'user_id' => $userId,
            'action'  => $action,
            'details' => $details,
            'ip'      => $ip
        ]);
    } catch (Exception $e) {
        // Silently continue if audit logging fails
    }
}
