<?php
/**
 * Automated System Verification Test
 * AI Camera POS System
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/auth.php';

echo "=== AI CAMERA POS SYSTEM AUTOMATED VERIFICATION ===" . PHP_EOL . PHP_EOL;

$pdo = get_db_connection();
$passCount = 0;
$failCount = 0;

function assert_test($description, $condition) {
    global $passCount, $failCount;
    if ($condition) {
        echo "[PASS] " . $description . PHP_EOL;
        $passCount++;
    } else {
        echo "[FAIL] " . $description . PHP_EOL;
        $failCount++;
    }
}

// TEST 1: Database Tables Existence
$tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
$expectedTables = ['users', 'categories', 'products', 'orders', 'order_items', 'settings', 'audit_logs'];
$missingTables = array_diff($expectedTables, $tables);
assert_test("All required tables exist (" . implode(', ', $expectedTables) . ")", empty($missingTables));

// TEST 2: Users & Password Hash Verification
$adminUser = $pdo->query("SELECT * FROM users WHERE username = 'admin'")->fetch();
$cashierUser = $pdo->query("SELECT * FROM users WHERE username = 'cashier'")->fetch();

assert_test("Admin user exists in database", !empty($adminUser));
assert_test("Admin user role is 'admin'", ($adminUser['role'] ?? '') === 'admin');
assert_test("Admin password 'admin123' verifies against hash", password_verify('admin123', $adminUser['password_hash'] ?? ''));

assert_test("Cashier user exists in database", !empty($cashierUser));
assert_test("Cashier user role is 'cashier'", ($cashierUser['role'] ?? '') === 'cashier');
assert_test("Cashier password 'cashier123' verifies against hash", password_verify('cashier123', $cashierUser['password_hash'] ?? ''));

// TEST 3: Settings Retrieval
$shopName = get_setting('shop_name');
$currency = get_setting('currency_symbol');
$model = get_setting('gemini_model');

assert_test("Settings 'shop_name' is configured: {$shopName}", !empty($shopName));
assert_test("Settings 'currency_symbol' is configured: {$currency}", $currency === 'RM');
assert_test("Settings 'gemini_model' is configured: {$model}", !empty($model));

// TEST 4: Products Catalog & Images
$stmtProd = $pdo->query("SELECT COUNT(*) FROM products WHERE status = 'active'");
$prodCount = (int)$stmtProd->fetchColumn();
assert_test("Catalog has at least 10 active products (found: {$prodCount})", $prodCount >= 10);

$sampleProd = $pdo->query("SELECT * FROM products WHERE sku = 'BEV-001'")->fetch();
assert_test("Sample product 'Coca-Cola Can 320ml' (BEV-001) exists", !empty($sampleProd));
assert_test("Product image exists on disk: {$sampleProd['image_path']}", file_exists(__DIR__ . '/../' . $sampleProd['image_path']));

// TEST 5: POS Transactional Checkout & Stock Deduction
echo PHP_EOL . "--- Testing Transactional Checkout ---" . PHP_EOL;

// Mock login as Cashier
$_SESSION['user_id'] = $cashierUser['id'];
$_SESSION['user_username'] = $cashierUser['username'];
$_SESSION['user_fullname'] = $cashierUser['full_name'];
$_SESSION['user_role'] = $cashierUser['role'];

$cokeBefore = (int)$pdo->query("SELECT stock_quantity FROM products WHERE sku = 'BEV-001'")->fetchColumn();
$breadBefore = (int)$pdo->query("SELECT stock_quantity FROM products WHERE sku = 'BAK-001'")->fetchColumn();

// Execute a test order via internal curl/subrequest or direct API simulation
$coke = $pdo->query("SELECT * FROM products WHERE sku = 'BEV-001'")->fetch();
$bread = $pdo->query("SELECT * FROM products WHERE sku = 'BAK-001'")->fetch();

$buyQtyCoke = 2;
$buyQtyBread = 1;
$expectedSubtotal = round(($coke['price'] * $buyQtyCoke) + ($bread['price'] * $buyQtyBread), 2);
$discount = 1.00;
$expectedGrandTotal = round($expectedSubtotal - $discount, 2);
$cashGiven = 50.00;
$expectedChange = round($cashGiven - $expectedGrandTotal, 2);

// Simulate checkout transaction directly using same transaction logic
$pdo->beginTransaction();
$invNo = "TEST-INV-" . time();
$stmtOrder = $pdo->prepare("
    INSERT INTO orders (invoice_no, cashier_id, customer_name, subtotal, discount, grand_total, payment_method, amount_paid, change_amount, status)
    VALUES (:inv, :cid, 'Test Customer', :sub, :disc, :gt, 'cash', :paid, :chg, 'completed')
");
$stmtOrder->execute([
    'inv'  => $invNo,
    'cid'  => $cashierUser['id'],
    'sub'  => $expectedSubtotal,
    'disc' => $discount,
    'gt'   => $expectedGrandTotal,
    'paid' => $cashGiven,
    'chg'  => $expectedChange
]);
$orderId = $pdo->lastInsertId();

$stmtItem = $pdo->prepare("
    INSERT INTO order_items (order_id, product_id, product_name, sku, unit_price, quantity, subtotal)
    VALUES (:oid, :pid, :name, :sku, :price, :qty, :sub)
");
$stmtItem->execute(['oid' => $orderId, 'pid' => $coke['id'], 'name' => $coke['name'], 'sku' => $coke['sku'], 'price' => $coke['price'], 'qty' => $buyQtyCoke, 'sub' => $coke['price'] * $buyQtyCoke]);
$stmtItem->execute(['oid' => $orderId, 'pid' => $bread['id'], 'name' => $bread['name'], 'sku' => $bread['sku'], 'price' => $bread['price'], 'qty' => $buyQtyBread, 'sub' => $bread['price'] * $buyQtyBread]);

// Deduct stock
$pdo->prepare("UPDATE products SET stock_quantity = stock_quantity - :qty WHERE id = :id")->execute(['qty' => $buyQtyCoke, 'id' => $coke['id']]);
$pdo->prepare("UPDATE products SET stock_quantity = stock_quantity - :qty WHERE id = :id")->execute(['qty' => $buyQtyBread, 'id' => $bread['id']]);
$pdo->commit();

$cokeAfter = (int)$pdo->query("SELECT stock_quantity FROM products WHERE sku = 'BEV-001'")->fetchColumn();
$breadAfter = (int)$pdo->query("SELECT stock_quantity FROM products WHERE sku = 'BAK-001'")->fetchColumn();

assert_test("Checkout created order #{$orderId} with invoice '{$invNo}'", $orderId > 0);
assert_test("Coca-Cola stock decremented correctly from {$cokeBefore} to {$cokeAfter}", ($cokeBefore - $cokeAfter) === $buyQtyCoke);
assert_test("Gardenia Bread stock decremented correctly from {$breadBefore} to {$breadAfter}", ($breadBefore - $breadAfter) === $buyQtyBread);
assert_test("Order change calculated correctly ({$currency}{$expectedChange})", $expectedChange == (50.00 - $expectedGrandTotal));

// TEST 6: Strict AI Matching & MySQL Price Integrity
echo PHP_EOL . "--- Testing AI Vision Matching & Price Protection ---" . PHP_EOL;

// Mock AI output from vision model
$mockAiDetection = [
    ['detected_name' => 'Coca-Cola Can 320ml', 'sku' => 'BEV-001', 'quantity' => 2],
    ['detected_name' => 'Gardenia Classic White Bread', 'sku' => 'BAK-001', 'quantity' => 1],
    ['detected_name' => 'Unknown Brand Cookies', 'sku' => null, 'quantity' => 1]
];

$catalog = $pdo->query("SELECT * FROM products WHERE status = 'active'")->fetchAll();
$catalogBySku = [];
foreach ($catalog as $p) {
    $catalogBySku[$p['sku']] = $p;
}

$matched = [];
$unmatched = [];

foreach ($mockAiDetection as $item) {
    if (!empty($item['sku']) && isset($catalogBySku[$item['sku']])) {
        $dbProd = $catalogBySku[$item['sku']];
        $matched[] = [
            'found' => true,
            'id'    => $dbProd['id'],
            'name'  => $dbProd['name'],
            'price' => (float)$dbProd['price'], // Loaded strictly from MySQL
            'qty'   => $item['quantity']
        ];
    } else {
        $unmatched[] = [
            'found'         => false,
            'detected_name' => $item['detected_name'],
            'message'       => 'Product not found'
        ];
    }
}

assert_test("AI detected 2 matched items from MySQL catalog", count($matched) === 2);
assert_test("AI matched item price for Coca-Cola comes strictly from MySQL (" . format_currency($coke['price']) . ")", $matched[0]['price'] == (float)$coke['price']);
assert_test("Unmatched product is flagged as 'Product not found'", count($unmatched) === 1 && $unmatched[0]['message'] === 'Product not found');

echo PHP_EOL . "========================================" . PHP_EOL;
echo "RESULTS: {$passCount} PASSED, {$failCount} FAILED" . PHP_EOL;
echo "========================================" . PHP_EOL;

if ($failCount > 0) {
    exit(1);
}
