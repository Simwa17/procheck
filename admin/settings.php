<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

$user = require_admin();
$pdo  = db();

$settings_keys = [
    // Company
    'company_name'    => ['label'=>'Company / Studio Name',    'type'=>'text',     'section'=>'company'],
    'company_email'   => ['label'=>'Company Email',            'type'=>'email',    'section'=>'company'],
    'company_phone'   => ['label'=>'Company Phone',            'type'=>'text',     'section'=>'company'],
    'company_address' => ['label'=>'Company Address',          'type'=>'textarea', 'section'=>'company'],
    // Quote & currency
    'usd_mwk_rate'    => ['label'=>'USD to MWK Exchange Rate', 'type'=>'number',   'section'=>'quote',
                          'help'=>'e.g. 1800 means 1 USD = 1800 MWK'],
    'quote_prefix'    => ['label'=>'Quote Number Prefix',      'type'=>'text',     'section'=>'quote',
                          'help'=>'e.g. QT produces QT-2025-0001'],
    'quote_footer'    => ['label'=>'Quote Footer Text',        'type'=>'textarea', 'section'=>'quote',
                          'help'=>'Printed at the bottom of every quote'],
    // Mail / SMTP
    'mail_from_name'  => ['label'=>'From Name',                'type'=>'text',     'section'=>'mail',
                          'help'=>'Name shown as the sender in emails'],
    'mail_from_email' => ['label'=>'From Email Address',       'type'=>'email',    'section'=>'mail',
                          'help'=>'Must be authorised to send via your SMTP server'],
    'smtp_host'       => ['label'=>'SMTP Host',                'type'=>'text',     'section'=>'mail',
                          'help'=>'e.g. smtp.gmail.com or smtp.mailtrap.io — leave blank to use PHP mail()'],
    'smtp_port'       => ['label'=>'SMTP Port',                'type'=>'number',   'section'=>'mail',
                          'help'=>'587 (STARTTLS) · 465 (SSL) · 25 (none)'],
    'smtp_encryption' => ['label'=>'Encryption',               'type'=>'select',   'section'=>'mail',
                          'options'=>['tls'=>'STARTTLS (recommended)', 'ssl'=>'SSL', 'none'=>'None'],
                          'help'=>'Use STARTTLS for most providers (Gmail, Mailtrap, etc.)'],
    'smtp_user'       => ['label'=>'SMTP Username',            'type'=>'text',     'section'=>'mail'],
    'smtp_pass'       => ['label'=>'SMTP Password',            'type'=>'password', 'section'=>'mail',
                          'help'=>'Stored in the database. Use an app-specific password for Gmail.'],
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    foreach (array_keys($settings_keys) as $key) {
        // Don't overwrite password if left blank
        if ($key === 'smtp_pass' && ($_POST[$key] ?? '') === '') continue;
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

<?php
// Group keys by section
$sections = [
    'company' => ['title' => 'Company Information',      'icon' => 'bi-building'],
    'quote'   => ['title' => 'Quote & Currency Settings', 'icon' => 'bi-cash-coin'],
    'mail'    => ['title' => 'Mail / SMTP Settings',      'icon' => 'bi-envelope-at',
                  'hint'  => 'Needed for registration verification emails and sending quotes to clients.
                              Use <a href="https://mailtrap.io" target="_blank">Mailtrap</a> (free) to test,
                              or configure Gmail SMTP with an app-specific password.'],
];

function render_field(string $key, array $meta, array $all): void {
    $val = $all[$key] ?? '';
    echo '<div class="mb-3">';
    echo '<label class="form-label fw-medium">' . h($meta['label']) . '</label>';

    switch ($meta['type']) {
        case 'textarea':
            echo '<textarea name="' . $key . '" class="form-control" rows="3">' . h($val) . '</textarea>';
            break;
        case 'password':
            echo '<input type="password" name="' . $key . '" class="form-control" autocomplete="new-password"
                         placeholder="' . ($val ? '(saved — leave blank to keep)' : '') . '">';
            break;
        case 'select':
            echo '<select name="' . $key . '" class="form-select">';
            foreach ($meta['options'] as $optVal => $optLabel) {
                $sel = $val === (string)$optVal ? ' selected' : '';
                echo '<option value="' . h($optVal) . '"' . $sel . '>' . h($optLabel) . '</option>';
            }
            echo '</select>';
            break;
        default:
            $extra = $meta['type'] === 'number' ? ' step="1" min="1"' : '';
            echo '<input type="' . $meta['type'] . '" name="' . $key . '" class="form-control"
                         value="' . h($val) . '"' . $extra . '>';
    }

    if (!empty($meta['help'])) {
        echo '<div class="form-text">' . $meta['help'] . '</div>';
    }
    echo '</div>';
}
?>

<div class="row justify-content-center">
  <div class="col-md-8">
    <form method="POST">
      <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
      <?php foreach ($sections as $sectionKey => $section): ?>
      <div class="card border-0 shadow-sm mb-3" id="<?= $sectionKey ?>">
        <div class="card-header bg-white border-0 d-flex align-items-center gap-2 pb-0">
          <i class="<?= $section['icon'] ?> text-primary"></i>
          <h6 class="fw-semibold mb-0"><?= $section['title'] ?></h6>
        </div>
        <div class="card-body">
          <?php if (!empty($section['hint'])): ?>
          <div class="alert alert-info py-2 small mb-3">
            <i class="bi bi-info-circle me-1"></i><?= $section['hint'] ?>
          </div>
          <?php endif; ?>
          <?php foreach ($settings_keys as $key => $meta): ?>
            <?php if (($meta['section'] ?? '') === $sectionKey): ?>
              <?php render_field($key, $meta, $all_settings); ?>
            <?php endif; ?>
          <?php endforeach; ?>
        </div>
      </div>
      <?php endforeach; ?>

      <!-- Test email -->
      <div class="card border-0 shadow-sm mb-3">
        <div class="card-body">
          <h6 class="fw-semibold mb-1"><i class="bi bi-send-check me-2 text-success"></i>Test Email</h6>
          <p class="text-muted small mb-3">Save your SMTP settings first, then send a test email to verify the configuration.</p>
          <a href="<?= APP_URL ?>/admin/test_mail.php" class="btn btn-outline-success btn-sm">
            <i class="bi bi-send me-1"></i>Send Test Email
          </a>
        </div>
      </div>

      <div class="text-end">
        <button type="submit" class="btn btn-primary px-5">
          <i class="bi bi-save me-2"></i>Save Settings
        </button>
      </div>
    </form>
  </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
