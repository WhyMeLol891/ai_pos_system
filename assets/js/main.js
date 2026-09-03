/**
 * Global JavaScript Utilities
 * AI Camera POS System
 */

// Toast notification helper
function showToast(message, type = 'info') {
    const toastContainer = document.getElementById('toastContainer') || (() => {
        const c = document.createElement('div');
        c.id = 'toastContainer';
        c.className = 'toast-container position-fixed bottom-0 end-0 p-3';
        c.style.zIndex = '1090';
        document.body.appendChild(c);
        return c;
    })();

    const bgClass = {
        'success': 'text-bg-success',
        'danger': 'text-bg-danger',
        'error': 'text-bg-danger',
        'warning': 'text-bg-warning',
        'info': 'text-bg-primary'
    }[type] || 'text-bg-dark';

    const toastEl = document.createElement('div');
    toastEl.className = `toast align-items-center ${bgClass} border-0 show shadow-lg mb-2`;
    toastEl.setAttribute('role', 'alert');
    toastEl.setAttribute('aria-live', 'assertive');
    toastEl.setAttribute('aria-atomic', 'true');
    toastEl.innerHTML = `
        <div class="d-flex">
            <div class="toast-body fw-semibold">
                ${message}
            </div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
        </div>
    `;

    toastContainer.appendChild(toastEl);
    const toast = new bootstrap.Toast(toastEl, { delay: 4000 });
    toast.show();

    toastEl.addEventListener('hidden.bs.toast', () => {
        toastEl.remove();
    });
}

// Format numbers as currency
function formatCurrency(amount, symbol = 'RM') {
    return symbol + parseFloat(amount || 0).toFixed(2);
}
