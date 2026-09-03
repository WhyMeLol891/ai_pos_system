<?php
/**
 * Test Web Login & Session
 */

$cookieFile = __DIR__ . '/test_cookie.txt';
if (file_exists($cookieFile)) unlink($cookieFile);

// 1. Fetch login page
$ch = curl_init('http://localhost/ai_pos_system/login.php');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieFile);
curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);
$html = curl_exec($ch);

preg_match('/name="csrf_token"\s+value="([^"]+)"/', $html, $matches);
$token = $matches[1] ?? '';

if (empty($token)) {
    echo "[FAIL] Could not extract CSRF token from login page." . PHP_EOL;
    exit(1);
}

// 2. Submit credentials
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
    'csrf_token' => $token,
    'username'   => 'admin',
    'password'   => 'admin123'
]));
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
$res = curl_exec($ch);
$info = curl_getinfo($ch);

echo "Target URL: " . $info['url'] . " (Status " . $info['http_code'] . ")" . PHP_EOL;

if (str_contains($res, 'Dashboard') && str_contains($res, 'System Administrator')) {
    echo "[PASS] Web login succeeded! Landed on Admin Dashboard." . PHP_EOL;
} else {
    echo "[FAIL] Did not land on Admin Dashboard. Snippet: " . substr($res, 0, 300) . PHP_EOL;
}

// 3. Test POS as Cashier
if (file_exists($cookieFile)) unlink($cookieFile);
$ch2 = curl_init('http://localhost/ai_pos_system/login.php');
curl_setopt($ch2, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch2, CURLOPT_COOKIEJAR, $cookieFile);
curl_setopt($ch2, CURLOPT_COOKIEFILE, $cookieFile);
$html2 = curl_exec($ch2);
preg_match('/name="csrf_token"\s+value="([^"]+)"/', $html2, $matches2);
$token2 = $matches2[1] ?? '';

curl_setopt($ch2, CURLOPT_POST, true);
curl_setopt($ch2, CURLOPT_POSTFIELDS, http_build_query([
    'csrf_token' => $token2,
    'username'   => 'cashier',
    'password'   => 'cashier123'
]));
curl_setopt($ch2, CURLOPT_FOLLOWLOCATION, true);
$res2 = curl_exec($ch2);
$info2 = curl_getinfo($ch2);

echo "Cashier Target URL: " . $info2['url'] . " (Status " . $info2['http_code'] . ")" . PHP_EOL;
if (str_contains($res2, 'AI Camera Scan') && str_contains($res2, 'Store Cashier')) {
    echo "[PASS] Cashier login succeeded! Landed on POS Screen." . PHP_EOL;
} else {
    echo "[FAIL] Cashier login failed." . PHP_EOL;
}

if (file_exists($cookieFile)) unlink($cookieFile);
