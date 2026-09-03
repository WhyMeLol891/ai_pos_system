<?php
/**
 * Product Management
 * AI Camera POS System
 */

$pageTitle = 'Products Catalog';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/auth.php';

require_admin();

$pdo = get_db_connection();

// Handle Delete Request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        set_flash('danger', 'Invalid security token.');
    } else {
        $prodId = (int) ($_POST['product_id'] ?? 0);
        
        // Check if product is in order_items
        $stmtCheck = $pdo->prepare("SELECT COUNT(*) FROM order_items WHERE product_id = :id");
        $stmtCheck->execute(['id' => $prodId]);
        $hasOrders = $stmtCheck->fetchColumn() > 0;

        if ($hasOrders) {
            // Soft delete by setting status to inactive so historical order records aren't broken
            $stmtDel = $pdo->prepare("UPDATE products SET status = 'inactive' WHERE id = :id");
            $stmtDel->execute(['id' => $prodId]);
            log_audit('PRODUCT_DEACTIVATE', "Product ID {$prodId} deactivated due to order history");
            set_flash('info', 'Product has associated sales history and has been archived (marked Inactive).');
        } else {
            $stmtDel = $pdo->prepare("DELETE FROM products WHERE id = :id");
            $stmtDel->execute(['id' => $prodId]);
            log_audit('PRODUCT_DELETE', "Product ID {$prodId} permanently deleted");
            set_flash('success', 'Product deleted successfully.');
        }
        header('Location: ' . base_url('admin/products.php'));
        exit;
    }
}

// Filters & Search
$search = trim($_GET['search'] ?? '');
$categoryFilter = (int) ($_GET['category'] ?? 0);
$statusFilter = trim($_GET['status'] ?? '');

$sql = "
    SELECT p.*, c.name AS category_name 
    FROM products p 
    LEFT JOIN categories c ON p.category_id = c.id 
    WHERE 1=1
";
$params = [];

if ($search !== '') {
    $sql .= " AND (p.name LIKE :search OR p.sku LIKE :search)";
    $params['search'] = "%{$search}%";
}

if ($categoryFilter > 0) {
    $sql .= " AND p.category_id = :cat_id";
    $params['cat_id'] = $categoryFilter;
}

if ($statusFilter !== '') {
    $sql .= " AND p.status = :status";
    $params['status'] = $statusFilter;
}

$sql .= " ORDER BY p.id DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$products = $stmt->fetchAll();

// Get categories for filter dropdown
$categories = $pdo->query("SELECT * FROM categories ORDER BY name ASC")->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-3">
    <div>
        <h3 class="fw-bold mb-1"><i class="bi bi-box-seam text-primary me-2"></i>Product Catalog</h3>
        <p class="text-muted mb-0">Manage shop items, inventory quantities, prices, and AI visual match catalog.</p>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= base_url('admin/product_form.php') ?>" class="btn btn-primary fw-semibold px-3 shadow-sm">
            <i class="bi bi-plus-circle-fill me-1"></i> Add New Product
        </a>
    </div>
</div>

<!-- Search & Filter Card -->
<div class="card mb-4 border-0 shadow-sm">
    <div class="card-body p-3">
        <form method="GET" action="<?= base_url('admin/products.php') ?>" class="row g-2 align-items-center">
            <div class="col-12 col-md-5">
                <div class="input-group">
                    <span class="input-group-text bg-light text-muted"><i class="bi bi-search"></i></span>
                    <input type="text" name="search" class="form-control" placeholder="Search by name or SKU..." value="<?= clean($search) ?>">
                </div>
            </div>
            <div class="col-6 col-md-3">
                <select name="category" class="form-select">
                    <option value="0">All Categories</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?= $cat['id'] ?>" <?= $categoryFilter == $cat['id'] ? 'selected' : '' ?>>
                            <?= clean($cat['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-6 col-md-2">
                <select name="status" class="form-select">
                    <option value="">All Statuses</option>
                    <option value="active" <?= $statusFilter === 'active' ? 'selected' : '' ?>>Active Only</option>
                    <option value="inactive" <?= $statusFilter === 'inactive' ? 'selected' : '' ?>>Inactive Only</option>
                </select>
            </div>
            <div class="col-12 col-md-2 d-flex gap-2">
                <button type="submit" class="btn btn-primary flex-fill">Filter</button>
                <a href="<?= base_url('admin/products.php') ?>" class="btn btn-outline-secondary" title="Reset Filters"><i class="bi bi-arrow-counterclockwise"></i></a>
            </div>
        </form>
    </div>
</div>

<!-- Product Table Card -->
<div class="card border-0 shadow-sm">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light small">
                    <tr>
                        <th style="width: 70px;">Image</th>
                        <th>Product Details</th>
                        <th>Category</th>
                        <th class="text-end">Price</th>
                        <th class="text-center">Stock</th>
                        <th class="text-center">Status</th>
                        <th class="text-end" style="width: 140px;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($products)): ?>
                        <tr>
                            <td colspan="7" class="text-center py-5 text-muted">
                                <i class="bi bi-box-seam fs-1 d-block mb-2 text-secondary"></i>
                                No products found matching your search.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($products as $p): ?>
                            <tr>
                                <td>
                                    <?php 
                                    $imgSrc = !empty($p['image_path']) ? base_url($p['image_path']) : base_url('assets/uploads/products/default_product.svg'); 
                                    ?>
                                    <img src="<?= $imgSrc ?>" alt="<?= clean($p['name']) ?>" width="52" height="52" class="rounded object-fit-contain bg-light border p-1">
                                </td>
                                <td>
                                    <span class="fw-bold text-dark d-block"><?= clean($p['name']) ?></span>
                                    <span class="badge bg-light text-secondary border font-monospace me-1"><?= clean($p['sku']) ?></span>
                                    <small class="text-muted">ID: #<?= $p['id'] ?></small>
                                </td>
                                <td>
                                    <span class="badge bg-secondary-subtle text-secondary"><?= clean($p['category_name'] ?? 'Uncategorized') ?></span>
                                </td>
                                <td class="text-end fw-bold text-dark">
                                    <?= format_currency($p['price']) ?>
                                    <?php if ($p['cost_price'] > 0): ?>
                                        <div class="text-muted small fw-normal">Cost: <?= format_currency($p['cost_price']) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <?php if ($p['stock_quantity'] <= 0): ?>
                                        <span class="badge bg-danger">Out of Stock (0)</span>
                                    <?php elseif ($p['stock_quantity'] <= 5): ?>
                                        <span class="badge bg-warning text-dark border border-warning">Low: <?= $p['stock_quantity'] ?></span>
                                    <?php else: ?>
                                        <span class="badge bg-success-subtle text-success border border-success-subtle fw-semibold"><?= $p['stock_quantity'] ?> units</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <?php if ($p['status'] === 'active'): ?>
                                        <span class="badge bg-success"><i class="bi bi-check-circle me-1"></i>Active</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary"><i class="bi bi-x-circle me-1"></i>Inactive</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end">
                                    <div class="btn-group">
                                        <a href="<?= base_url('admin/product_form.php?id=' . $p['id']) ?>" class="btn btn-sm btn-outline-primary" title="Edit">
                                            <i class="bi bi-pencil-square"></i>
                                        </a>
                                        <button type="button" class="btn btn-sm btn-outline-danger" title="Delete" onclick="confirmDelete(<?= $p['id'] ?>, '<?= addslashes(clean($p['name'])) ?>')">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    <div class="card-footer bg-light py-2 text-muted small">
        Showing <?= count($products) ?> products in inventory
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form method="POST" action="<?= base_url('admin/products.php') ?>" class="modal-content">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="product_id" id="deleteProductId">
            <div class="modal-header">
                <h5 class="modal-title fw-bold text-danger"><i class="bi bi-exclamation-triangle-fill me-2"></i>Confirm Delete</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to remove <strong id="deleteProductName"></strong> from the active product catalog?</p>
                <div class="alert alert-warning small mb-0">
                    <i class="bi bi-info-circle me-1"></i> If this item has existing sales history, it will be safely deactivated (archived) to preserve historical accounting records.
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-danger fw-semibold">Yes, Delete</button>
            </div>
        </form>
    </div>
</div>

<script>
function confirmDelete(id, name) {
    document.getElementById('deleteProductId').value = id;
    document.getElementById('deleteProductName').textContent = name;
    new bootstrap.Modal(document.getElementById('deleteModal')).show();
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
