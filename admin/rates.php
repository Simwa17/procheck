<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

$user = require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    foreach (['junior','mid','senior'] as $tier) {
        $rate = max(0, (float)($_POST['rate_' . $tier] ?? 0));
        $desc = trim($_POST['desc_' . $tier] ?? '');
        $stmt = db()->prepare('UPDATE developer_rates SET hourly_rate_mwk=?, description=? WHERE tier=?');
        $stmt->execute([$rate, $desc, $tier]);
    }
    flash_set('success', 'Developer rates updated successfully.');
    header('Location: ' . APP_URL . '/admin/rates.php');
    exit;
}

$rates = get_developer_rates();
$rates_by_tier = array_column($rates, null, 'tier');
$page_title = 'Developer Rates';
include __DIR__ . '/../includes/header.php';
?>

<div class="page-header mb-4">
  <div class="d-flex justify-content-between align-items-center">
    <div>
      <h4 class="fw-bold mb-0"><i class="bi bi-cash-coin me-2 text-primary"></i>Developer Rates</h4>
      <p class="text-muted mb-0">Set hourly rates in MWK for each developer tier</p>
    </div>
    <a href="<?= APP_URL ?>/admin/index.php" class="btn btn-outline-secondary btn-sm">
      <i class="bi bi-arrow-left me-1"></i>Back
    </a>
  </div>
</div>

<div class="row justify-content-center">
  <div class="col-md-8">
    <div class="card border-0 shadow-sm">
      <div class="card-body p-4">
        <p class="text-muted small mb-4">
          <i class="bi bi-info-circle me-1"></i>
          These rates reflect the Malawian developer market. Rates are in <strong>MWK per hour</strong> and used in all quote calculations.
        </p>
        <form method="POST">
          <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
          <div class="row g-4">
            <?php
            $tiers = [
                'junior' => ['color'=>'info',    'icon'=>'bi-person',        'label'=>'Junior Developer'],
                'mid'    => ['color'=>'warning',  'icon'=>'bi-person-check',  'label'=>'Mid-Level Developer'],
                'senior' => ['color'=>'danger',   'icon'=>'bi-person-badge',  'label'=>'Senior Developer'],
            ];
            foreach ($tiers as $t => $meta):
                $r = $rates_by_tier[$t] ?? ['hourly_rate_mwk'=>0,'description'=>''];
            ?>
            <div class="col-md-4">
              <div class="card border-<?= $meta['color'] ?> h-100">
                <div class="card-header bg-<?= $meta['color'] ?> bg-opacity-10 border-0">
                  <h6 class="mb-0 text-<?= $meta['color'] ?>"><i class="<?= $meta['icon'] ?> me-2"></i><?= $meta['label'] ?></h6>
                </div>
                <div class="card-body">
                  <div class="mb-3">
                    <label class="form-label fw-medium">Hourly Rate (MWK)</label>
                    <div class="input-group">
                      <span class="input-group-text">MWK</span>
                      <input type="number" name="rate_<?= $t ?>" class="form-control fw-bold"
                             value="<?= number_format($r['hourly_rate_mwk'], 2, '.', '') ?>"
                             min="0" step="50" required>
                    </div>
                  </div>
                  <div>
                    <label class="form-label fw-medium">Description</label>
                    <input type="text" name="desc_<?= $t ?>" class="form-control"
                           value="<?= h($r['description'] ?? '') ?>" placeholder="e.g. 0–2 years exp.">
                  </div>
                  <div class="mt-3 text-muted small">
                    ≈ USD <?= number_format($r['hourly_rate_mwk'] / (float)setting('usd_mwk_rate','1800'), 2) ?>/hr
                  </div>
                </div>
              </div>
            </div>
            <?php endforeach; ?>
          </div>
          <div class="mt-4 text-end">
            <button type="submit" class="btn btn-primary px-4">
              <i class="bi bi-save me-2"></i>Save Rates
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
