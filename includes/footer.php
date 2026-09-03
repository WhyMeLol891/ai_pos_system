    </div><!-- container-fluid end -->
</main>

<!-- Footer -->
<footer class="bg-white border-top py-2 mt-auto">
    <div class="container-fluid px-3 px-lg-4 d-flex flex-wrap justify-content-between align-items-center small text-muted">
        <div>
            <strong><?= clean(get_setting('shop_name', 'AI SMART MART')) ?></strong> &copy; <?= date('Y') ?> &bull; AI Camera POS System
        </div>
        <div>
            <span class="badge bg-secondary-subtle text-secondary border me-2">
                <i class="bi bi-cpu me-1"></i> <?= clean(get_setting('gemini_model', 'gemini-3.7-flash')) ?>
            </span>
            <span class="text-success"><i class="bi bi-circle-fill" style="font-size: 0.55rem;"></i> System Online</span>
        </div>
    </div>
</footer>

<!-- Bootstrap 5 JS Bundle -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<!-- Global App JS -->
<script src="<?= base_url('assets/js/main.js') ?>"></script>
</body>
</html>
