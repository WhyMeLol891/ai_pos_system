<?php
/**
 * Root Router
 * AI Camera POS System
 */

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/auth.php';

if (!is_logged_in()) {
    header('Location: ' . base_url('login.php'));
    exit;
}

if (is_admin()) {
    header('Location: ' . base_url('admin/index.php'));
} else {
    header('Location: ' . base_url('pos/index.php'));
}
exit;
