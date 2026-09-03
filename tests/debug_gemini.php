<?php
require_once __DIR__ . '/../config/config.php';
$pdo = get_db_connection();
$rowKey = $pdo->query("SELECT setting_value FROM settings WHERE setting_key = 'gemini_api_key'")->fetch();
$apiKey = $rowKey['setting_value'] ?? '';
$rowModel = $pdo->query("SELECT setting_value FROM settings WHERE setting_key = 'gemini_model'")->fetch();
$model = $rowModel['setting_value'] ?? 'gemini-3.7-flash';

echo "Model: " . $model . PHP_EOL;
echo "API Key length: " . strlen($apiKey) . " starts with: " . substr($apiKey, 0, 6) . PHP_EOL;

if (empty($apiKey)) {
    echo "No API key saved!" . PHP_EOL;
    exit;
}

$url = "https://generativelanguage.googleapis.com/v1beta/models/" . urlencode($model) . ":generateContent?key=" . urlencode($apiKey);

$payload = [
    'contents' => [
        [
            'role' => 'user',
            'parts' => [
                ['text' => 'Ping test. Reply with exactly: "AI POS Engine Ready."']
            ]
        ]
    ],
    'generationConfig' => [
        'temperature' => 0.1,
        'maxOutputTokens' => 30
    ]
];

$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => json_encode($payload),
    CURLOPT_HTTPHEADER     => [
        'Content-Type: application/json'
    ],
    CURLOPT_TIMEOUT        => 15,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_SSL_VERIFYHOST => 0
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlErr = curl_error($ch);
curl_close($ch);

echo "HTTP Code: " . $httpCode . PHP_EOL;
echo "Curl error: " . ($curlErr ?: 'None') . PHP_EOL;
echo "Raw Response: " . PHP_EOL . $response . PHP_EOL;
