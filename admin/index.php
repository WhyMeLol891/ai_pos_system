<?php
/**
 * Admin Dashboard
 * AI Camera POS System
 */

$pageTitle = 'Admin Dashboard';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/auth.php';

require_admin();

$pdo = get_db_connection();

// Low stock threshold setting
$threshold = (int) get_setting('low_stock_threshold', 5);

// 1. Total Products
$totalProducts = (int) $pdo->query("SELECT COUNT(*) FROM products WHERE status = 'active'")->fetchColumn();

// 2. Total Stock Units
$totalStock = (int) $pdo->query("SELECT COALESCE(SUM(stock_quantity), 0) FROM products WHERE status = 'active'")->fetchColumn();

// 3. Today's Sales
$todaySales = (float) $pdo->query("
    SELECT COALESCE(SUM(grand_total), 0) 
    FROM orders 
    WHERE DATE(created_at) = CURDATE() AND status = 'completed'
")->fetchColumn();

// 4. Total Orders
$totalOrders = (int) $pdo->query("SELECT COUNT(*) FROM orders WHERE status = 'completed'")->fetchColumn();

// 5. Low-Stock Products
$stmtLowStock = $pdo->prepare("
    SELECT p.*, c.name AS category_name 
    FROM products p 
    LEFT JOIN categories c ON p.category_id = c.id 
    WHERE p.status = 'active' AND p.stock_quantity <= :thresh 
    ORDER BY p.stock_quantity ASC 
    LIMIT 10
");
$stmtLowStock->execute(['thresh' => $threshold]);
$lowStockProducts = $stmtLowStock->fetchAll();

// 6. Recent Orders
$stmtRecent = $pdo->query("
    SELECT o.*, u.full_name AS cashier_name 
    FROM orders o 
    LEFT JOIN users u ON o.cashier_id = u.id 
    ORDER BY o.created_at DESC 
    LIMIT 8
");
$recentOrders = $stmtRecent->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-3">
    <div>
        <h3 class="fw-bold mb-1"><i class="bi bi-speedometer2 text-primary me-2"></i>Dashboard</h3>
        <p class="text-muted mb-0">Overview of inventory, daily sales, and store performance.</p>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= base_url('pos/index.php') ?>" class="btn btn-warning fw-bold px-3 shadow-sm">
            <i class="bi bi-shop me-1"></i> Open POS
        </a>
        <a href="<?= base_url('admin/product_form.php') ?>" class="btn btn-primary fw-semibold px-3 shadow-sm">
            <i class="bi bi-plus-lg me-1"></i> Add Product
        </a>
        <a href="<?= base_url('admin/settings.php') ?>" class="btn btn-outline-secondary px-3">
            <i class="bi bi-gear me-1"></i> AI Settings
        </a>
    </div>
</div>

<!-- Stat Cards -->
<div class="row g-3 mb-4">
    <!-- Today's Sales -->
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="card stat-card bg-primary text-white h-100">
            <div class="card-body d-flex justify-content-between align-items-center p-3">
                <div>
                    <span class="text-white-50 text-uppercase fw-semibold small">Today's Sales</span>
                    <h3 class="fw-bold mt-1 mb-0"><?= format_currency($todaySales) ?></h3>
                    <span class="small text-white-50">Completed transactions today</span>
                </div>
                <div class="stat-icon bg-white bg-opacity-25 text-white">
                    <i class="bi bi-cash-stack"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Total Orders -->
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="card stat-card bg-success text-white h-100">
            <div class="card-body d-flex justify-content-between align-items-center p-3">
                <div>
                    <span class="text-white-50 text-uppercase fw-semibold small">Total Orders</span>
                    <h3 class="fw-bold mt-1 mb-0"><?= number_format($totalOrders) ?></h3>
                    <span class="small text-white-50">All-time completed orders</span>
                </div>
                <div class="stat-icon bg-white bg-opacity-25 text-white">
                    <i class="bi bi-receipt"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Total Products -->
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="card stat-card bg-indigo text-white h-100" style="background: #6366f1;">
            <div class="card-body d-flex justify-content-between align-items-center p-3">
                <div>
                    <span class="text-white-50 text-uppercase fw-semibold small">Active Products</span>
                    <h3 class="fw-bold mt-1 mb-0"><?= number_format($totalProducts) ?></h3>
                    <span class="small text-white-50">Items available in catalog</span>
                </div>
                <div class="stat-icon bg-white bg-opacity-25 text-white">
                    <i class="bi bi-box-seam"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Total Stock Units -->
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="card stat-card bg-info text-dark h-100" style="background: #38bdf8;">
            <div class="card-body d-flex justify-content-between align-items-center p-3">
                <div>
                    <span class="text-dark-50 text-uppercase fw-semibold small text-muted">Total Stock Units</span>
                    <h3 class="fw-bold mt-1 mb-0"><?= number_format($totalStock) ?></h3>
                    <span class="small text-muted">Units in warehouse & shelf</span>
                </div>
                <div class="stat-icon bg-white bg-opacity-50 text-dark">
                    <i class="bi bi-layers"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row g-4">
    <!-- Low Stock Alert Panel -->
    <div class="col-12 col-lg-5">
        <div class="card h-100 shadow-sm border-0">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                <span class="fw-bold text-danger">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i>Low-Stock Alert
                    <span class="badge bg-danger rounded-pill ms-1"><?= count($lowStockProducts) ?></span>
                </span>
                <span class="text-muted small">Threshold: &le; <?= $threshold ?> units</span>
            </div>
            <div class="card-body p-0">
                <?php if (empty($lowStockProducts)): ?>
                    <div class="text-center py-4 text-muted">
                        <i class="bi bi-check-circle-fill text-success fs-1 mb-2 d-block"></i>
                        <p class="mb-0">All items are sufficiently stocked!</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light small">
                                <tr>
                                    <th>Product</th>
                                    <th>SKU</th>
                                    <th class="text-center">Stock</th>
                                    <th class="text-end">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($lowStockProducts as $lp): ?>
                                    <tr>
                                        <td>
                                            <div class="d-flex align-items-center gap-2">
                                                <?php $img = $lp['image_path'] ? base_url($lp['image_path']) : base_url('assets/uploads/products/default_product.svg'); ?>
                                                <img src="<?= $img ?>" alt="" width="36" height="36" class="rounded object-fit-contain bg-light border">
                                                <div>
                                                    <span class="fw-semibold text-dark d-block text-truncate" style="max-width: 140px;"><?= clean($lp['name']) ?></span>
                                                    <small class="text-muted"><?= clean($lp['category_name'] ?? 'General') ?></small>
                                                </div>
                                            </div>
                                        </td>
                                        <td><span class="badge bg-light text-secondary border font-monospace"><?= clean($lp['sku']) ?></span></td>
                                        <td class="text-center">
                                            <span class="badge bg-danger-subtle text-danger border border-danger-subtle fw-bold fs-6">
                                                <?= $lp['stock_quantity'] ?>
                                            </span>
                                        </td>
                                        <td class="text-end">
                                            <a href="<?= base_url('admin/product_form.php?id=' . $lp['id']) ?>" class="btn btn-sm btn-outline-primary py-1 px-2" title="Edit & Restock">
                                                <i class="bi bi-pencil"></i>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
            <div class="card-footer bg-light text-center py-2">
                <a href="<?= base_url('admin/products.php') ?>" class="small text-decoration-none fw-semibold">View All Products &rarr;</a>
            </div>
        </div>
    </div>

    <!-- Recent Orders Table -->
    <div class="col-12 col-lg-7">
        <div class="card h-100 shadow-sm border-0">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                <span class="fw-bold text-dark">
                    <i class="bi bi-clock-history text-primary me-2"></i>Recent Transactions
                </span>
                <a href="<?= base_url('admin/sales.php') ?>" class="small text-decoration-none fw-semibold">View Full History &rarr;</a>
            </div>
            <div class="card-body p-0">
                <?php if (empty($recentOrders)): ?>
                    <div class="text-center py-4 text-muted">
                        <i class="bi bi-inbox fs-1 mb-2 d-block"></i>
                        <p class="mb-0">No sales recorded yet. Open POS to make your first transaction!</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light small">
                                <tr>
                                    <th>Invoice #</th>
                                    <th>Cashier</th>
                                    <th>Method</th>
                                    <th class="text-end">Total</th>
                                    <th class="text-end">Date & Time</th>
                                    <th class="text-center">Receipt</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recentOrders as $ro): ?>
                                    <tr>
                                        <td>
                                            <span class="font-monospace fw-bold text-primary"><?= clean($ro['invoice_no']) ?></span>
                                        </td>
                                        <td><small class="text-muted"><?= clean($ro['cashier_name'] ?? 'System') ?></small></td>
                                        <td>
                                            <?php
                                            $methodBadge = match($ro['payment_method']) {
                                                'cash' => 'bg-success-subtle text-success border border-success-subtle',
                                                'card' => 'bg-primary-subtle text-primary border border-primary-subtle',
                                                'qr'   => 'bg-info-subtle text-info-emphasis border border-info-subtle',
                                                default=> 'bg-secondary-subtle text-secondary'
                                            };
                                            ?>
                                            <span class="badge <?= $methodBadge ?> text-uppercase"><?= clean($ro['payment_method']) ?></span>
                                        </td>
                                        <td class="text-end fw-bold"><?= format_currency($ro['grand_total']) ?></td>
                                        <td class="text-end text-muted small"><?= format_date($ro['created_at'], 'd M, h:i A') ?></td>
                                        <td class="text-center">
                                            <a href="<?= base_url('receipt_view.php?invoice=' . urlencode($ro['invoice_no'])) ?>" target="_blank" class="btn btn-sm btn-light border py-1 px-2" title="View Receipt">
                                                <i class="bi bi-printer"></i>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
            <div class="card-footer bg-light text-center py-2">
                <a href="<?= base_url('admin/sales.php') ?>" class="small text-decoration-none fw-semibold">View Sales History & Financial Reports &rarr;</a>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
