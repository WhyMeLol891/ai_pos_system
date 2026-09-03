<?php
/**
 * Test Gemini AI Connection Endpoint
 * AI Camera POS System
 */

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/auth.php';

// Only logged in admins can test connection
if (!is_admin()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$apiKey = trim($_POST['api_key'] ?? '') ?: get_setting('gemini_api_key', '') ?: (getenv('GEMINI_API_KEY') ?: '');
$model = trim($_POST['model'] ?? '') ?: get_setting('gemini_model', '') ?: (getenv('GEMINI_MODEL') ?: 'gemini-3.7-flash');

if (empty($apiKey)) {
    echo json_encode(['success' => false, 'message' => 'Gemini API Key is empty. Please enter an API key.']);
    exit;
}

$startTime = microtime(true);

// Build API URL
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
    CURLOPT_TIMEOUT        => 12,
    CURLOPT_SSL_VERIFYPEER => true
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

$latency = round((microtime(true) - $startTime) * 1000);

if ($curlError) {
    echo json_encode([
        'success' => false,
        'message' => 'cURL connection error: ' . $curlError
    ]);
    exit;
}

$result = json_decode($response, true);

if ($httpCode === 200 && isset($result['candidates'][0]['content']['parts'][0]['text'])) {
    $reply = trim($result['candidates'][0]['content']['parts'][0]['text']);
    echo json_encode([
        'success'    => true,
        'model'      => $model,
        'latency_ms' => $latency,
        'reply'      => $reply
    ]);
} else {
    $errorMsg = $result['error']['message'] ?? "HTTP {$httpCode}: Failed to connect to Gemini API.";
    echo json_encode([
        'success' => false,
        'message' => $errorMsg,
        'raw'     => $result
    ]);
}
