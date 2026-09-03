<?php
/**
 * Flash Message Renderer
 * AI Camera POS System
 */

$flash = get_flash();
if ($flash):
    $alertIcon = match($flash['type']) {
        'success' => 'bi-check-circle-fill',
        'danger'  => 'bi-exclamation-triangle-fill',
        'warning' => 'bi-exclamation-circle-fill',
        default   => 'bi-info-circle-fill'
    };
?>
<div class="alert alert-<?= clean($flash['type']) ?> alert-dismissible fade show shadow-sm my-3" role="alert">
    <i class="bi <?= $alertIcon ?> me-2"></i>
    <?= clean($flash['message']) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
</div>
<?php endif; ?>
