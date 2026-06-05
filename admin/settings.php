<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

$user = require_admin();
$pdo  = db();

$settings_keys = [
    'company_name'    => ['label'=>'Company / Studio Name', 'type'=>'text'],
    'company_email'   => ['label'=>'Company Email', 'type'=>'email'],
    'company_phone'   => ['label'=>'Company Phone', 'type'=>'text'],
    'company_address' => ['label'=>'Company Address', 'type'=>'textarea'],
    'usd_mwk_rate'    => ['label'=>'USD to MWK Exchange Rate', 'type'=>'number', 'help'=>'e.g. 1800 means 1 USD = 1800 MWK'],
    'quote_prefix'    => ['label'=>'Quote Number Prefix', 'type'=>'text', 'help'=>'e.g. QT produces QT-2025-0001'],
    'quote_footer'    => ['label'=>'Quote Footer Text', 'type'=>'textarea', 'help'=>'Printed at the bottom of every quote'],
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    foreach (array_keys($settings_keys) as $key) {
        $val = trim($_POST[$key] ?? '');
        $stmt = $pdo->prepare('INSERT INTO settings (setting_key, setting_value) VALUES (?,?) ON DUPLICATE KEY UPDATE setting_value=?');
        $stmt->execute([$key, $val, $val]);
    }
    flash_set('success', 'Settings saved.');
    header('Location: ' . APP_URL . '/admin/settings.php');
    exit;
}

// Load all settings
$all_settings = [];
foreach ($pdo->query('SELECT setting_key, setting_value FROM settings') as $row) {
    $all_settings[$row['setting_key']] = $row['setting_value'];
}

$page_title = 'Settings';
include __DIR__ . '/../includes/header.php';
?>

<div class="page-header mb-4">
  <div class="d-flex justify-content-between align-items-center">
    <div>
      <h4 class="fw-bold mb-0"><i class="bi bi-gear me-2 text-primary"></i>System Settings</h4>
      <p class="text-muted mb-0">Configure ProCheck for your business</p>
    </div>
    <a href="<?= APP_URL ?>/admin/index.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i>Back</a>
  </div>
</div>

<div class="row justify-content-center">
  <div class="col-md-8">
    <div class="card border-0 shadow-sm">
      <div class="card-body p-4">
        <form method="POST">
          <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">

          <h6 class="text-muted text-uppercase small fw-semibold mb-3 border-bottom pb-2">Company Information</h6>
          <?php
          $company_keys = ['company_name','company_email','company_phone','company_address'];
          foreach ($company_keys as $key):
              $meta = $settings_keys[$key];
              $val  = $all_settings[$key] ?? '';
          ?>
          <div class="mb-3">
            <label class="form-label fw-medium"><?= $meta['label'] ?></label>
            <?php if ($meta['type'] === 'textarea'): ?>
              <textarea name="<?= $key ?>" class="form-control" rows="2"><?= h($val) ?></textarea>
            <?php else: ?>
              <input type="<?= $meta['type'] ?>" name="<?= $key ?>" class="form-control" value="<?= h($val) ?>">
            <?php endif; ?>
            <?php if (isset($meta['help'])): ?><div class="form-text"><?= $meta['help'] ?></div><?php endif; ?>
          </div>
          <?php endforeach; ?>

          <h6 class="text-muted text-uppercase small fw-semibold mb-3 border-bottom pb-2 mt-4">Quote & Currency Settings</h6>
          <?php
          $other_keys = ['usd_mwk_rate','quote_prefix','quote_footer'];
          foreach ($other_keys as $key):
              $meta = $settings_keys[$key];
              $val  = $all_settings[$key] ?? '';
          ?>
          <div class="mb-3">
            <label class="form-label fw-medium"><?= $meta['label'] ?></label>
            <?php if ($meta['type'] === 'textarea'): ?>
              <textarea name="<?= $key ?>" class="form-control" rows="3"><?= h($val) ?></textarea>
            <?php else: ?>
              <input type="<?= $meta['type'] ?>" name="<?= $key ?>" class="form-control" value="<?= h($val) ?>"
                     <?= $meta['type']==='number' ? 'step="1" min="1"' : '' ?>>
            <?php endif; ?>
            <?php if (isset($meta['help'])): ?><div class="form-text"><?= $meta['help'] ?></div><?php endif; ?>
          </div>
          <?php endforeach; ?>

          <div class="text-end mt-4">
            <button type="submit" class="btn btn-primary px-5">
              <i class="bi bi-save me-2"></i>Save Settings
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
