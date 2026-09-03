<?php
/**
 * Database Initializer & Migration Script
 * AI Camera POS System
 * 
 * Can be executed via CLI (php init_db.php) or web browser.
 */

require_once __DIR__ . '/../config/database.php';

$isCli = (php_sapi_name() === 'cli');

function output_msg($msg, $type = 'info') {
    global $isCli;
    if ($isCli) {
        $prefix = match($type) {
            'success' => '[OK] ',
            'error'   => '[ERROR] ',
            'warn'    => '[WARN] ',
            default   => '[INFO] '
        };
        echo $prefix . $msg . PHP_EOL;
    } else {
        $color = match($type) {
            'success' => '#155724; background-color: #d4edda; border-color: #c3e6cb;',
            'error'   => '#721c24; background-color: #f8d7da; border-color: #f5c6cb;',
            'warn'    => '#856404; background-color: #fff3cd; border-color: #ffeeba;',
            default   => '#0c5460; background-color: #d1ecf1; border-color: #bee5eb;'
        };
        echo "<div style='padding: 12px; margin: 8px 0; border: 1px solid; border-radius: 6px; font-family: sans-serif; color: {$color}'>{$msg}</div>";
    }
}

if (!$isCli) {
    echo "<!DOCTYPE html><html><head><title>Database Setup - AI POS System</title></head><body style='max-width: 800px; margin: 40px auto; padding: 20px;'>";
    echo "<h2>AI Camera POS System - Database Initialization</h2>";
}

try {
    output_msg("Attempting to connect to MySQL database server...", 'info');
    $pdo = get_db_connection();
    output_msg("Connected successfully to MySQL server!", 'success');

    $schemaFile = __DIR__ . '/schema.sql';
    if (!file_exists($schemaFile)) {
        throw new Exception("schema.sql not found at " . $schemaFile);
    }

    output_msg("Reading schema.sql...", 'info');
    $sql = file_get_contents($schemaFile);

    // Remove comments and split by semicolon (handling statements safely)
    // Multi-statement execution via PDO
    output_msg("Executing schema migrations and seeding initial data...", 'info');
    $pdo->exec($sql);
    output_msg("Database tables and seed data created successfully!", 'success');

    // Verify tables
    $stmt = $pdo->query("SHOW TABLES;");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    output_msg("Found tables: " . implode(', ', $tables), 'info');

    // Count products
    $prodCount = $pdo->query("SELECT COUNT(*) FROM products")->fetchColumn();
    output_msg("Initialized {$prodCount} sample products in the catalog.", 'success');

    // Count users
    $userCount = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
    output_msg("Created {$userCount} users (admin / cashier).", 'success');

    if (!$isCli) {
        echo "<hr><p>Setup complete! <a href='../login.php' style='display: inline-block; padding: 10px 20px; background: #0d6efd; color: #fff; text-decoration: none; border-radius: 6px;'>Go to Login</a></p>";
        echo "</body></html>";
    }
} catch (Exception $e) {
    output_msg("Initialization error: " . $e->getMessage(), 'error');
    if (!$isCli) {
        echo "</body></html>";
    }
    exit(1);
}
