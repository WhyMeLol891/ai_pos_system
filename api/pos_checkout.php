<?php
/**
 * POS Checkout API Endpoint
 * AI Camera POS System
 * 
 * Performs transactional order processing and inventory deduction.
 */

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/auth.php';

if (!is_logged_in()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized. Please login.']);
    exit;
}

$pdo = get_db_connection();
$currentUser = current_user();

// Read JSON input or POST form
$rawInput = file_get_contents('php://input');
$payload = json_decode($rawInput, true);

if (!is_array($payload)) {
    $payload = $_POST;
}

$items = $payload['items'] ?? [];
$paymentMethod = in_array($payload['payment_method'] ?? '', ['cash', 'card', 'qr']) ? $payload['payment_method'] : 'cash';
$amountPaid = (float)($payload['amount_paid'] ?? 0);
$discount = max(0, (float)($payload['discount'] ?? 0));
$customerName = trim($payload['customer_name'] ?? 'Walk-in Customer') ?: 'Walk-in Customer';

if (empty($items) || !is_array($items)) {
    echo json_encode(['success' => false, 'message' => 'The cart is empty. Please add items before checking out.']);
    exit;
}

try {
    $pdo->beginTransaction();

    $computedSubtotal = 0.0;
    $orderItemsData = [];

    // Verify stock and calculate real total from database prices
    foreach ($items as $item) {
        $productId = (int)($item['id'] ?? 0);
        $qty = max(1, (int)($item['quantity'] ?? 1));

        if ($productId <= 0) {
            throw new Exception("Invalid item in cart.");
        }

        // Lock row for update
        $stmtProd = $pdo->prepare("SELECT id, name, sku, price, stock_quantity FROM products WHERE id = :id FOR UPDATE");
        $stmtProd->execute(['id' => $productId]);
        $prod = $stmtProd->fetch();

        if (!$prod) {
            throw new Exception("Product ID #{$productId} is no longer available.");
        }

        if ($prod['stock_quantity'] < $qty) {
            throw new Exception("Insufficient stock for '{$prod['name']}'. Only {$prod['stock_quantity']} left in stock.");
        }

        $unitPrice = (float)$prod['price'];
        $lineSubtotal = round($unitPrice * $qty, 2);
        $computedSubtotal += $lineSubtotal;

        $orderItemsData[] = [
            'product_id'   => $prod['id'],
            'product_name' => $prod['name'],
            'sku'          => $prod['sku'],
            'unit_price'   => $unitPrice,
            'quantity'     => $qty,
            'subtotal'     => $lineSubtotal
        ];
    }

    $grandTotal = max(0.00, round($computedSubtotal - $discount, 2));

    // Validate payment amounts
    $changeAmount = 0.00;
    if ($paymentMethod === 'cash') {
        if ($amountPaid < $grandTotal) {
            throw new Exception("Cash paid (" . format_currency($amountPaid) . ") is less than the total (" . format_currency($grandTotal) . ").");
        }
        $changeAmount = round($amountPaid - $grandTotal, 2);
    } else {
        // Card or QR payment pays exact
        $amountPaid = $grandTotal;
        $changeAmount = 0.00;
    }

    // Generate Invoice Number
    $datePart = date('Ymd');
    $randomPart = strtoupper(substr(uniqid(), -4));
    $invoiceNo = "INV-{$datePart}-{$randomPart}";

    // Insert Order
    $stmtOrder = $pdo->prepare("
        INSERT INTO orders (
            invoice_no, cashier_id, customer_name, subtotal, discount, 
            grand_total, payment_method, amount_paid, change_amount, status
        ) VALUES (
            :inv, :cashier_id, :cust_name, :subtotal, :discount, 
            :grand_total, :method, :paid, :change, 'completed'
        )
    ");
    $stmtOrder->execute([
        'inv'         => $invoiceNo,
        'cashier_id'  => $currentUser['id'],
        'cust_name'   => $customerName,
        'subtotal'    => $computedSubtotal,
        'discount'    => $discount,
        'grand_total' => $grandTotal,
        'method'      => $paymentMethod,
        'paid'        => $amountPaid,
        'change'      => $changeAmount
    ]);
    $orderId = $pdo->lastInsertId();

    // Insert Order Items and Deduct Stock
    $stmtItem = $pdo->prepare("
        INSERT INTO order_items (order_id, product_id, product_name, sku, unit_price, quantity, subtotal)
        VALUES (:order_id, :product_id, :product_name, :sku, :unit_price, :quantity, :subtotal)
    ");

    $stmtDeduct = $pdo->prepare("
        UPDATE products 
        SET stock_quantity = stock_quantity - :qty 
        WHERE id = :id
    ");

    foreach ($orderItemsData as $oi) {
        $stmtItem->execute([
            'order_id'     => $orderId,
            'product_id'   => $oi['product_id'],
            'product_name' => $oi['product_name'],
            'sku'          => $oi['sku'],
            'unit_price'   => $oi['unit_price'],
            'quantity'     => $oi['quantity'],
            'subtotal'     => $oi['subtotal']
        ]);

        $stmtDeduct->execute([
            'qty' => $oi['quantity'],
            'id'  => $oi['product_id']
        ]);
    }

    $pdo->commit();

    log_audit('CHECKOUT', "Order {$invoiceNo} completed by {$currentUser['username']}. Total: " . format_currency($grandTotal));

    // Return receipt payload for immediate modal rendering
    echo json_encode([
        'success'      => true,
        'message'      => 'Order completed successfully!',
        'order'        => [
            'id'             => $orderId,
            'invoice_no'     => $invoiceNo,
            'date_time'      => date('d M Y, h:i A'),
            'cashier_name'   => $currentUser['full_name'],
            'customer_name'  => $customerName,
            'payment_method' => strtoupper($paymentMethod),
            'subtotal'       => $computedSubtotal,
            'discount'       => $discount,
            'grand_total'    => $grandTotal,
            'amount_paid'    => $amountPaid,
            'change_amount'  => $changeAmount,
            'items'          => $orderItemsData,
            'shop_name'      => get_setting('shop_name', 'AI SMART MART'),
            'shop_address'   => get_setting('shop_address', ''),
            'shop_phone'     => get_setting('shop_phone', ''),
            'receipt_footer' => get_setting('receipt_footer', 'Thank you for shopping with us!'),
            'currency'       => get_setting('currency_symbol', 'RM')
        ],
        'receipt_url'  => base_url('receipt_view.php?invoice=' . urlencode($invoiceNo))
    ]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
