<?php
/**
 * System Settings & Gemini AI Configuration
 * AI Camera POS System
 */

$pageTitle = 'Settings & AI Configuration';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/auth.php';

require_admin();

// Handle Settings Update POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        set_flash('danger', 'Invalid security token.');
    } else {
        $shopName = trim($_POST['shop_name'] ?? '');
        $shopAddress = trim($_POST['shop_address'] ?? '');
        $shopPhone = trim($_POST['shop_phone'] ?? '');
        $currencySymbol = trim($_POST['currency_symbol'] ?? 'RM');
        $receiptFooter = trim($_POST['receipt_footer'] ?? '');
        $lowStockThreshold = max(1, (int)($_POST['low_stock_threshold'] ?? 5));
        
        $geminiApiKey = trim($_POST['gemini_api_key'] ?? '');
        $geminiModel = trim($_POST['gemini_model'] ?? 'gemini-3.6-flash');
        $customModel = trim($_POST['custom_model'] ?? '');
        if ($geminiModel === 'custom' && !empty($customModel)) {
            $geminiModel = $customModel;
        }

        update_setting('shop_name', $shopName);
        update_setting('shop_address', $shopAddress);
        update_setting('shop_phone', $shopPhone);
        update_setting('currency_symbol', $currencySymbol);
        update_setting('receipt_footer', $receiptFooter);
        update_setting('low_stock_threshold', (string)$lowStockThreshold);
        update_setting('gemini_api_key', $geminiApiKey);
        update_setting('gemini_model', $geminiModel);

        log_audit('SETTINGS_UPDATE', "Updated store & Gemini settings");
        set_flash('success', 'System settings & Gemini AI configuration updated successfully!');

        header('Location: ' . base_url('admin/settings.php'));
        exit;
    }
}

// Current values
$shopName = get_setting('shop_name', 'AI SMART MART');
$shopAddress = get_setting('shop_address', '123 Tech Avenue, Digital Mall, 50000 Kuala Lumpur');
$shopPhone = get_setting('shop_phone', '+60 12-345 6789');
$currencySymbol = get_setting('currency_symbol', 'RM');
$receiptFooter = get_setting('receipt_footer', 'Thank you for shopping with us! Please come again.');
$lowStockThreshold = get_setting('low_stock_threshold', '5');
$geminiApiKey = get_setting('gemini_api_key', '');
$geminiModel = get_setting('gemini_model', 'gemini-3.6-flash');

require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-3">
    <div>
        <h3 class="fw-bold mb-1"><i class="bi bi-gear text-primary me-2"></i>System Settings & AI Configuration</h3>
        <p class="text-muted mb-0">Configure shop branding, thermal receipt info, and Google Gemini Vision model parameters.</p>
    </div>
</div>

<div class="row g-4">
    <!-- Gemini AI Configuration Card -->
    <div class="col-12 col-lg-7">
        <form method="POST" action="<?= base_url('admin/settings.php') ?>" class="card border-0 shadow-sm mb-4">
            <?= csrf_field() ?>
            <div class="card-header bg-white py-3 border-bottom d-flex align-items-center gap-2">
                <span class="badge bg-purple-subtle text-purple p-2 rounded-3" style="background: #f3e8ff; color: #7c3aed;">
                    <i class="bi bi-cpu-fill fs-5"></i>
                </span>
                <div>
                    <h5 class="fw-bold mb-0 text-dark">Google Gemini AI Camera Engine</h5>
                    <small class="text-muted">Powers multi-product visual recognition and automatic cart matching</small>
                </div>
            </div>
            
            <div class="card-body p-4">
                <!-- Gemini Model Selection (Requirement 5) -->
                <div class="mb-4">
                    <label for="gemini_model" class="form-label fw-semibold">
                        <i class="bi bi-robot me-1 text-primary"></i> Selected AI Model <span class="text-danger">*</span>
                    </label>
                    <select class="form-select form-select-lg" id="gemini_model" name="gemini_model" onchange="toggleCustomModel(this.value)">
                        <option value="gemini-3.6-flash" <?= $geminiModel === 'gemini-3.6-flash' ? 'selected' : '' ?>>
                            gemini-3.6-flash (Latest, Recommended for fast vision)
                        </option>
                        <option value="custom" <?= $geminiModel !== 'gemini-3.6-flash' ? 'selected' : '' ?>>
                            Custom Model Name...
                        </option>
                    </select>
                    <div class="form-text">Choose the Gemini model to process cashier product camera captures.</div>
                </div>

                <!-- Custom model input if needed -->
                <div class="mb-4 <?= $geminiModel !== 'gemini-3.6-flash' ? '' : 'd-none' ?>" id="customModelGroup">
                    <label for="custom_model" class="form-label fw-semibold">Custom Model Identifier</label>
                    <input type="text" class="form-control font-monospace" id="custom_model" name="custom_model" value="<?= clean($geminiModel) ?>" placeholder="e.g. gemini-1.5-flash">
                </div>

                <!-- Gemini API Key -->
                <div class="mb-4">
                    <label for="gemini_api_key" class="form-label fw-semibold">
                        <i class="bi bi-key-fill me-1 text-primary"></i> Gemini API Key <span class="text-danger">*</span>
                    </label>
                    <div class="input-group">
                        <input type="password" class="form-control font-monospace" id="gemini_api_key" name="gemini_api_key" value="<?= clean($geminiApiKey) ?>" placeholder="AIzaSy..." autocomplete="off">
                        <button class="btn btn-outline-secondary" type="button" onclick="toggleApiKeyVisibility()">
                            <i class="bi bi-eye" id="toggleApiKeyIcon"></i>
                        </button>
                    </div>
                    <div class="form-text">
                        Obtain a free API key from <a href="https://aistudio.google.com/app/apikey" target="_blank" class="text-decoration-none fw-semibold">Google AI Studio <i class="bi bi-box-arrow-up-right"></i></a>.
                    </div>
                </div>

                <!-- Test AI Connection Button (Requirement 5) -->
                <div class="p-3 bg-light rounded-3 border mb-4">
                    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
                        <div>
                            <strong class="d-block text-dark"><i class="bi bi-broadcast me-1 text-primary"></i> Test AI Connection</strong>
                            <small class="text-muted">Sends a test prompt to Google Gemini to verify key & model response.</small>
                        </div>
                        <button type="button" class="btn btn-outline-primary fw-semibold px-3" id="btnTestAi" onclick="testGeminiConnection()">
                            <span class="spinner-border spinner-border-sm me-1 d-none" id="testAiSpinner" role="status"></span>
                            <i class="bi bi-plug-fill me-1" id="testAiIcon"></i> Test Connection
                        </button>
                    </div>
                    <div id="testAiResult" class="mt-3 d-none"></div>
                </div>

                <div class="border-top pt-4">
                    <h5 class="fw-bold text-dark mb-3"><i class="bi bi-shop me-2 text-primary"></i>Store Information & Receipt Setup</h5>
                    
                    <div class="row g-3">
                        <div class="col-12 col-md-8">
                            <label class="form-label fw-semibold">Store / Business Name <span class="text-danger">*</span></label>
                            <input type="text" name="shop_name" class="form-control" value="<?= clean($shopName) ?>" required>
                        </div>
                        <div class="col-12 col-md-4">
                            <label class="form-label fw-semibold">Currency Symbol <span class="text-danger">*</span></label>
                            <input type="text" name="currency_symbol" class="form-control font-monospace" value="<?= clean($currencySymbol) ?>" required placeholder="e.g. RM, $, €">
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold">Store Address (Printed on Receipt)</label>
                            <input type="text" name="shop_address" class="form-control" value="<?= clean($shopAddress) ?>">
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label fw-semibold">Phone / Contact Number</label>
                            <input type="text" name="shop_phone" class="form-control" value="<?= clean($shopPhone) ?>">
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label fw-semibold">Low Stock Threshold (Alert level)</label>
                            <input type="number" min="1" name="low_stock_threshold" class="form-control" value="<?= clean($lowStockThreshold) ?>" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold">Receipt Footer Message</label>
                            <textarea name="receipt_footer" class="form-control" rows="2"><?= clean($receiptFooter) ?></textarea>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card-footer bg-light p-3 d-flex justify-content-end">
                <button type="submit" class="btn btn-primary px-4 fw-semibold shadow-sm">
                    <i class="bi bi-check-lg me-1"></i> Save All Settings
                </button>
            </div>
        </form>
    </div>

    <!-- AI Guide & Camera Tips Column -->
    <div class="col-12 col-lg-5">
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white py-3">
                <h5 class="fw-bold mb-0 text-dark"><i class="bi bi-lightbulb-fill text-warning me-2"></i>How AI Camera Scan Works</h5>
            </div>
            <div class="card-body">
                <ol class="ps-3 mb-3 text-secondary small" style="line-height: 1.7;">
                    <li><strong>Cashier opens Camera</strong> on the POS screen (works with laptop webcam or smartphone camera).</li>
                    <li>Cashier snaps a photo containing one or multiple products (e.g. 2 cans of Coke and 1 packet of bread).</li>
                    <li>The photo is sent to the configured <strong>Google Gemini Vision API</strong> along with your active MySQL catalog index.</li>
                    <li>Gemini counts and identifies the items:
                        <div class="p-2 my-2 bg-light border rounded font-monospace small text-dark">
                            Product A - Quantity 2<br>
                            Product B - Quantity 1
                        </div>
                    </li>
                    <li><strong>Strict MySQL Price & Catalog Protection</strong>: The system queries your local MySQL database. All official prices, SKUs, and stock quantities come <em>only</em> from your database! The AI cannot alter prices or hallucinate items.</li>
                    <li>If an item is unrecognized, the POS marks it as <code>Product not found</code> and lets the cashier manually select or scan it.</li>
                </ol>

                <div class="alert alert-info small mb-0">
                    <i class="bi bi-shield-check me-1"></i> <strong>Offline Fallback:</strong> If no API key is set or the internet connection is interrupted, the cashier can seamlessly use the POS search bar and one-touch product buttons.
                </div>
            </div>
        </div>

        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white py-3">
                <h5 class="fw-bold mb-0 text-dark"><i class="bi bi-info-circle-fill text-primary me-2"></i>System Information</h5>
            </div>
            <div class="card-body p-0">
                <ul class="list-group list-group-flush small">
                    <li class="list-group-item d-flex justify-content-between">
                        <span class="text-muted">PHP Version</span>
                        <span class="fw-semibold font-monospace"><?= PHP_VERSION ?></span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between">
                        <span class="text-muted">Database Engine</span>
                        <span class="fw-semibold">MySQL / PDO utf8mb4</span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between">
                        <span class="text-muted">Server Time</span>
                        <span class="fw-semibold"><?= date('Y-m-d H:i:s T') ?></span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between">
                        <span class="text-muted">Image Uploads Directory</span>
                        <span class="fw-semibold text-success"><i class="bi bi-check-circle me-1"></i>Writable</span>
                    </li>
                </ul>
            </div>
        </div>
    </div>
</div>

<script>
function toggleApiKeyVisibility() {
    const input = document.getElementById('gemini_api_key');
    const icon = document.getElementById('toggleApiKeyIcon');
    if (input.type === 'password') {
        input.type = 'text';
        icon.classList.remove('bi-eye');
        icon.classList.add('bi-eye-slash');
    } else {
        input.type = 'password';
        icon.classList.remove('bi-eye-slash');
        icon.classList.add('bi-eye');
    }
}

function toggleCustomModel(val) {
    const group = document.getElementById('customModelGroup');
    if (val === 'custom') {
        group.classList.remove('d-none');
    } else {
        group.classList.add('d-none');
    }
}

async function testGeminiConnection() {
    const btn = document.getElementById('btnTestAi');
    const spinner = document.getElementById('testAiSpinner');
    const icon = document.getElementById('testAiIcon');
    const resultBox = document.getElementById('testAiResult');
    
    const apiKey = document.getElementById('gemini_api_key').value.trim();
    let model = document.getElementById('gemini_model').value;
    if (model === 'custom') {
        model = document.getElementById('custom_model').value.trim();
    }

    if (!apiKey) {
        resultBox.className = 'mt-3 alert alert-warning small';
        resultBox.innerHTML = '<i class="bi bi-exclamation-triangle me-1"></i> Please enter a Gemini API Key first.';
        resultBox.classList.remove('d-none');
        return;
    }

    btn.disabled = true;
    spinner.classList.remove('d-none');
    icon.classList.add('d-none');
    resultBox.classList.add('d-none');

    try {
        const formData = new FormData();
        formData.append('api_key', apiKey);
        formData.append('model', model);

        const response = await fetch('<?= base_url('api/test_ai.php') ?>', {
            method: 'POST',
            body: formData
        });

        const data = await response.json();

        resultBox.classList.remove('d-none');
        if (data.success) {
            resultBox.className = 'mt-3 alert alert-success small';
            resultBox.innerHTML = `
                <div class="fw-bold"><i class="bi bi-check-circle-fill me-1"></i> Connection Successful!</div>
                <div>Model: <strong>${data.model}</strong> | Latency: <strong>${data.latency_ms} ms</strong></div>
                <div class="text-muted mt-1 fst-italic">AI response: "${data.reply}"</div>
            `;
        } else {
            resultBox.className = 'mt-3 alert alert-danger small';
            resultBox.innerHTML = `
                <div class="fw-bold"><i class="bi bi-x-circle-fill me-1"></i> Connection Failed!</div>
                <div>${data.message || 'Unknown error'}</div>
            `;
        }
    } catch (err) {
        resultBox.classList.remove('d-none');
        resultBox.className = 'mt-3 alert alert-danger small';
        resultBox.innerHTML = `<i class="bi bi-x-circle-fill me-1"></i> Network/Server error: ${err.message}`;
    } finally {
        btn.disabled = false;
        spinner.classList.add('d-none');
        icon.classList.remove('d-none');
    }
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
