<?php
/**
 * AI Camera Product Detection API Endpoint
 * AI Camera POS System
 * 
 * Powered by Google Gemini Vision API
 */

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/auth.php';

if (!is_logged_in()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized. Please login.']);
    exit;
}

$pdo = get_db_connection();

// 1. Get Gemini Settings
$apiKey = get_setting('gemini_api_key', '');
$model = get_setting('gemini_model', 'gemini-3.7-flash');

if (empty($apiKey)) {
    echo json_encode([
        'success' => false,
        'message' => 'Gemini API Key is not configured. Please go to Admin Settings and enter your Gemini API Key.'
    ]);
    exit;
}

// 2. Extract Image Data (Support base64 POST or multipart file upload)
$base64Image = '';
$mimeType = 'image/jpeg';

if (!empty($_POST['image_base64'])) {
    $raw = $_POST['image_base64'];
    if (preg_match('/^data:(image\/[a-zA-Z0-9\+\-\.]+);base64,(.+)$/', $raw, $matches)) {
        $mimeType = $matches[1];
        $base64Image = $matches[2];
    } else {
        $base64Image = $raw;
    }
} elseif (!empty($_FILES['image']['tmp_name']) && is_uploaded_file($_FILES['image']['tmp_name'])) {
    $tmpFile = $_FILES['image']['tmp_name'];
    $mimeType = mime_content_type($tmpFile) ?: 'image/jpeg';
    $base64Image = base64_encode(file_get_contents($tmpFile));
}

if (empty($base64Image)) {
    echo json_encode(['success' => false, 'message' => 'No image data was received from the camera.']);
    exit;
}

// 3. Fetch Active Catalog from MySQL
$stmt = $pdo->query("
    SELECT id, name, sku, price, stock_quantity, image_path 
    FROM products 
    WHERE status = 'active'
");
$allProducts = $stmt->fetchAll();

if (empty($allProducts)) {
    echo json_encode(['success' => false, 'message' => 'No active products exist in the store database.']);
    exit;
}

// Map products by SKU and lowercased name for fast matching
$catalogBySku = [];
$catalogByName = [];
$catalogTextList = [];

foreach ($allProducts as $prod) {
    $catalogBySku[$prod['sku']] = $prod;
    $cleanName = strtolower(trim($prod['name']));
    $catalogByName[$cleanName] = $prod;
    $catalogTextList[] = "- Product: \"{$prod['name']}\" | SKU: {$prod['sku']}";
}

$catalogPrompt = implode("\n", $catalogTextList);

// 4. Construct Gemini Prompt
$systemPrompt = <<<PROMPT
You are an expert AI vision scanner for a supermarket checkout POS system.
Examine the image carefully and detect any retail products present in the photo. Count how many units of each product are visible.

Here is the store's current active product catalog:
$catalogPrompt

Rules:
1. Match detected items to the store catalog where possible.
2. For each distinct product found, return:
   - "detected_name": short recognizable name of the item
   - "sku": the exact matching SKU code from the store catalog above, or null if the item is not in the catalog
   - "quantity": integer count of how many units of this item are visible in the photo (at least 1)
3. If an item in the image is NOT in the store catalog, set "sku" to null.
4. Output MUST be a valid JSON array of objects. Do not include markdown codeblocks or extra text. Example:
[
  {"detected_name": "Coca-Cola Can 320ml", "sku": "BEV-001", "quantity": 2},
  {"detected_name": "Unknown Chocolate Bar", "sku": null, "quantity": 1}
]
PROMPT;

// 5. Call Google Gemini API
$url = "https://generativelanguage.googleapis.com/v1beta/models/" . urlencode($model) . ":generateContent?key=" . urlencode($apiKey);

$requestPayload = [
    'contents' => [
        [
            'role' => 'user',
            'parts' => [
                ['text' => $systemPrompt],
                [
                    'inline_data' => [
                        'mime_type' => $mimeType,
                        'data'      => $base64Image
                    ]
                ]
            ]
        ]
    ],
    'generationConfig' => [
        'temperature'       => 0.1,
        'response_mime_type'=> 'application/json'
    ]
];

$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => json_encode($requestPayload),
    CURLOPT_HTTPHEADER     => [
        'Content-Type: application/json'
    ],
    CURLOPT_TIMEOUT        => 20,
    CURLOPT_SSL_VERIFYPEER => true
]);

$apiResponse = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

if ($curlError) {
    echo json_encode([
        'success' => false,
        'message' => 'Failed to reach Google Gemini API: ' . $curlError
    ]);
    exit;
}

$decoded = json_decode($apiResponse, true);

if ($httpCode !== 200 || !isset($decoded['candidates'][0]['content']['parts'][0]['text'])) {
    $errorMsg = $decoded['error']['message'] ?? "Gemini API error (HTTP {$httpCode}).";
    echo json_encode([
        'success' => false,
        'message' => $errorMsg,
        'details' => $decoded
    ]);
    exit;
}

$aiText = trim($decoded['candidates'][0]['content']['parts'][0]['text']);

// Remove markdown backticks if Gemini included them despite json instruction
$cleanedJson = preg_replace('/^```(?:json)?\s*|\s*```$/i', '', $aiText);
$aiDetectedList = json_decode($cleanedJson, true);

if (!is_array($aiDetectedList)) {
    echo json_encode([
        'success' => false,
        'message' => 'AI returned an unexpected format. Please try scanning again with clearer lighting.',
        'raw'     => $aiText
    ]);
    exit;
}

// 6. Match Detected Items STRICTLY with MySQL
// AI should ONLY identify the product. The system must get:
// Product name, SKU, Price, Stock from MySQL.
// Do NOT allow AI to create a new product or change the price.
$verifiedResults = [];

foreach ($aiDetectedList as $item) {
    $detectedName = trim($item['detected_name'] ?? 'Unidentified Item');
    $detectedSku = trim($item['sku'] ?? '');
    $qty = max(1, (int)($item['quantity'] ?? 1));

    $matchedProduct = null;

    // First try matching by SKU
    if (!empty($detectedSku) && isset($catalogBySku[$detectedSku])) {
        $matchedProduct = $catalogBySku[$detectedSku];
    }

    // Second try matching by exact or partial name in MySQL
    if (!$matchedProduct) {
        $lowerDetected = strtolower($detectedName);
        if (isset($catalogByName[$lowerDetected])) {
            $matchedProduct = $catalogByName[$lowerDetected];
        } else {
            // Fuzzy search across catalog names
            foreach ($allProducts as $p) {
                $pNameLower = strtolower($p['name']);
                if (str_contains($pNameLower, $lowerDetected) || str_contains($lowerDetected, $pNameLower)) {
                    $matchedProduct = $p;
                    break;
                }
            }
        }
    }

    if ($matchedProduct) {
        // MATCHED: Load official data strictly from MySQL
        $verifiedResults[] = [
            'found'           => true,
            'id'              => (int)$matchedProduct['id'],
            'name'            => $matchedProduct['name'],
            'sku'             => $matchedProduct['sku'],
            'price'           => (float)$matchedProduct['price'],
            'formatted_price' => format_currency($matchedProduct['price']),
            'stock'           => (int)$matchedProduct['stock_quantity'],
            'image_url'       => !empty($matchedProduct['image_path']) ? base_url($matchedProduct['image_path']) : base_url('assets/uploads/products/default_product.svg'),
            'quantity'        => $qty,
            'line_total'      => round((float)$matchedProduct['price'] * $qty, 2),
            'formatted_total' => format_currency((float)$matchedProduct['price'] * $qty)
        ];
    } else {
        // UNMATCHED: Show "Product not found"
        $verifiedResults[] = [
            'found'         => false,
            'detected_name' => $detectedName,
            'message'       => 'Product not found',
            'quantity'      => $qty
        ];
    }
}

echo json_encode([
    'success' => true,
    'model'   => $model,
    'items'   => $verifiedResults,
    'total_detected' => count($verifiedResults)
]);
