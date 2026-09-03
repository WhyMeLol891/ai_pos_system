<?php
/**
 * Cashier Sales History
 * AI Camera POS System
 */

$pageTitle = 'My Sales History';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/auth.php';

require_login();

$pdo = get_db_connection();
$currentUser = current_user();

$dateFilter = trim($_GET['date'] ?? date('Y-m-d'));

$sql = "
    SELECT o.*, u.full_name AS cashier_name 
    FROM orders o 
    LEFT JOIN users u ON o.cashier_id = u.id 
    WHERE DATE(o.created_at) = :dt
";
$params = ['dt' => $dateFilter];

// If not admin, show only current cashier's sales
if (!is_admin()) {
    $sql .= " AND o.cashier_id = :cid";
    $params['cid'] = $currentUser['id'];
}

$sql .= " ORDER BY o.created_at DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$orders = $stmt->fetchAll();

// Total for the day
$dayTotal = 0.0;
foreach ($orders as $o) {
    $dayTotal += (float)$o['grand_total'];
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-3">
    <div>
        <h3 class="fw-bold mb-1"><i class="bi bi-clock-history text-primary me-2"></i>My Sales Transactions</h3>
        <p class="text-muted mb-0">Review receipts and daily totals handled by <strong><?= clean($currentUser['full_name']) ?></strong>.</p>
    </div>
    <a href="<?= base_url('pos/index.php') ?>" class="btn btn-primary fw-bold px-3 shadow-sm">
        <i class="bi bi-shop me-1"></i> Back to POS
    </a>
</div>

<!-- Filter & Daily Summary Card -->
<div class="row g-3 mb-4">
    <div class="col-12 col-md-6 col-lg-4">
        <div class="card border-0 shadow-sm p-3">
            <form method="GET" action="<?= base_url('pos/sales.php') ?>" class="d-flex gap-2 align-items-end">
                <div class="flex-grow-1">
                    <label class="form-label small fw-semibold text-secondary">Select Date</label>
                    <input type="date" name="date" class="form-control" value="<?= clean($dateFilter) ?>">
                </div>
                <button type="submit" class="btn btn-primary">Filter</button>
            </form>
        </div>
    </div>
    <div class="col-12 col-md-6 col-lg-4">
        <div class="card border-0 shadow-sm bg-success text-white p-3">
            <span class="small text-white-50 fw-semibold text-uppercase">Sales on <?= date('d M Y', strtotime($dateFilter)) ?></span>
            <h3 class="fw-bold mt-1 mb-0"><?= format_currency($dayTotal) ?></h3>
            <span class="small text-white-50"><?= count($orders) ?> completed transactions</span>
        </div>
    </div>
</div>

<!-- Orders Table -->
<div class="card border-0 shadow-sm">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light small">
                    <tr>
                        <th>Invoice #</th>
                        <th>Time</th>
                        <th>Customer</th>
                        <th>Method</th>
                        <th class="text-end">Total</th>
                        <th class="text-center" style="width: 130px;">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($orders)): ?>
                        <tr>
                            <td colspan="6" class="text-center py-5 text-muted">
                                <i class="bi bi-inbox fs-1 d-block mb-2 text-secondary"></i>
                                No transactions found for this date.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($orders as $o): ?>
                            <tr>
                                <td><span class="font-monospace fw-bold text-primary"><?= clean($o['invoice_no']) ?></span></td>
                                <td><span class="text-muted small"><?= format_date($o['created_at'], 'h:i A') ?></span></td>
                                <td><span class="text-dark small"><?= clean($o['customer_name']) ?></span></td>
                                <td>
                                    <?php
                                    $badge = match($o['payment_method']) {
                                        'cash' => 'bg-success-subtle text-success border border-success-subtle',
                                        'card' => 'bg-primary-subtle text-primary border border-primary-subtle',
                                        'qr'   => 'bg-info-subtle text-info-emphasis border border-info-subtle',
                                        default=> 'bg-secondary'
                                    };
                                    ?>
                                    <span class="badge <?= $badge ?> text-uppercase"><?= clean($o['payment_method']) ?></span>
                                </td>
                                <td class="text-end fw-bold fs-6"><?= format_currency($o['grand_total']) ?></td>
                                <td class="text-center">
                                    <a href="<?= base_url('receipt_view.php?invoice=' . urlencode($o['invoice_no'])) ?>" target="_blank" class="btn btn-sm btn-outline-secondary py-1 px-2" title="Print Receipt">
                                        <i class="bi bi-printer me-1"></i> Receipt
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
