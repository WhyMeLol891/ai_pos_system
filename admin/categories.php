<?php
/**
 * Category Management
 * AI Camera POS System
 */

$pageTitle = 'Categories';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/auth.php';

require_admin();

$pdo = get_db_connection();

// Handle Actions (Add, Edit, Delete)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        set_flash('danger', 'Invalid security token.');
        header('Location: ' . base_url('admin/categories.php'));
        exit;
    }

    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $name = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');

        if (empty($name)) {
            set_flash('danger', 'Category name cannot be empty.');
        } else {
            try {
                $stmt = $pdo->prepare("INSERT INTO categories (name, description) VALUES (:name, :desc)");
                $stmt->execute(['name' => $name, 'desc' => $description]);
                log_audit('CATEGORY_CREATE', "Created category {$name}");
                set_flash('success', "Category '{$name}' created successfully.");
            } catch (Exception $e) {
                set_flash('danger', 'Error: A category with this name may already exist.');
            }
        }
    } elseif ($action === 'update') {
        $id = (int)($_POST['category_id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');

        if (empty($name) || $id <= 0) {
            set_flash('danger', 'Category name cannot be empty.');
        } else {
            try {
                $stmt = $pdo->prepare("UPDATE categories SET name = :name, description = :desc WHERE id = :id");
                $stmt->execute(['name' => $name, 'desc' => $description, 'id' => $id]);
                log_audit('CATEGORY_UPDATE', "Updated category ID {$id} ({$name})");
                set_flash('success', "Category updated successfully.");
            } catch (Exception $e) {
                set_flash('danger', 'Error updating category: Name may already exist.');
            }
        }
    } elseif ($action === 'delete') {
        $id = (int)($_POST['category_id'] ?? 0);
        try {
            // Count products
            $count = $pdo->prepare("SELECT COUNT(*) FROM products WHERE category_id = :id");
            $count->execute(['id' => $id]);
            $prodCount = $count->fetchColumn();

            // Nullify category_id on products before deleting
            $pdo->prepare("UPDATE products SET category_id = NULL WHERE category_id = :id")->execute(['id' => $id]);
            
            $stmt = $pdo->prepare("DELETE FROM categories WHERE id = :id");
            $stmt->execute(['id' => $id]);
            log_audit('CATEGORY_DELETE', "Deleted category ID {$id}");
            set_flash('success', "Category deleted. ({$prodCount} items unassigned).");
        } catch (Exception $e) {
            set_flash('danger', 'Error deleting category: ' . $e->getMessage());
        }
    }

    header('Location: ' . base_url('admin/categories.php'));
    exit;
}

// Fetch all categories with product count
$sql = "
    SELECT c.*, COUNT(p.id) AS product_count 
    FROM categories c 
    LEFT JOIN products p ON c.id = p.category_id 
    GROUP BY c.id 
    ORDER BY c.name ASC
";
$categories = $pdo->query($sql)->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-3">
    <div>
        <h3 class="fw-bold mb-1"><i class="bi bi-tags text-primary me-2"></i>Product Categories</h3>
        <p class="text-muted mb-0">Organize store products for faster checkout and POS filtering.</p>
    </div>
    <button type="button" class="btn btn-primary fw-semibold px-3 shadow-sm" data-bs-toggle="modal" data-bs-target="#addCategoryModal">
        <i class="bi bi-plus-circle-fill me-1"></i> Add Category
    </button>
</div>

<div class="row">
    <div class="col-12 col-lg-9">
        <div class="card border-0 shadow-sm">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light small">
                            <tr>
                                <th style="width: 60px;">#</th>
                                <th>Category Name</th>
                                <th>Description</th>
                                <th class="text-center">Products Count</th>
                                <th class="text-end" style="width: 140px;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($categories)): ?>
                                <tr>
                                    <td colspan="5" class="text-center py-4 text-muted">No categories created yet.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($categories as $index => $c): ?>
                                    <tr>
                                        <td class="text-muted small"><?= $index + 1 ?></td>
                                        <td><span class="fw-bold text-dark"><?= clean($c['name']) ?></span></td>
                                        <td><span class="text-muted small"><?= clean($c['description'] ?: '-') ?></span></td>
                                        <td class="text-center">
                                            <a href="<?= base_url('admin/products.php?category=' . $c['id']) ?>" class="badge bg-primary-subtle text-primary border border-primary-subtle text-decoration-none">
                                                <?= $c['product_count'] ?> items
                                            </a>
                                        </td>
                                        <td class="text-end">
                                            <button type="button" class="btn btn-sm btn-outline-primary" onclick="openEditModal(<?= htmlspecialchars(json_encode($c)) ?>)">
                                                <i class="bi bi-pencil"></i>
                                            </button>
                                            <button type="button" class="btn btn-sm btn-outline-danger" onclick="confirmDelete(<?= $c['id'] ?>, '<?= addslashes(clean($c['name'])) ?>', <?= $c['product_count'] ?>)">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Add Category Modal -->
<div class="modal fade" id="addCategoryModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form method="POST" action="<?= base_url('admin/categories.php') ?>" class="modal-content">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="create">
            <div class="modal-header">
                <h5 class="modal-title fw-bold"><i class="bi bi-plus-circle me-2 text-primary"></i>Add New Category</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label fw-semibold">Category Name <span class="text-danger">*</span></label>
                    <input type="text" name="name" class="form-control" required placeholder="e.g. Beverages, Snacks">
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Description</label>
                    <textarea name="description" class="form-control" rows="3" placeholder="Brief description (optional)"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary fw-semibold">Create Category</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Category Modal -->
<div class="modal fade" id="editCategoryModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form method="POST" action="<?= base_url('admin/categories.php') ?>" class="modal-content">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="category_id" id="editCategoryId">
            <div class="modal-header">
                <h5 class="modal-title fw-bold"><i class="bi bi-pencil-square me-2 text-primary"></i>Edit Category</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label fw-semibold">Category Name <span class="text-danger">*</span></label>
                    <input type="text" name="name" id="editCategoryName" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Description</label>
                    <textarea name="description" id="editCategoryDesc" class="form-control" rows="3"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary fw-semibold">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteCategoryModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form method="POST" action="<?= base_url('admin/categories.php') ?>" class="modal-content">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="category_id" id="deleteCatId">
            <div class="modal-header">
                <h5 class="modal-title fw-bold text-danger"><i class="bi bi-trash me-2"></i>Delete Category</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete category <strong id="deleteCatName"></strong>?</p>
                <div class="alert alert-info small mb-0">
                    <i class="bi bi-info-circle me-1"></i> Products in this category (<span id="deleteCatCount">0</span>) will remain in inventory as unassigned.
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-danger fw-semibold">Delete</button>
            </div>
        </form>
    </div>
</div>

<script>
function openEditModal(cat) {
    document.getElementById('editCategoryId').value = cat.id;
    document.getElementById('editCategoryName').value = cat.name;
    document.getElementById('editCategoryDesc').value = cat.description || '';
    new bootstrap.Modal(document.getElementById('editCategoryModal')).show();
}

function confirmDelete(id, name, count) {
    document.getElementById('deleteCatId').value = id;
    document.getElementById('deleteCatName').textContent = name;
    document.getElementById('deleteCatCount').textContent = count;
    new bootstrap.Modal(document.getElementById('deleteCategoryModal')).show();
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
