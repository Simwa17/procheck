</div><!-- /container-fluid -->

<footer class="footer mt-auto py-3 bg-light border-top">
  <div class="container-fluid px-4">
    <div class="d-flex justify-content-between align-items-center">
      <span class="text-muted small">
        <i class="bi bi-check2-circle me-1 text-primary"></i>
        <strong>ProCheck</strong> v<?= APP_VERSION ?> &mdash; Project Pricing for Malawian Developers
      </span>
      <span class="text-muted small">&copy; <?= date('Y') ?> <?= h(setting('company_name', 'ProCheck')) ?></span>
    </div>
  </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<?= $extra_scripts ?? '' ?>
</body>
</html>
