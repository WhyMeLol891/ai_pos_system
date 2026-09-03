<?php
/**
 * Standalone Receipt Viewer & Thermal Printer Page
 * AI Camera POS System
 */

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/auth.php';

require_login();

$invoiceNo = trim($_GET['invoice'] ?? '');
if (empty($invoiceNo)) {
    die("Invoice number is required.");
}

$pdo = get_db_connection();

// Fetch order
$stmt = $pdo->prepare("
    SELECT o.*, u.full_name AS cashier_name 
    FROM orders o 
    LEFT JOIN users u ON o.cashier_id = u.id 
    WHERE o.invoice_no = :inv 
    LIMIT 1
");
$stmt->execute(['inv' => $invoiceNo]);
$order = $stmt->fetch();

if (!$order) {
    die("Order with invoice '{$invoiceNo}' was not found.");
}

// Fetch order items
$stmtItems = $pdo->prepare("
    SELECT * FROM order_items WHERE order_id = :oid ORDER BY id ASC
");
$stmtItems->execute(['oid' => $order['id']]);
$items = $stmtItems->fetchAll();

$shopName = get_setting('shop_name', 'AI SMART MART');
$shopAddress = get_setting('shop_address', '');
$shopPhone = get_setting('shop_phone', '');
$currency = get_setting('currency_symbol', 'RM');
$receiptFooter = get_setting('receipt_footer', 'Thank you for shopping with us!');
$autoPrint = isset($_GET['print']) && $_GET['print'] == '1';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Receipt - <?= clean($order['invoice_no']) ?></title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link rel="stylesheet" href="<?= base_url('assets/css/receipt.css') ?>">
    <style>
        body {
            background-color: #f1f5f9;
            padding: 30px 10px;
        }
        @media print {
            body {
                background: #ffffff;
                padding: 0;
            }
        }
    </style>
</head>
<body>

<div class="text-center mb-3 no-print">
    <button type="button" class="btn btn-success fw-bold px-4 shadow-sm" onclick="window.print()">
        <i class="bi bi-printer-fill me-1"></i> Print Receipt
    </button>
    <button type="button" class="btn btn-light border ms-2" onclick="window.close()">
        Close Window
    </button>
</div>

<div class="receipt-container">
    <div class="receipt-header">
        <div class="receipt-shop-title"><?= clean($shopName) ?></div>
        <?php if (!empty($shopAddress)): ?>
            <div class="receipt-info-line"><?= clean($shopAddress) ?></div>
        <?php endif; ?>
        <?php if (!empty($shopPhone)): ?>
            <div class="receipt-info-line">Tel: <?= clean($shopPhone) ?></div>
        <?php endif; ?>
    </div>

    <div class="receipt-divider"></div>

    <div class="receipt-info-line"><strong>Receipt #:</strong> <?= clean($order['invoice_no']) ?></div>
    <div class="receipt-info-line"><strong>Date/Time:</strong> <?= format_date($order['created_at']) ?></div>
    <div class="receipt-info-line"><strong>Cashier:</strong> <?= clean($order['cashier_name'] ?? 'Staff') ?></div>
    <div class="receipt-info-line"><strong>Customer:</strong> <?= clean($order['customer_name']) ?></div>

    <div class="receipt-divider"></div>

    <table class="receipt-table">
        <thead>
            <tr>
                <th style="text-align: left;">Product</th>
                <th style="text-align: center; width: 40px;">Qty</th>
                <th style="text-align: right; width: 55px;">Price</th>
                <th style="text-align: right; width: 65px;">Total</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($items as $item): ?>
                <tr>
                    <td style="padding: 3px 0;">
                        <?= clean($item['product_name']) ?><br>
                        <span style="font-size: 0.72rem; color: #64748b;"><?= clean($item['sku']) ?></span>
                    </td>
                    <td style="text-align: center;"><?= $item['quantity'] ?></td>
                    <td style="text-align: right;"><?= format_currency($item['unit_price']) ?></td>
                    <td style="text-align: right; font-weight: bold;"><?= format_currency($item['subtotal']) ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <div class="receipt-divider"></div>

    <div class="receipt-totals">
        <div class="receipt-totals-row">
            <span>Subtotal:</span>
            <span><?= format_currency($order['subtotal']) ?></span>
        </div>
        <?php if ((float)$order['discount'] > 0): ?>
            <div class="receipt-totals-row" style="color: #dc2626;">
                <span>Discount:</span>
                <span>-<?= format_currency($order['discount']) ?></span>
            </div>
        <?php endif; ?>
        <div class="receipt-totals-row receipt-grand-total">
            <span>GRAND TOTAL:</span>
            <span><?= format_currency($order['grand_total']) ?></span>
        </div>
        <div class="receipt-totals-row">
            <span>Payment (<?= strtoupper(clean($order['payment_method'])) ?>):</span>
            <span><?= format_currency($order['amount_paid']) ?></span>
        </div>
        <div class="receipt-totals-row" style="font-weight: bold;">
            <span>Change:</span>
            <span><?= format_currency($order['change_amount']) ?></span>
        </div>
    </div>

    <div class="receipt-divider"></div>

    <div class="receipt-footer">
        <p class="mb-1"><?= clean($receiptFooter) ?></p>
        <small class="text-muted">AI Camera POS &bull; www.pos.local</small>
    </div>
</div>

<?php if ($autoPrint): ?>
<script>
window.addEventListener('load', () => {
    window.print();
});
</script>
<?php endif; ?>

</body>
</html>
