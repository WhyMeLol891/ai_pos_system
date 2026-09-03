<?php
require_once __DIR__ . '/../config/config.php';
$pdo = get_db_connection();
$rowKey = $pdo->query("SELECT setting_value FROM settings WHERE setting_key = 'gemini_api_key'")->fetch();
$apiKey = $rowKey['setting_value'] ?? '';

// Try listing models with this key
$url = "https://generativelanguage.googleapis.com/v1beta/models?key=" . urlencode($apiKey);

$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 10,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_SSL_VERIFYHOST => 0
]);
$res = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "List Models HTTP Code: " . $code . PHP_EOL;
echo "Response: " . substr($res, 0, 500) . PHP_EOL;
