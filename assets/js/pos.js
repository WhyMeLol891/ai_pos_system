/**
 * POS Logic & Cart Management
 * AI Camera POS System
 */

class PosApp {
    constructor(config = {}) {
        this.currency = config.currency || 'RM';
        this.cart = []; // [{ id, name, sku, price, stock, quantity, image_url }]
        this.discount = 0.00;
        this.products = [];
        this.activeCategory = 0;
        this.searchQuery = '';
        
        // Camera manager
        this.camera = new CameraManager({
            videoElement: document.getElementById('cameraVideo'),
            canvasElement: document.getElementById('cameraCanvas'),
            previewImage: document.getElementById('cameraPreview')
        });

        this.detectedAiItems = [];

        this.init();
    }

    init() {
        this.bindEvents();
        this.loadProducts();
        this.renderCart();
    }

    bindEvents() {
        // Search filter
        const searchInput = document.getElementById('posSearchInput');
        if (searchInput) {
            searchInput.addEventListener('input', (e) => {
                this.searchQuery = e.target.value.trim().toLowerCase();
                this.renderProducts();
            });
        }

        // Category tabs
        document.querySelectorAll('.cat-pill').forEach(btn => {
            btn.addEventListener('click', (e) => {
                document.querySelectorAll('.cat-pill').forEach(b => b.classList.remove('active', 'btn-primary'));
                document.querySelectorAll('.cat-pill').forEach(b => b.classList.add('btn-outline-secondary'));
                
                btn.classList.remove('btn-outline-secondary');
                btn.classList.add('active', 'btn-primary');

                this.activeCategory = parseInt(btn.dataset.category || 0);
                this.renderProducts();
            });
        });

        // Discount input
        const discountInput = document.getElementById('cartDiscountInput');
        if (discountInput) {
            discountInput.addEventListener('input', (e) => {
                this.discount = Math.max(0, parseFloat(e.target.value) || 0);
                this.updateTotals();
            });
        }

        // Clear Cart
        const btnClearCart = document.getElementById('btnClearCart');
        if (btnClearCart) {
            btnClearCart.addEventListener('click', () => this.clearCart());
        }

        // Camera modal events
        const cameraModalEl = document.getElementById('aiCameraModal');
        if (cameraModalEl) {
            cameraModalEl.addEventListener('shown.bs.modal', () => {
                this.startCameraSession();
            });
            cameraModalEl.addEventListener('hidden.bs.modal', () => {
                this.camera.stopCamera();
                this.camera.reset();
                this.resetAiResults();
            });
        }

        // Camera capture button
        const btnCapture = document.getElementById('btnCameraCapture');
        if (btnCapture) {
            btnCapture.addEventListener('click', () => this.captureAndAnalyze());
        }

        // Switch camera (front/back)
        const btnSwitch = document.getElementById('btnCameraSwitch');
        if (btnSwitch) {
            btnSwitch.addEventListener('click', async () => {
                try {
                    await this.camera.switchCamera();
                } catch (err) {
                    showToast(err.message, 'warning');
                }
            });
        }

        // Native file input fallback
        const fileInput = document.getElementById('cameraFileInput');
        if (fileInput) {
            fileInput.addEventListener('change', async (e) => {
                if (e.target.files && e.target.files[0]) {
                    try {
                        await this.camera.handleFileInput(e.target.files[0]);
                        await this.analyzeImage(this.camera.getCapturedData());
                    } catch (err) {
                        showToast(err.message, 'danger');
                    }
                }
            });
        }

        // Retake button
        const btnRetake = document.getElementById('btnCameraRetake');
        if (btnRetake) {
            btnRetake.addEventListener('click', () => {
                this.camera.reset();
                this.resetAiResults();
                this.camera.startCamera();
            });
        }

        // Add detected items to cart button
        const btnAddDetected = document.getElementById('btnAddDetectedToCart');
        if (btnAddDetected) {
            btnAddDetected.addEventListener('click', () => this.addDetectedItemsToCart());
        }

        // Cash preset buttons in checkout modal
        document.querySelectorAll('.cash-preset-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                const amount = parseFloat(btn.dataset.amount || 0);
                const isAdd = btn.dataset.add === 'true';
                const input = document.getElementById('cashReceivedInput');
                
                if (isAdd) {
                    const current = parseFloat(input.value) || 0;
                    input.value = (current + amount).toFixed(2);
                } else if (btn.dataset.exact === 'true') {
                    input.value = this.getGrandTotal().toFixed(2);
                } else {
                    input.value = amount.toFixed(2);
                }
                this.calculateChange();
            });
        });

        // Cash received input
        const cashInput = document.getElementById('cashReceivedInput');
        if (cashInput) {
            cashInput.addEventListener('input', () => this.calculateChange());
        }

        // Payment method change
        document.querySelectorAll('input[name="payment_method"]').forEach(radio => {
            radio.addEventListener('change', (e) => {
                this.togglePaymentMethodUI(e.target.value);
            });
        });

        // Checkout submit
        const btnConfirmCheckout = document.getElementById('btnConfirmCheckout');
        if (btnConfirmCheckout) {
            btnConfirmCheckout.addEventListener('click', () => this.processCheckout());
        }
    }

    /**
     * Load products from API
     */
    async loadProducts() {
        const container = document.getElementById('posProductsGrid');
        if (!container) return;

        try {
            const res = await fetch(`${window.BASE_URL}/api/get_products.php`);
            const data = await res.json();
            if (data.success) {
                this.products = data.products;
                this.renderProducts();
            } else {
                container.innerHTML = `<div class="col-12 text-center text-danger py-4">${data.message}</div>`;
            }
        } catch (err) {
            container.innerHTML = `<div class="col-12 text-center text-danger py-4">Failed to load product catalog.</div>`;
        }
    }

    /**
     * Render product cards grid
     */
    renderProducts() {
        const container = document.getElementById('posProductsGrid');
        if (!container) return;

        const filtered = this.products.filter(p => {
            const matchesCat = (this.activeCategory === 0 || parseInt(p.category_id) === this.activeCategory);
            const matchesSearch = (!this.searchQuery || 
                p.name.toLowerCase().includes(this.searchQuery) || 
                p.sku.toLowerCase().includes(this.searchQuery));
            return matchesCat && matchesSearch;
        });

        if (filtered.length === 0) {
            container.innerHTML = `
                <div class="col-12 text-center py-5 text-muted">
                    <i class="bi bi-search fs-1 d-block mb-2"></i>
                    No products found matching your search.
                </div>
            `;
            return;
        }

        container.innerHTML = filtered.map(p => {
            const isOutOfStock = parseInt(p.stock_quantity) <= 0;
            return `
                <div class="col-6 col-sm-4 col-md-3 col-xl-2 mb-3">
                    <div class="product-card p-2 text-center ${isOutOfStock ? 'opacity-50' : ''}" 
                         onclick="${isOutOfStock ? "showToast('Item is out of stock', 'warning')" : `posApp.addToCartById(${p.id})`}">
                        <img src="${p.image_url}" alt="${p.name}" class="product-thumb mb-2">
                        <div class="fw-bold text-dark text-truncate small mb-1" title="${p.name}">${p.name}</div>
                        <div class="d-flex justify-content-between align-items-center mt-auto">
                            <span class="fw-bold text-primary">${this.currency}${parseFloat(p.price).toFixed(2)}</span>
                            <span class="badge ${parseInt(p.stock_quantity) <= 5 ? 'bg-warning text-dark' : 'bg-light text-secondary border'} small" style="font-size: 0.7rem;">
                                ${p.stock_quantity} left
                            </span>
                        </div>
                    </div>
                </div>
            `;
        }).join('');
    }

    /**
     * Add item to cart by ID
     */
    addToCartById(id, qty = 1) {
        const prod = this.products.find(p => parseInt(p.id) === parseInt(id));
        if (!prod) return;

        const existing = this.cart.find(item => parseInt(item.id) === parseInt(id));
        const maxStock = parseInt(prod.stock_quantity);

        if (existing) {
            if (existing.quantity + qty > maxStock) {
                showToast(`Cannot add more. Only ${maxStock} units in stock.`, 'warning');
                return;
            }
            existing.quantity += qty;
        } else {
            if (qty > maxStock) {
                showToast(`Cannot add. Only ${maxStock} units in stock.`, 'warning');
                return;
            }
            this.cart.push({
                id: parseInt(prod.id),
                name: prod.name,
                sku: prod.sku,
                price: parseFloat(prod.price),
                stock: maxStock,
                quantity: qty,
                image_url: prod.image_url
            });
        }

        this.renderCart();
    }

    /**
     * Change item quantity in cart
     */
    updateCartQty(id, newQty) {
        const item = this.cart.find(i => parseInt(i.id) === parseInt(id));
        if (!item) return;

        const qty = parseInt(newQty);
        if (qty <= 0) {
            this.removeCartItem(id);
            return;
        }

        if (qty > item.stock) {
            showToast(`Cannot add more. Only ${item.stock} in stock.`, 'warning');
            item.quantity = item.stock;
        } else {
            item.quantity = qty;
        }

        this.renderCart();
    }

    /**
     * Remove item from cart
     */
    removeCartItem(id) {
        this.cart = this.cart.filter(i => parseInt(i.id) !== parseInt(id));
        this.renderCart();
    }

    /**
     * Clear all items in cart
     */
    clearCart() {
        if (this.cart.length === 0) return;
        this.cart = [];
        this.discount = 0;
        const discountInput = document.getElementById('cartDiscountInput');
        if (discountInput) discountInput.value = '';
        this.renderCart();
        showToast('Cart cleared', 'info');
    }

    /**
     * Calculate financial totals
     */
    getSubtotal() {
        return this.cart.reduce((sum, item) => sum + (item.price * item.quantity), 0);
    }

    getGrandTotal() {
        return Math.max(0, this.getSubtotal() - this.discount);
    }

    /**
     * Render cart rows & update summary
     */
    renderCart() {
        const container = document.getElementById('cartItemsContainer');
        const emptyState = document.getElementById('cartEmptyState');
        const checkoutBtn = document.getElementById('btnOpenCheckout');

        if (!container) return;

        if (this.cart.length === 0) {
            container.innerHTML = '';
            if (emptyState) emptyState.classList.remove('d-none');
            if (checkoutBtn) checkoutBtn.disabled = true;
            this.updateTotals();
            return;
        }

        if (emptyState) emptyState.classList.add('d-none');
        if (checkoutBtn) checkoutBtn.disabled = false;

        container.innerHTML = this.cart.map(item => {
            const lineTotal = (item.price * item.quantity).toFixed(2);
            return `
                <div class="cart-item-row p-2 border-bottom d-flex align-items-center justify-content-between gap-2">
                    <div class="d-flex align-items-center gap-2 flex-grow-1" style="min-width: 0;">
                        <img src="${item.image_url}" alt="" width="36" height="36" class="rounded object-fit-contain bg-light border flex-shrink-0">
                        <div class="text-truncate">
                            <div class="fw-semibold text-dark text-truncate small" title="${item.name}">${item.name}</div>
                            <small class="text-muted">${this.currency}${item.price.toFixed(2)} / unit</small>
                        </div>
                    </div>

                    <!-- Quantity Stepper -->
                    <div class="d-flex align-items-center gap-1 flex-shrink-0">
                        <button type="button" class="btn btn-sm btn-outline-secondary qty-btn" onclick="posApp.updateCartQty(${item.id}, ${item.quantity - 1})">-</button>
                        <input type="number" min="1" max="${item.stock}" value="${item.quantity}" 
                               class="form-control form-control-sm text-center font-monospace px-1" 
                               style="width: 44px; height: 32px;" 
                               onchange="posApp.updateCartQty(${item.id}, this.value)">
                        <button type="button" class="btn btn-sm btn-outline-secondary qty-btn" onclick="posApp.updateCartQty(${item.id}, ${item.quantity + 1})">+</button>
                    </div>

                    <!-- Total & Remove -->
                    <div class="text-end flex-shrink-0" style="width: 75px;">
                        <span class="fw-bold text-dark d-block small">${this.currency}${lineTotal}</span>
                        <button type="button" class="btn btn-link btn-sm text-danger p-0 text-decoration-none" style="font-size: 0.75rem;" onclick="posApp.removeCartItem(${item.id})">
                            <i class="bi bi-trash"></i> Remove
                        </button>
                    </div>
                </div>
            `;
        }).join('');

        this.updateTotals();
    }

    /**
     * Update summary labels
     */
    updateTotals() {
        const subtotal = this.getSubtotal();
        const grandTotal = this.getGrandTotal();
        const itemCount = this.cart.reduce((count, item) => count + item.quantity, 0);

        const subtotalEl = document.getElementById('cartSubtotalText');
        const grandTotalEl = document.getElementById('cartGrandTotalText');
        const countBadge = document.getElementById('cartCountBadge');

        if (subtotalEl) subtotalEl.textContent = `${this.currency}${subtotal.toFixed(2)}`;
        if (grandTotalEl) grandTotalEl.textContent = `${this.currency}${grandTotal.toFixed(2)}`;
        if (countBadge) countBadge.textContent = `${itemCount} items`;
    }

    // ==============================================================
    // AI CAMERA DETECTION METHODS (Requirement 4 & 5)
    // ==============================================================

    async startCameraSession() {
        const statusBox = document.getElementById('cameraStatusBox');
        try {
            if (statusBox) statusBox.classList.add('d-none');
            await this.camera.startCamera();
        } catch (err) {
            if (statusBox) {
                statusBox.classList.remove('d-none');
                statusBox.innerHTML = `
                    <div class="alert alert-warning small mb-0">
                        <i class="bi bi-camera-video-off me-1"></i> ${err.message}<br>
                        <strong>Tip:</strong> Tap <em>"Upload / Phone Camera"</em> button below to snap directly using your phone's native camera.
                    </div>
                `;
            }
        }
    }

    async captureAndAnalyze() {
        try {
            const base64 = this.camera.captureSnapshot();
            await this.analyzeImage(base64);
        } catch (err) {
            showToast(err.message, 'warning');
        }
    }

    async analyzeImage(base64Data) {
        if (!base64Data) {
            showToast('No photo available to analyze.', 'warning');
            return;
        }

        const loadingOverlay = document.getElementById('aiScanLoading');
        const resultsBox = document.getElementById('aiScanResultsBox');
        const captureBtn = document.getElementById('btnCameraCapture');
        const retakeBtn = document.getElementById('btnCameraRetake');

        if (loadingOverlay) loadingOverlay.classList.remove('d-none');
        if (resultsBox) resultsBox.classList.add('d-none');
        if (captureBtn) captureBtn.disabled = true;
        if (retakeBtn) retakeBtn.disabled = true;

        try {
            const formData = new FormData();
            formData.append('image_base64', base64Data);

            const res = await fetch(`${window.BASE_URL}/api/ai_detect.php`, {
                method: 'POST',
                body: formData
            });

            const data = await res.json();

            if (data.success) {
                this.detectedAiItems = data.items || [];
                this.renderDetectedItems(this.detectedAiItems, data.model);
            } else {
                showToast(data.message || 'AI scanning failed.', 'danger');
                if (resultsBox) {
                    resultsBox.classList.remove('d-none');
                    resultsBox.innerHTML = `
                        <div class="alert alert-danger mb-0">
                            <strong><i class="bi bi-exclamation-triangle-fill me-1"></i> AI Error:</strong> ${data.message}
                        </div>
                    `;
                }
            }
        } catch (err) {
            showToast('Network error while analyzing photo: ' + err.message, 'danger');
        } finally {
            if (loadingOverlay) loadingOverlay.classList.add('d-none');
            if (captureBtn) captureBtn.disabled = false;
            if (retakeBtn) retakeBtn.disabled = false;
        }
    }

    renderDetectedItems(items, modelName) {
        const resultsBox = document.getElementById('aiScanResultsBox');
        const listContainer = document.getElementById('aiDetectedList');
        const btnAddDetected = document.getElementById('btnAddDetectedToCart');

        if (!resultsBox || !listContainer) return;

        resultsBox.classList.remove('d-none');

        if (items.length === 0) {
            listContainer.innerHTML = `
                <div class="alert alert-warning mb-0 small">
                    <i class="bi bi-info-circle me-1"></i> No products could be detected in this photo. Please retake the photo with clearer lighting or add items manually.
                </div>
            `;
            if (btnAddDetected) btnAddDetected.classList.add('d-none');
            return;
        }

        let hasFoundItems = false;

        listContainer.innerHTML = items.map((item, idx) => {
            if (item.found) {
                hasFoundItems = true;
                return `
                    <div class="d-flex align-items-center justify-content-between p-2 mb-2 bg-white rounded border border-success-subtle shadow-sm">
                        <div class="form-check me-2">
                            <input class="form-check-input ai-item-check" type="checkbox" checked id="aiCheck_${idx}" data-index="${idx}">
                        </div>
                        <img src="${item.image_url}" alt="" width="36" height="36" class="rounded object-fit-contain bg-light border me-2">
                        <div class="flex-grow-1 text-truncate">
                            <strong class="d-block text-dark small text-truncate">${item.name}</strong>
                            <span class="badge bg-light text-secondary border font-monospace" style="font-size: 0.65rem;">${item.sku}</span>
                            <span class="text-primary fw-bold ms-1 small">${item.formatted_price}</span>
                        </div>
                        <div class="d-flex align-items-center gap-1">
                            <button type="button" class="btn btn-sm btn-light border py-0 px-2" onclick="posApp.adjustAiQty(${idx}, -1)">-</button>
                            <span class="fw-bold px-2 small" id="aiQtyText_${idx}">${item.quantity}</span>
                            <button type="button" class="btn btn-sm btn-light border py-0 px-2" onclick="posApp.adjustAiQty(${idx}, 1)">+</button>
                        </div>
                    </div>
                `;
            } else {
                // Product Not Found (Requirement 4)
                return `
                    <div class="d-flex align-items-center justify-content-between p-2 mb-2 bg-light rounded border border-danger-subtle">
                        <div class="me-2 text-danger">
                            <i class="bi bi-question-diamond-fill fs-5"></i>
                        </div>
                        <div class="flex-grow-1">
                            <strong class="text-dark small d-block">${item.detected_name}</strong>
                            <span class="badge bg-danger-subtle text-danger border border-danger-subtle">Product not found</span>
                            <small class="text-muted d-block" style="font-size: 0.7rem;">Item not in MySQL database catalog</small>
                        </div>
                        <div>
                            <button type="button" class="btn btn-sm btn-outline-secondary py-1" onclick="posApp.openManualSearchModal('${encodeURIComponent(item.detected_name)}')">
                                Search Catalog
                            </button>
                        </div>
                    </div>
                `;
            }
        }).join('');

        if (btnAddDetected) {
            if (hasFoundItems) {
                btnAddDetected.classList.remove('d-none');
            } else {
                btnAddDetected.classList.add('d-none');
            }
        }
    }

    adjustAiQty(index, change) {
        if (!this.detectedAiItems[index]) return;
        const item = this.detectedAiItems[index];
        const newQty = Math.max(1, item.quantity + change);

        if (newQty > item.stock) {
            showToast(`Cannot add more. Only ${item.stock} in stock.`, 'warning');
            return;
        }

        item.quantity = newQty;
        const textEl = document.getElementById(`aiQtyText_${index}`);
        if (textEl) textEl.textContent = newQty;
    }

    addDetectedItemsToCart() {
        let addedCount = 0;
        document.querySelectorAll('.ai-item-check:checked').forEach(cb => {
            const idx = parseInt(cb.dataset.index);
            const item = this.detectedAiItems[idx];
            if (item && item.found) {
                this.addToCartById(item.id, item.quantity);
                addedCount++;
            }
        });

        if (addedCount > 0) {
            showToast(`Added ${addedCount} AI detected items to cart!`, 'success');
            // Close camera modal
            const modalEl = document.getElementById('aiCameraModal');
            if (modalEl) {
                bootstrap.Modal.getInstance(modalEl)?.hide();
            }
        } else {
            showToast('No items selected to add.', 'warning');
        }
    }

    resetAiResults() {
        this.detectedAiItems = [];
        const resultsBox = document.getElementById('aiScanResultsBox');
        if (resultsBox) resultsBox.classList.add('d-none');
    }

    openManualSearchModal(term) {
        // Pre-fill search in POS and close camera modal
        const searchInput = document.getElementById('posSearchInput');
        if (searchInput) {
            searchInput.value = decodeURIComponent(term);
            this.searchQuery = decodeURIComponent(term).toLowerCase();
            this.renderProducts();
        }
        const modalEl = document.getElementById('aiCameraModal');
        if (modalEl) {
            bootstrap.Modal.getInstance(modalEl)?.hide();
        }
        showToast(`Filtered POS products for: ${decodeURIComponent(term)}`, 'info');
    }

    // ==============================================================
    // CHECKOUT & PAYMENT METHODS (Requirement 7)
    // ==============================================================

    openCheckoutModal() {
        if (this.cart.length === 0) {
            showToast('The cart is empty.', 'warning');
            return;
        }

        const grandTotal = this.getGrandTotal();
        const totalDisplay = document.getElementById('checkoutTotalDisplay');
        const cashInput = document.getElementById('cashReceivedInput');
        const changeDisplay = document.getElementById('checkoutChangeDisplay');

        if (totalDisplay) totalDisplay.textContent = `${this.currency}${grandTotal.toFixed(2)}`;
        if (cashInput) {
            cashInput.value = grandTotal.toFixed(2);
        }

        this.calculateChange();
        new bootstrap.Modal(document.getElementById('checkoutModal')).show();
    }

    togglePaymentMethodUI(method) {
        const cashSection = document.getElementById('cashPaymentSection');
        const qrSection = document.getElementById('qrPaymentSection');
        const cardSection = document.getElementById('cardPaymentSection');

        if (cashSection) cashSection.classList.add('d-none');
        if (qrSection) qrSection.classList.add('d-none');
        if (cardSection) cardSection.classList.add('d-none');

        if (method === 'cash') {
            if (cashSection) cashSection.classList.remove('d-none');
            this.calculateChange();
        } else if (method === 'qr') {
            if (qrSection) qrSection.classList.remove('d-none');
        } else if (method === 'card') {
            if (cardSection) cardSection.classList.remove('d-none');
        }
    }

    calculateChange() {
        const grandTotal = this.getGrandTotal();
        const cashInput = document.getElementById('cashReceivedInput');
        const changeDisplay = document.getElementById('checkoutChangeDisplay');
        const errorAlert = document.getElementById('checkoutCashError');
        const btnSubmit = document.getElementById('btnConfirmCheckout');

        if (!cashInput || !changeDisplay) return;

        const cashPaid = parseFloat(cashInput.value) || 0;
        const change = cashPaid - grandTotal;

        if (change < 0) {
            changeDisplay.textContent = `${this.currency}0.00`;
            if (errorAlert) {
                errorAlert.classList.remove('d-none');
                errorAlert.textContent = `Cash is short by ${this.currency}${Math.abs(change).toFixed(2)}`;
            }
            if (btnSubmit) btnSubmit.disabled = true;
        } else {
            changeDisplay.textContent = `${this.currency}${change.toFixed(2)}`;
            if (errorAlert) errorAlert.classList.add('d-none');
            if (btnSubmit) btnSubmit.disabled = false;
        }
    }

    async processCheckout() {
        const grandTotal = this.getGrandTotal();
        const selectedMethod = document.querySelector('input[name="payment_method"]:checked')?.value || 'cash';
        const cashInput = document.getElementById('cashReceivedInput');
        const customerNameInput = document.getElementById('customerNameInput');
        const btnSubmit = document.getElementById('btnConfirmCheckout');

        let amountPaid = grandTotal;
        if (selectedMethod === 'cash') {
            amountPaid = parseFloat(cashInput?.value) || grandTotal;
            if (amountPaid < grandTotal) {
                showToast('Insufficient cash received.', 'danger');
                return;
            }
        }

        if (btnSubmit) {
            btnSubmit.disabled = true;
            btnSubmit.innerHTML = `<span class="spinner-border spinner-border-sm me-1"></span> Processing...`;
        }

        try {
            const payload = {
                customer_name: customerNameInput ? customerNameInput.value.trim() : 'Walk-in Customer',
                payment_method: selectedMethod,
                amount_paid: amountPaid,
                discount: this.discount,
                items: this.cart.map(i => ({ id: i.id, quantity: i.quantity }))
            };

            const res = await fetch(`${window.BASE_URL}/api/pos_checkout.php`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            });

            const result = await res.json();

            if (result.success) {
                // Close checkout modal
                bootstrap.Modal.getInstance(document.getElementById('checkoutModal'))?.hide();

                // Show Receipt Modal
                this.showReceiptModal(result.order);

                // Clear cart
                this.cart = [];
                this.discount = 0;
                const discountInput = document.getElementById('cartDiscountInput');
                if (discountInput) discountInput.value = '';
                this.renderCart();

                // Refresh product inventory counts in grid
                this.loadProducts();
            } else {
                showToast(result.message || 'Checkout failed.', 'danger');
            }
        } catch (err) {
            showToast('Checkout network error: ' + err.message, 'danger');
        } finally {
            if (btnSubmit) {
                btnSubmit.disabled = false;
                btnSubmit.innerHTML = `<i class="bi bi-check-circle-fill me-1"></i> Complete & Print Receipt`;
            }
        }
    }

    showReceiptModal(order) {
        const container = document.getElementById('receiptSlipContainer');
        if (!container) return;

        const itemsHtml = order.items.map(i => `
            <tr>
                <td style="padding: 3px 0;">${i.product_name}<br><span style="font-size: 0.75rem; color: #555;">${i.sku}</span></td>
                <td style="text-align: center;">${i.quantity}</td>
                <td style="text-align: right;">${order.currency}${parseFloat(i.unit_price).toFixed(2)}</td>
                <td style="text-align: right; font-weight: bold;">${order.currency}${parseFloat(i.subtotal).toFixed(2)}</td>
            </tr>
        `).join('');

        container.innerHTML = `
            <div class="receipt-container">
                <div class="receipt-header">
                    <div class="receipt-shop-title">${order.shop_name}</div>
                    <div class="receipt-info-line">${order.shop_address || ''}</div>
                    <div class="receipt-info-line">Tel: ${order.shop_phone || '-'}</div>
                </div>

                <div class="receipt-divider"></div>

                <div class="receipt-info-line"><strong>Invoice:</strong> ${order.invoice_no}</div>
                <div class="receipt-info-line"><strong>Date:</strong> ${order.date_time}</div>
                <div class="receipt-info-line"><strong>Cashier:</strong> ${order.cashier_name}</div>
                <div class="receipt-info-line"><strong>Customer:</strong> ${order.customer_name}</div>

                <div class="receipt-divider"></div>

                <table class="receipt-table">
                    <thead>
                        <tr>
                            <th style="text-align: left;">Item</th>
                            <th style="text-align: center; width: 40px;">Qty</th>
                            <th style="text-align: right; width: 55px;">Price</th>
                            <th style="text-align: right; width: 65px;">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${itemsHtml}
                    </tbody>
                </table>

                <div class="receipt-divider"></div>

                <div class="receipt-totals">
                    <div class="receipt-totals-row">
                        <span>Subtotal:</span>
                        <span>${order.currency}${parseFloat(order.subtotal).toFixed(2)}</span>
                    </div>
                    ${order.discount > 0 ? `
                    <div class="receipt-totals-row text-danger">
                        <span>Discount:</span>
                        <span>-${order.currency}${parseFloat(order.discount).toFixed(2)}</span>
                    </div>` : ''}
                    <div class="receipt-totals-row receipt-grand-total">
                        <span>GRAND TOTAL:</span>
                        <span>${order.currency}${parseFloat(order.grand_total).toFixed(2)}</span>
                    </div>
                    <div class="receipt-totals-row">
                        <span>Payment (${order.payment_method}):</span>
                        <span>${order.currency}${parseFloat(order.amount_paid).toFixed(2)}</span>
                    </div>
                    <div class="receipt-totals-row" style="font-weight: bold;">
                        <span>Change:</span>
                        <span>${order.currency}${parseFloat(order.change_amount).toFixed(2)}</span>
                    </div>
                </div>

                <div class="receipt-divider"></div>

                <div class="receipt-footer">
                    <p class="mb-1">${order.receipt_footer}</p>
                    <small>Powered by AI Camera POS System</small>
                </div>
            </div>
        `;

        new bootstrap.Modal(document.getElementById('receiptModal')).show();
    }
}
