<?php
/**
 * Application Configuration & Helper Functions
 * AI Camera POS System
 */

// Start session securely if not already started
if (session_status() === PHP_SESSION_NONE) {
    // Session cookie settings
    $cookieParams = session_get_cookie_params();
    session_set_cookie_params([
        'lifetime' => 86400, // 24 hours
        'path'     => '/',
        'domain'   => $cookieParams['domain'] ?? '',
        'secure'   => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    session_start();
}

require_once __DIR__ . '/database.php';

// Timezone
date_default_timezone_set('Asia/Kuala_Lumpur');

/**
 * Get Base URL of the Application
 */
function base_url(string $path = ''): string {
    static $baseUrl = null;
    if ($baseUrl === null) {
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || ($_SERVER['SERVER_PORT'] ?? 80) == 443) ? "https://" : "http://";
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        
        // Compute script directory relative to document root
        $docRoot = str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT'] ?? '');
        $appRoot = str_replace('\\', '/', realpath(__DIR__ . '/..'));
        
        $subDir = '';
        if ($docRoot && str_starts_with($appRoot, $docRoot)) {
            $subDir = substr($appRoot, strlen($docRoot));
        } else {
            $subDir = '/ai_pos_system';
        }
        $subDir = rtrim($subDir, '/');
        $baseUrl = $protocol . $host . $subDir;
    }
    return $baseUrl . '/' . ltrim($path, '/');
}

/**
 * CSRF Protection Helpers
 */
function csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string {
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrf_token()) . '">';
}

function verify_csrf_token(?string $token): bool {
    if (empty($token) || empty($_SESSION['csrf_token'])) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Settings Helpers (cached during request)
 */
function get_setting(string $key, $default = null): mixed {
    static $settingsCache = null;

    if ($settingsCache === null) {
        $settingsCache = [];
        try {
            $pdo = get_db_connection();
            $stmt = $pdo->query("SELECT setting_key, setting_value FROM settings");
            while ($row = $stmt->fetch()) {
                $settingsCache[$row['setting_key']] = $row['setting_value'];
            }
        } catch (Exception $e) {
            // Silently fall back to defaults during early install
        }
    }

    return $settingsCache[$key] ?? $default;
}

function update_setting(string $key, string $value): bool {
    try {
        $pdo = get_db_connection();
        $stmt = $pdo->prepare("
            INSERT INTO settings (setting_key, setting_value) 
            VALUES (:k, :v)
            ON DUPLICATE KEY UPDATE setting_value = :v2
        ");
        return $stmt->execute(['k' => $key, 'v' => $value, 'v2' => $value]);
    } catch (Exception $e) {
        return false;
    }
}

/**
 * Currency Formatter
 */
function format_currency(float|int|string $amount): string {
    $symbol = get_setting('currency_symbol', 'RM');
    return $symbol . number_format((float)$amount, 2);
}

/**
 * Date Formatter
 */
function format_date(string $datetime, string $format = 'd M Y, h:i A'): string {
    if (empty($datetime)) return '-';
    $ts = strtotime($datetime);
    return date($format, $ts);
}

/**
 * Sanitize Output
 */
function clean(?string $value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

/**
 * Flash Message Helpers
 */
function set_flash(string $type, string $message): void {
    $_SESSION['flash'] = [
        'type'    => $type, // 'success', 'danger', 'warning', 'info'
        'message' => $message
    ];
}

function get_flash(): ?array {
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}
