<?php
/**
 * Sales History & Financial Reports
 * AI Camera POS System
 */

$pageTitle = 'Sales History & Reports';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/auth.php';

require_admin();

$pdo = get_db_connection();

// Filters
$startDate = trim($_GET['start_date'] ?? date('Y-m-01'));
$endDate = trim($_GET['end_date'] ?? date('Y-m-d'));
$methodFilter = trim($_GET['payment_method'] ?? '');
$cashierFilter = (int)($_GET['cashier_id'] ?? 0);

$sql = "
    SELECT o.*, u.full_name AS cashier_name 
    FROM orders o 
    LEFT JOIN users u ON o.cashier_id = u.id 
    WHERE DATE(o.created_at) BETWEEN :start AND :end
";
$params = [
    'start' => $startDate,
    'end'   => $endDate
];

if (!empty($methodFilter)) {
    $sql .= " AND o.payment_method = :method";
    $params['method'] = $methodFilter;
}

if ($cashierFilter > 0) {
    $sql .= " AND o.cashier_id = :cashier";
    $params['cashier'] = $cashierFilter;
}

$sql .= " ORDER BY o.created_at DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$orders = $stmt->fetchAll();

// Aggregates
$totalRevenue = 0;
$totalOrders = count($orders);
$cashTotal = 0;
$cardTotal = 0;
$qrTotal = 0;

foreach ($orders as $o) {
    $val = (float)$o['grand_total'];
    $totalRevenue += $val;
    if ($o['payment_method'] === 'cash') $cashTotal += $val;
    elseif ($o['payment_method'] === 'card') $cardTotal += $val;
    elseif ($o['payment_method'] === 'qr') $qrTotal += $val;
}

$avgOrderValue = $totalOrders > 0 ? ($totalRevenue / $totalOrders) : 0;

// Cashiers list for filter
$cashiers = $pdo->query("SELECT id, full_name FROM users ORDER BY full_name ASC")->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-3">
    <div>
        <h3 class="fw-bold mb-1"><i class="bi bi-receipt text-primary me-2"></i>Sales History & Reports</h3>
        <p class="text-muted mb-0">Track store transactions, revenue by payment method, and inspect receipts.</p>
    </div>
    <a href="<?= base_url('pos/index.php') ?>" class="btn btn-warning fw-bold px-3 shadow-sm">
        <i class="bi bi-shop me-1"></i> Open POS
    </a>
</div>

<!-- Filters Card -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-body p-3">
        <form method="GET" action="<?= base_url('admin/sales.php') ?>" class="row g-2 align-items-end">
            <div class="col-6 col-md-3">
                <label class="form-label small fw-semibold text-secondary">Start Date</label>
                <input type="date" name="start_date" class="form-control form-control-sm" value="<?= clean($startDate) ?>">
            </div>
            <div class="col-6 col-md-3">
                <label class="form-label small fw-semibold text-secondary">End Date</label>
                <input type="date" name="end_date" class="form-control form-control-sm" value="<?= clean($endDate) ?>">
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label small fw-semibold text-secondary">Payment Method</label>
                <select name="payment_method" class="form-select form-select-sm">
                    <option value="">All Methods</option>
                    <option value="cash" <?= $methodFilter === 'cash' ? 'selected' : '' ?>>Cash</option>
                    <option value="card" <?= $methodFilter === 'card' ? 'selected' : '' ?>>Card</option>
                    <option value="qr" <?= $methodFilter === 'qr' ? 'selected' : '' ?>>QR Payment</option>
                </select>
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label small fw-semibold text-secondary">Cashier</label>
                <select name="cashier_id" class="form-select form-select-sm">
                    <option value="0">All Cashiers</option>
                    <?php foreach ($cashiers as $c): ?>
                        <option value="<?= $c['id'] ?>" <?= $cashierFilter == $c['id'] ? 'selected' : '' ?>>
                            <?= clean($c['full_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-12 col-md-2 d-flex gap-2">
                <button type="submit" class="btn btn-primary btn-sm flex-fill">Apply</button>
                <a href="<?= base_url('admin/sales.php') ?>" class="btn btn-outline-secondary btn-sm" title="Reset"><i class="bi bi-arrow-counterclockwise"></i></a>
            </div>
        </form>
    </div>
</div>

<!-- Summary Cards -->
<div class="row g-3 mb-4">
    <div class="col-12 col-sm-6 col-md-3">
        <div class="card border-0 bg-primary text-white stat-card p-3">
            <span class="text-white-50 small fw-semibold">TOTAL REVENUE</span>
            <h4 class="fw-bold mt-1 mb-0"><?= format_currency($totalRevenue) ?></h4>
            <span class="small text-white-50"><?= $totalOrders ?> transactions</span>
        </div>
    </div>
    <div class="col-12 col-sm-6 col-md-3">
        <div class="card border-0 bg-success text-white stat-card p-3">
            <span class="text-white-50 small fw-semibold">CASH SALES</span>
            <h4 class="fw-bold mt-1 mb-0"><?= format_currency($cashTotal) ?></h4>
            <span class="small text-white-50">Direct cash register</span>
        </div>
    </div>
    <div class="col-12 col-sm-6 col-md-3">
        <div class="card border-0 text-white stat-card p-3" style="background: #0ea5e9;">
            <span class="text-white-50 small fw-semibold">QR / CARD SALES</span>
            <h4 class="fw-bold mt-1 mb-0"><?= format_currency($cardTotal + $qrTotal) ?></h4>
            <span class="small text-white-50">Digital & contactless</span>
        </div>
    </div>
    <div class="col-12 col-sm-6 col-md-3">
        <div class="card border-0 bg-dark text-white stat-card p-3">
            <span class="text-white-50 small fw-semibold">AVG TICKET VALUE</span>
            <h4 class="fw-bold mt-1 mb-0"><?= format_currency($avgOrderValue) ?></h4>
            <span class="small text-white-50">Per completed order</span>
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
                        <th>Date & Time</th>
                        <th>Cashier</th>
                        <th>Payment Method</th>
                        <th class="text-end">Subtotal</th>
                        <th class="text-end">Discount</th>
                        <th class="text-end">Grand Total</th>
                        <th class="text-center" style="width: 120px;">Receipt</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($orders)): ?>
                        <tr>
                            <td colspan="8" class="text-center py-5 text-muted">
                                <i class="bi bi-inbox fs-1 d-block mb-2 text-secondary"></i>
                                No orders found within the selected criteria.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($orders as $o): ?>
                            <tr>
                                <td>
                                    <span class="font-monospace fw-bold text-primary"><?= clean($o['invoice_no']) ?></span>
                                </td>
                                <td><small class="text-dark"><?= format_date($o['created_at']) ?></small></td>
                                <td><span class="small text-muted"><?= clean($o['cashier_name'] ?? 'System') ?></span></td>
                                <td>
                                    <?php
                                    $badgeClass = match($o['payment_method']) {
                                        'cash' => 'bg-success-subtle text-success border border-success-subtle',
                                        'card' => 'bg-primary-subtle text-primary border border-primary-subtle',
                                        'qr'   => 'bg-info-subtle text-info-emphasis border border-info-subtle',
                                        default=> 'bg-secondary-subtle text-secondary'
                                    };
                                    ?>
                                    <span class="badge <?= $badgeClass ?> text-uppercase"><?= clean($o['payment_method']) ?></span>
                                </td>
                                <td class="text-end text-muted small"><?= format_currency($o['subtotal']) ?></td>
                                <td class="text-end text-danger small"><?= (float)$o['discount'] > 0 ? '-' . format_currency($o['discount']) : '-' ?></td>
                                <td class="text-end fw-bold text-dark fs-6"><?= format_currency($o['grand_total']) ?></td>
                                <td class="text-center">
                                    <a href="<?= base_url('receipt_view.php?invoice=' . urlencode($o['invoice_no'])) ?>" target="_blank" class="btn btn-sm btn-outline-secondary py-1 px-2" title="Print Thermal Receipt">
                                        <i class="bi bi-printer me-1"></i> Print
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
