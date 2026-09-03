<?php
/**
 * Add / Edit Product Form
 * AI Camera POS System
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/auth.php';

require_admin();

$pdo = get_db_connection();
$id = (int) ($_GET['id'] ?? 0);
$isEdit = ($id > 0);
$pageTitle = $isEdit ? 'Edit Product' : 'Add New Product';

$product = [
    'id'             => 0,
    'category_id'    => '',
    'name'           => '',
    'sku'            => '',
    'price'          => '0.00',
    'cost_price'     => '0.00',
    'stock_quantity' => 0,
    'image_path'     => '',
    'status'         => 'active',
];

if ($isEdit) {
    $stmt = $pdo->prepare("SELECT * FROM products WHERE id = :id");
    $stmt->execute(['id' => $id]);
    $existing = $stmt->fetch();
    if (!$existing) {
        set_flash('danger', 'Product not found.');
        header('Location: ' . base_url('admin/products.php'));
        exit;
    }
    $product = $existing;
}

$errors = [];

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid security token. Please resubmit.';
    } else {
        $name = trim($_POST['name'] ?? '');
        $sku = trim(strtoupper($_POST['sku'] ?? ''));
        $categoryId = !empty($_POST['category_id']) ? (int)$_POST['category_id'] : null;
        $price = (float) ($_POST['price'] ?? 0);
        $costPrice = (float) ($_POST['cost_price'] ?? 0);
        $stock = (int) ($_POST['stock_quantity'] ?? 0);
        $status = in_array($_POST['status'] ?? '', ['active', 'inactive']) ? $_POST['status'] : 'active';

        // Validations
        if (empty($name)) {
            $errors[] = 'Product name is required.';
        }
        if (empty($sku)) {
            $errors[] = 'SKU / Barcode is required.';
        } else {
            // Check uniqueness
            $stmtSku = $pdo->prepare("SELECT id FROM products WHERE sku = :sku AND id != :id LIMIT 1");
            $stmtSku->execute(['sku' => $sku, 'id' => $id]);
            if ($stmtSku->fetch()) {
                $errors[] = "SKU '{$sku}' is already assigned to another product.";
            }
        }
        if ($price < 0) {
            $errors[] = 'Price cannot be negative.';
        }
        if ($stock < 0) {
            $errors[] = 'Stock quantity cannot be negative.';
        }

        // Image Upload Handling
        $imagePath = $product['image_path'];
        if (!empty($_FILES['product_image']['name'])) {
            $file = $_FILES['product_image'];
            $allowedExts = ['jpg', 'jpeg', 'png', 'webp', 'svg'];
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

            if (!in_array($ext, $allowedExts)) {
                $errors[] = 'Invalid image format. Allowed formats: JPG, PNG, WEBP, SVG.';
            } elseif ($file['size'] > 5 * 1024 * 1024) {
                $errors[] = 'Image file size exceeds 5MB limit.';
            } elseif ($file['error'] !== UPLOAD_ERR_OK) {
                $errors[] = 'Error uploading image file.';
            } else {
                $uploadDir = __DIR__ . '/../assets/uploads/products/';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0777, true);
                }
                $safeName = preg_replace('/[^a-z0-9_-]/i', '_', strtolower($sku)) . '_' . time() . '.' . $ext;
                $targetFile = $uploadDir . $safeName;

                if (move_uploaded_file($file['tmp_name'], $targetFile)) {
                    $imagePath = 'assets/uploads/products/' . $safeName;
                } else {
                    $errors[] = 'Failed to save uploaded image.';
                }
            }
        }

        // Save to Database if no validation errors
        if (empty($errors)) {
            try {
                if ($isEdit) {
                    $stmtUpdate = $pdo->prepare("
                        UPDATE products SET 
                            name = :name,
                            sku = :sku,
                            category_id = :cat_id,
                            price = :price,
                            cost_price = :cost,
                            stock_quantity = :stock,
                            image_path = :img,
                            status = :status
                        WHERE id = :id
                    ");
                    $stmtUpdate->execute([
                        'name'    => $name,
                        'sku'     => $sku,
                        'cat_id'  => $categoryId,
                        'price'   => $price,
                        'cost'    => $costPrice,
                        'stock'   => $stock,
                        'img'     => $imagePath,
                        'status'  => $status,
                        'id'      => $id
                    ]);
                    log_audit('PRODUCT_UPDATE', "Updated product #{$id} ({$name})");
                    set_flash('success', "Product '{$name}' updated successfully!");
                } else {
                    $stmtInsert = $pdo->prepare("
                        INSERT INTO products (name, sku, category_id, price, cost_price, stock_quantity, image_path, status)
                        VALUES (:name, :sku, :cat_id, :price, :cost, :stock, :img, :status)
                    ");
                    $stmtInsert->execute([
                        'name'    => $name,
                        'sku'     => $sku,
                        'cat_id'  => $categoryId,
                        'price'   => $price,
                        'cost'    => $costPrice,
                        'stock'   => $stock,
                        'img'     => $imagePath,
                        'status'  => $status
                    ]);
                    $newId = $pdo->lastInsertId();
                    log_audit('PRODUCT_CREATE', "Created product #{$newId} ({$name})");
                    set_flash('success', "Product '{$name}' created successfully!");
                }

                header('Location: ' . base_url('admin/products.php'));
                exit;
            } catch (Exception $e) {
                $errors[] = 'Database error: ' . $e->getMessage();
            }
        }

        // Update local object for redisplaying values on error
        $product['name'] = $name;
        $product['sku'] = $sku;
        $product['category_id'] = $categoryId;
        $product['price'] = $price;
        $product['cost_price'] = $costPrice;
        $product['stock_quantity'] = $stock;
        $product['status'] = $status;
        $product['image_path'] = $imagePath;
    }
}

// Fetch categories
$categories = $pdo->query("SELECT * FROM categories ORDER BY name ASC")->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<div class="row justify-content-center">
    <div class="col-12 col-lg-9">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h3 class="fw-bold mb-0">
                <i class="bi <?= $isEdit ? 'bi-pencil-square' : 'bi-plus-circle' ?> text-primary me-2"></i>
                <?= $pageTitle ?>
            </h3>
            <a href="<?= base_url('admin/products.php') ?>" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left me-1"></i> Back to Products
            </a>
        </div>

        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger shadow-sm">
                <h6 class="fw-bold mb-2"><i class="bi bi-exclamation-triangle-fill me-2"></i>Please fix the following errors:</h6>
                <ul class="mb-0 ps-3">
                    <?php foreach ($errors as $err): ?>
                        <li><?= clean($err) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <form method="POST" action="<?= base_url('admin/product_form.php' . ($isEdit ? '?id=' . $id : '')) ?>" enctype="multipart/form-data" class="card shadow-sm border-0">
            <?= csrf_field() ?>
            <div class="card-body p-4">
                <div class="row g-3">
                    <!-- Product Name -->
                    <div class="col-12 col-md-8">
                        <label for="name" class="form-label fw-semibold">Product Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="name" name="name" value="<?= clean($product['name']) ?>" required placeholder="e.g. Coca-Cola Can 320ml">
                        <div class="form-text small">This name will be matched by the AI camera when cashier scans products.</div>
                    </div>

                    <!-- SKU / Barcode -->
                    <div class="col-12 col-md-4">
                        <label for="sku" class="form-label fw-semibold">SKU / Barcode <span class="text-danger">*</span></label>
                        <input type="text" class="form-control font-monospace" id="sku" name="sku" value="<?= clean($product['sku']) ?>" required placeholder="e.g. BEV-001">
                        <div class="form-text small">Unique stock identifier code.</div>
                    </div>

                    <!-- Category -->
                    <div class="col-12 col-md-6">
                        <label for="category_id" class="form-label fw-semibold">Category</label>
                        <select class="form-select" id="category_id" name="category_id">
                            <option value="">-- Select Category --</option>
                            <?php foreach ($categories as $c): ?>
                                <option value="<?= $c['id'] ?>" <?= $product['category_id'] == $c['id'] ? 'selected' : '' ?>>
                                    <?= clean($c['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Status -->
                    <div class="col-12 col-md-6">
                        <label for="status" class="form-label fw-semibold">Status</label>
                        <select class="form-select" id="status" name="status">
                            <option value="active" <?= $product['status'] === 'active' ? 'selected' : '' ?>>Active (Available in POS)</option>
                            <option value="inactive" <?= $product['status'] === 'inactive' ? 'selected' : '' ?>>Inactive (Hidden)</option>
                        </select>
                    </div>

                    <!-- Selling Price -->
                    <div class="col-12 col-md-4">
                        <label for="price" class="form-label fw-semibold">Selling Price (<?= get_setting('currency_symbol', 'RM') ?>) <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text"><?= get_setting('currency_symbol', 'RM') ?></span>
                            <input type="number" step="0.01" min="0" class="form-control" id="price" name="price" value="<?= clean($product['price']) ?>" required>
                        </div>
                    </div>

                    <!-- Cost Price -->
                    <div class="col-12 col-md-4">
                        <label for="cost_price" class="form-label fw-semibold">Cost Price (<?= get_setting('currency_symbol', 'RM') ?>)</label>
                        <div class="input-group">
                            <span class="input-group-text"><?= get_setting('currency_symbol', 'RM') ?></span>
                            <input type="number" step="0.01" min="0" class="form-control" id="cost_price" name="cost_price" value="<?= clean($product['cost_price']) ?>">
                        </div>
                    </div>

                    <!-- Stock Quantity -->
                    <div class="col-12 col-md-4">
                        <label for="stock_quantity" class="form-label fw-semibold">Stock Quantity <span class="text-danger">*</span></label>
                        <input type="number" min="0" class="form-control" id="stock_quantity" name="stock_quantity" value="<?= clean($product['stock_quantity']) ?>" required>
                        <div class="form-text small">Units available in inventory.</div>
                    </div>

                    <!-- Product Image -->
                    <div class="col-12">
                        <label for="product_image" class="form-label fw-semibold">Product Image</label>
                        <div class="d-flex align-items-center gap-3">
                            <div class="border rounded p-1 bg-light text-center" style="width: 80px; height: 80px; flex-shrink: 0;">
                                <?php 
                                $preview = !empty($product['image_path']) ? base_url($product['image_path']) : base_url('assets/uploads/products/default_product.svg');
                                ?>
                                <img src="<?= $preview ?>" id="imagePreview" alt="Preview" class="w-100 h-100 object-fit-contain rounded">
                            </div>
                            <div class="flex-grow-1">
                                <input class="form-control" type="file" id="product_image" name="product_image" accept="image/*" onchange="previewFile(this)">
                                <div class="form-text small">Accepted: JPG, PNG, WEBP, SVG (Max 5MB). Used for POS buttons and catalog visualization.</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card-footer bg-light p-3 d-flex justify-content-end gap-2">
                <a href="<?= base_url('admin/products.php') ?>" class="btn btn-light border">Cancel</a>
                <button type="submit" class="btn btn-primary px-4 fw-semibold">
                    <i class="bi bi-save me-1"></i> <?= $isEdit ? 'Update Product' : 'Save Product' ?>
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function previewFile(input) {
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = function(e) {
            document.getElementById('imagePreview').src = e.target.result;
        };
        reader.readAsDataURL(input.files[0]);
    }
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
