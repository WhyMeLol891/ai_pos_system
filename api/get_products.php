<?php
/**
 * Get Products Catalog Endpoint (for POS)
 * AI Camera POS System
 */

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/auth.php';

if (!is_logged_in()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$pdo = get_db_connection();

$categoryId = (int)($_GET['category_id'] ?? 0);
$search = trim($_GET['search'] ?? '');

$sql = "
    SELECT p.id, p.category_id, p.name, p.sku, p.price, p.stock_quantity, p.image_path, c.name AS category_name
    FROM products p
    LEFT JOIN categories c ON p.category_id = c.id
    WHERE p.status = 'active'
";
$params = [];

if ($categoryId > 0) {
    $sql .= " AND p.category_id = :cat_id";
    $params['cat_id'] = $categoryId;
}

if ($search !== '') {
    $sql .= " AND (p.name LIKE :search OR p.sku LIKE :search)";
    $params['search'] = "%{$search}%";
}

$sql .= " ORDER BY p.name ASC";

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $products = $stmt->fetchAll();

    // Add formatted image URLs
    foreach ($products as &$p) {
        $p['formatted_price'] = format_currency($p['price']);
        $p['image_url'] = !empty($p['image_path']) ? base_url($p['image_path']) : base_url('assets/uploads/products/default_product.svg');
    }

    echo json_encode([
        'success'  => true,
        'products' => $products
    ]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
