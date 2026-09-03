<?php
/**
 * Cashier POS Main Interface
 * AI Camera POS System
 */

$pageTitle = 'Point of Sale';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/auth.php';

require_login();

$pdo = get_db_connection();
$categories = $pdo->query("SELECT * FROM categories ORDER BY name ASC")->fetchAll();
$currency = get_setting('currency_symbol', 'RM');
$geminiModel = get_setting('gemini_model', 'gemini-3.6-flash');

require_once __DIR__ . '/../includes/header.php';
?>

<script>
window.BASE_URL = '<?= rtrim(base_url(), '/') ?>';
</script>

<!-- POS Container -->
<div class="row g-3 pos-container">
    <!-- LEFT PANEL: AI Camera Trigger + Product Quick-Grid -->
    <div class="col-12 col-lg-7 col-xl-8 d-flex flex-column">
        <div class="pos-products-panel p-3 flex-grow-1 shadow-sm">
            <!-- Top Controls Row -->
            <div class="row g-2 align-items-center mb-3">
                <!-- BIG AI CAMERA SCAN BUTTON (Requirement 13) -->
                <div class="col-12 col-sm-6 col-md-5">
                    <button type="button" class="btn btn-pos-action btn-camera-scan w-100 shadow-sm" data-bs-toggle="modal" data-bs-target="#aiCameraModal">
                        <i class="bi bi-camera-fill fs-4"></i>
                        <span>AI Camera Scan</span>
                    </button>
                </div>
                
                <!-- Product Search Input -->
                <div class="col-12 col-sm-6 col-md-7">
                    <div class="input-group input-group-lg">
                        <span class="input-group-text bg-light text-muted border-end-0"><i class="bi bi-search"></i></span>
                        <input type="text" id="posSearchInput" class="form-control form-control-lg border-start-0" placeholder="Search product name or SKU...">
                    </div>
                </div>
            </div>

            <!-- Category Filter Pills -->
            <div class="d-flex gap-2 overflow-x-auto pb-2 mb-2 border-bottom text-nowrap">
                <button type="button" class="btn btn-sm btn-primary cat-pill rounded-pill px-3 active" data-category="0">
                    <i class="bi bi-grid-fill me-1"></i> All Items
                </button>
                <?php foreach ($categories as $cat): ?>
                    <button type="button" class="btn btn-sm btn-outline-secondary cat-pill rounded-pill px-3" data-category="<?= $cat['id'] ?>">
                        <?= clean($cat['name']) ?>
                    </button>
                <?php endforeach; ?>
            </div>

            <!-- Product Grid Scroll Area -->
            <div class="products-grid-scroll flex-grow-1">
                <div class="row g-2" id="posProductsGrid">
                    <div class="col-12 text-center py-5 text-muted">
                        <div class="spinner-border text-primary" role="status"></div>
                        <p class="mt-2 small">Loading catalog items...</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- RIGHT PANEL: Shopping Cart & Checkout (Requirement 6 & 7) -->
    <div class="col-12 col-lg-5 col-xl-4 d-flex flex-column">
        <div class="pos-cart-panel shadow-sm">
            <!-- Cart Header -->
            <div class="p-3 border-bottom bg-white d-flex justify-content-between align-items-center">
                <div class="d-flex align-items-center gap-2">
                    <h5 class="fw-bold mb-0 text-dark"><i class="bi bi-cart3 text-primary me-1"></i>Current Cart</h5>
                    <span class="badge bg-primary rounded-pill font-monospace" id="cartCountBadge">0 items</span>
                </div>
                <button type="button" class="btn btn-sm btn-outline-danger" id="btnClearCart" title="Clear all items">
                    <i class="bi bi-trash me-1"></i> Clear
                </button>
            </div>

            <!-- Cart Table Header -->
            <div class="bg-light px-3 py-2 border-bottom d-flex justify-content-between text-muted small fw-semibold">
                <div class="flex-grow-1">Product Name</div>
                <div style="width: 110px;" class="text-center">Quantity</div>
                <div style="width: 75px;" class="text-end">Total</div>
            </div>

            <!-- Cart Items Scroll List -->
            <div class="cart-items-scroll flex-grow-1 p-2" id="cartItemsList">
                <div id="cartItemsContainer"></div>
                <div id="cartEmptyState" class="text-center py-5 text-muted">
                    <i class="bi bi-cart-x fs-1 d-block mb-2 text-secondary"></i>
                    <p class="mb-1 fw-semibold">Cart is currently empty</p>
                    <small class="text-muted">Use <strong>AI Camera Scan</strong> or tap items on the left to add.</small>
                </div>
            </div>

            <!-- Cart Totals & Big Checkout Button -->
            <div class="p-3 bg-white border-top">
                <div class="d-flex justify-content-between text-muted mb-1 small">
                    <span>Subtotal</span>
                    <span class="fw-semibold text-dark font-monospace" id="cartSubtotalText"><?= $currency ?>0.00</span>
                </div>

                <div class="d-flex justify-content-between align-items-center mb-2 small">
                    <span class="text-muted">Discount (<?= $currency ?>)</span>
                    <div style="width: 100px;">
                        <input type="number" step="0.50" min="0" id="cartDiscountInput" class="form-control form-control-sm text-end font-monospace py-0" placeholder="0.00">
                    </div>
                </div>

                <div class="d-flex justify-content-between align-items-center py-2 border-top border-bottom my-2">
                    <span class="fw-bold fs-5 text-dark">Grand Total</span>
                    <span class="fw-bold fs-4 text-primary font-monospace" id="cartGrandTotalText"><?= $currency ?>0.00</span>
                </div>

                <!-- BIG CHECKOUT BUTTON (Requirement 13) -->
                <div class="d-grid mt-2">
                    <button type="button" class="btn btn-pos-action btn-checkout shadow-sm" id="btnOpenCheckout" onclick="posApp.openCheckoutModal()" disabled>
                        <i class="bi bi-cash-coin fs-4"></i>
                        <span>Checkout</span>
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ======================================================= -->
<!-- AI CAMERA SCAN MODAL (Requirement 4 & 5) -->
<!-- ======================================================= -->
<div class="modal fade" id="aiCameraModal" tabindex="-1" aria-labelledby="aiCameraModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-dark text-white py-3">
                <div class="d-flex align-items-center gap-2">
                    <span class="badge bg-primary p-2 rounded-3">
                        <i class="bi bi-camera-fill fs-5"></i>
                    </span>
                    <div>
                        <h5 class="modal-title fw-bold mb-0" id="aiCameraModalLabel">AI Camera Product Scan</h5>
                        <small class="text-white-50">Model: <span class="badge bg-secondary font-monospace"><?= clean($geminiModel) ?></span></small>
                    </div>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <div class="modal-body p-3">
                <div id="cameraStatusBox" class="d-none mb-3"></div>

                <!-- Viewfinder Box -->
                <div class="camera-viewfinder-box mb-3">
                    <video id="cameraVideo" autoplay playsinline muted></video>
                    <img id="cameraPreview" class="d-none" alt="Captured Photo">
                    <canvas id="cameraCanvas" class="d-none"></canvas>

                    <!-- Scanning HUD Crosshairs -->
                    <div class="scan-crosshairs">
                        <div class="scan-laser-line"></div>
                    </div>

                    <!-- AI Analyzing Spinner Overlay -->
                    <div id="aiScanLoading" class="position-absolute top-0 start-0 w-100 h-100 bg-dark bg-opacity-75 d-flex flex-column align-items-center justify-content-center text-white d-none" style="z-index: 10;">
                        <div class="spinner-border text-primary mb-3" style="width: 3.5rem; height: 3.5rem;" role="status"></div>
                        <h5 class="fw-bold mb-1">Gemini AI Vision Analyzing...</h5>
                        <p class="small text-white-70 mb-0">Counting items and matching against MySQL catalog</p>
                    </div>
                </div>

                <!-- Camera Control Buttons (Large for Mobile) -->
                <div class="row g-2 mb-3">
                    <div class="col-6 col-sm-4">
                        <button type="button" class="btn btn-primary btn-lg w-100 fw-bold d-flex align-items-center justify-content-center gap-2" id="btnCameraCapture">
                            <i class="bi bi-camera"></i> Take Photo
                        </button>
                    </div>
                    <div class="col-6 col-sm-4">
                        <button type="button" class="btn btn-outline-secondary btn-lg w-100 fw-semibold d-flex align-items-center justify-content-center gap-1" id="btnCameraSwitch">
                            <i class="bi bi-arrow-repeat"></i> Flip Cam
                        </button>
                    </div>
                    <div class="col-12 col-sm-4">
                        <label class="btn btn-outline-dark btn-lg w-100 fw-semibold d-flex align-items-center justify-content-center gap-2 mb-0">
                            <i class="bi bi-phone"></i> Upload / Phone
                            <input type="file" id="cameraFileInput" accept="image/*" capture="environment" class="d-none">
                        </label>
                    </div>
                </div>

                <!-- Retake Action -->
                <div class="text-center mb-2">
                    <button type="button" class="btn btn-link btn-sm text-secondary text-decoration-none" id="btnCameraRetake">
                        <i class="bi bi-arrow-counterclockwise me-1"></i> Retake / Clear Preview
                    </button>
                </div>

                <!-- Detected Products Review Panel -->
                <div id="aiScanResultsBox" class="border rounded-3 p-3 bg-light d-none">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <h6 class="fw-bold text-dark mb-0">
                            <i class="bi bi-check2-circle text-success me-1"></i> Detected Products
                        </h6>
                        <span class="text-muted small">Verify quantities before adding</span>
                    </div>

                    <div id="aiDetectedList" class="mb-3"></div>

                    <!-- BIG ADD DETECTED TO CART BUTTON (Requirement 13) -->
                    <div class="d-grid">
                        <button type="button" class="btn btn-pos-action btn-primary py-2" id="btnAddDetectedToCart">
                            <i class="bi bi-cart-plus-fill me-1"></i> Add Detected Items to Cart
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ======================================================= -->
<!-- CHECKOUT & PAYMENT MODAL (Requirement 7) -->
<!-- ======================================================= -->
<div class="modal fade" id="checkoutModal" tabindex="-1" aria-labelledby="checkoutModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-success text-white py-3">
                <h5 class="modal-title fw-bold mb-0" id="checkoutModalLabel">
                    <i class="bi bi-credit-card-2-front me-2"></i>Order Checkout
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            
            <div class="modal-body p-4">
                <!-- Customer Name -->
                <div class="mb-3">
                    <label class="form-label small fw-semibold text-secondary">Customer Name (Optional)</label>
                    <input type="text" id="customerNameInput" class="form-control" value="Walk-in Customer" placeholder="Enter name">
                </div>

                <!-- Grand Total Display -->
                <div class="p-3 bg-light rounded-3 border text-center mb-4">
                    <span class="text-muted small text-uppercase fw-semibold d-block">Amount Payable</span>
                    <h2 class="fw-bold text-success mt-1 mb-0 font-monospace" id="checkoutTotalDisplay"><?= $currency ?>0.00</h2>
                </div>

                <!-- Payment Method Selector (Requirement 7) -->
                <label class="form-label fw-semibold text-secondary small">Select Payment Method</label>
                <div class="row g-2 mb-4">
                    <div class="col-4">
                        <input type="radio" class="btn-check" name="payment_method" id="payMethodCash" value="cash" checked autocomplete="off">
                        <label class="btn btn-outline-success w-100 py-2 d-flex flex-column align-items-center gap-1" for="payMethodCash">
                            <i class="bi bi-cash-stack fs-4"></i>
                            <span class="fw-bold small">Cash</span>
                        </label>
                    </div>
                    <div class="col-4">
                        <input type="radio" class="btn-check" name="payment_method" id="payMethodCard" value="card" autocomplete="off">
                        <label class="btn btn-outline-primary w-100 py-2 d-flex flex-column align-items-center gap-1" for="payMethodCard">
                            <i class="bi bi-credit-card fs-4"></i>
                            <span class="fw-bold small">Card</span>
                        </label>
                    </div>
                    <div class="col-4">
                        <input type="radio" class="btn-check" name="payment_method" id="payMethodQr" value="qr" autocomplete="off">
                        <label class="btn btn-outline-info w-100 py-2 d-flex flex-column align-items-center gap-1" for="payMethodQr">
                            <i class="bi bi-qr-code-scan fs-4"></i>
                            <span class="fw-bold small">QR Pay</span>
                        </label>
                    </div>
                </div>

                <!-- Cash Payment Section with Fast Calculator -->
                <div id="cashPaymentSection">
                    <div class="mb-3">
                        <label for="cashReceivedInput" class="form-label fw-semibold">Cash Received (<?= $currency ?>) <span class="text-danger">*</span></label>
                        <div class="input-group input-group-lg">
                            <span class="input-group-text"><?= $currency ?></span>
                            <input type="number" step="0.50" min="0" id="cashReceivedInput" class="form-control form-control-lg font-monospace fw-bold text-end" value="0.00">
                        </div>
                    </div>

                    <!-- Fast Cash Preset Buttons -->
                    <div class="d-flex flex-wrap gap-2 mb-3 justify-content-center">
                        <button type="button" class="btn btn-light border cash-preset-btn flex-fill" data-exact="true">Exact</button>
                        <button type="button" class="btn btn-light border cash-preset-btn flex-fill" data-amount="10" data-add="true">+10</button>
                        <button type="button" class="btn btn-light border cash-preset-btn flex-fill" data-amount="20" data-add="true">+20</button>
                        <button type="button" class="btn btn-light border cash-preset-btn flex-fill" data-amount="50" data-add="false">RM50</button>
                        <button type="button" class="btn btn-light border cash-preset-btn flex-fill" data-amount="100" data-add="false">RM100</button>
                    </div>

                    <!-- Change Calculation Box (Requirement 7) -->
                    <div class="p-3 bg-light-subtle border rounded-3 text-center mb-3">
                        <span class="text-muted small fw-semibold text-uppercase d-block">Change to Return</span>
                        <h3 class="fw-bold text-dark mt-1 mb-0 font-monospace" id="checkoutChangeDisplay"><?= $currency ?>0.00</h3>
                        <div id="checkoutCashError" class="text-danger small mt-1 d-none fw-semibold"></div>
                    </div>
                </div>

                <!-- QR Payment Section -->
                <div id="qrPaymentSection" class="text-center py-3 d-none">
                    <p class="text-muted small mb-2">Scan with e-Wallet / Banking App:</p>
                    <div class="d-inline-block p-2 bg-white border rounded shadow-sm">
                        <!-- Simulated QR Code SVG -->
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100" width="140" height="140">
                            <rect width="100" height="100" fill="#ffffff"/>
                            <path d="M10 10h30v30h-30z M15 15v20h20v-20z M20 20h10v10h-10z M60 10h30v30h-30z M65 15v20h20v-20z M70 20h10v10h-10z M10 60h30v30h-30z M15 65v20h20v-20z M20 70h10v10h-10z M60 60h10v10h-10z M80 60h10v10h-10z M70 70h10v10h-10z M60 80h10v10h-10z M80 80h10v10h-10z" fill="#000000"/>
                        </svg>
                    </div>
                    <div class="mt-2 text-success fw-bold small">
                        <i class="bi bi-shield-lock-fill me-1"></i> DuitNow / QR Pay Ready
                    </div>
                </div>

                <!-- Card Payment Section -->
                <div id="cardPaymentSection" class="text-center py-3 d-none">
                    <i class="bi bi-credit-card-2-front text-primary display-4 d-block mb-2"></i>
                    <p class="text-muted small mb-0">Tap, insert or swipe customer card on terminal.</p>
                </div>
            </div>

            <div class="modal-footer p-3 bg-light">
                <button type="button" class="btn btn-light border" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-success fw-bold px-4" id="btnConfirmCheckout">
                    <i class="bi bi-check-circle-fill me-1"></i> Complete & Print Receipt
                </button>
            </div>
        </div>
    </div>
</div>

<!-- ======================================================= -->
<!-- RECEIPT MODAL & THERMAL SLIP (Requirement 8) -->
<!-- ======================================================= -->
<div class="modal fade" id="receiptModal" tabindex="-1" aria-labelledby="receiptModalLabel" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-dark text-white py-2 no-print">
                <h6 class="modal-title fw-bold mb-0" id="receiptModalLabel">
                    <i class="bi bi-receipt me-2"></i>Official Receipt
                </h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            
            <div class="modal-body p-3">
                <div id="receiptSlipContainer"></div>
            </div>

            <div class="modal-footer p-3 bg-light d-flex justify-content-between no-print">
                <button type="button" class="btn btn-primary" data-bs-dismiss="modal">
                    <i class="bi bi-plus-lg me-1"></i> New Sale
                </button>
                <!-- BIG PRINT RECEIPT BUTTON (Requirement 13) -->
                <button type="button" class="btn btn-pos-action btn-success px-4" onclick="window.print()">
                    <i class="bi bi-printer-fill fs-5"></i> Print Receipt
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Scripts -->
<script src="<?= base_url('assets/js/camera.js') ?>"></script>
<script src="<?= base_url('assets/js/pos.js') ?>"></script>
<script>
let posApp;
document.addEventListener('DOMContentLoaded', () => {
    posApp = new PosApp({
        currency: '<?= $currency ?>'
    });
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
