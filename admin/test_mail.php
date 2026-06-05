<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/mailer.php';

$user = require_admin();

$sent  = null;
$toEmail = $user['email'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $toEmail = trim($_POST['to_email'] ?? $user['email']);
    $sent = Mailer::send(
        $toEmail,
        $user['name'],
        'ProCheck — Test Email',
        Mailer::wrapTemplate(
            '<h2 style="margin:0 0 16px;color:#2563eb">Test Email ✓</h2>
            <p style="margin:0 0 12px">This is a test email from <strong>' . h(setting('company_name', 'ProCheck')) . '</strong>.</p>
            <p style="margin:0 0 12px">If you received this, your SMTP configuration is working correctly.</p>
            <ul style="color:#475569">
              <li>SMTP Host: <strong>' . h(setting('smtp_host', 'PHP mail()')) . '</strong></li>
              <li>Port: <strong>' . h(setting('smtp_port', 'N/A')) . '</strong></li>
              <li>Encryption: <strong>' . h(setting('smtp_encryption', 'N/A')) . '</strong></li>
              <li>From: <strong>' . h(setting('mail_from_email', 'N/A')) . '</strong></li>
            </ul>'
        )
    );
}

$page_title = 'Test Email';
include __DIR__ . '/../includes/header.php';
?>

<div class="page-header mb-4">
  <div class="d-flex justify-content-between align-items-center">
    <h4 class="fw-bold mb-0"><i class="bi bi-send-check me-2 text-success"></i>Send Test Email</h4>
    <a href="<?= APP_URL ?>/admin/settings.php#mail" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i>Back to Settings</a>
  </div>
</div>

<div class="row justify-content-center">
  <div class="col-md-6">
    <div class="card border-0 shadow-sm">
      <div class="card-body p-4">
        <?php if ($sent === true): ?>
          <div class="alert alert-success"><i class="bi bi-check-circle-fill me-2"></i>Test email sent to <strong><?= h($toEmail) ?></strong>. Check your inbox!</div>
        <?php elseif ($sent === false): ?>
          <div class="alert alert-danger"><i class="bi bi-x-circle-fill me-2"></i>Failed to send. Check your SMTP settings and the PHP error log.</div>
        <?php endif; ?>

        <form method="POST">
          <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
          <div class="mb-3">
            <label class="form-label fw-medium">Send Test To</label>
            <input type="email" name="to_email" class="form-control" value="<?= h($toEmail) ?>" required>
          </div>
          <button type="submit" class="btn btn-success w-100">
            <i class="bi bi-send me-2"></i>Send Test Email
          </button>
        </form>

        <div class="mt-4 p-3 bg-light rounded small text-muted">
          <strong>Current mail config:</strong><br>
          From: <?= h(setting('mail_from_email') ?: '(not set)') ?><br>
          SMTP: <?= h(setting('smtp_host') ?: 'PHP mail() (no SMTP configured)') ?><?= setting('smtp_host') ? ':' . h(setting('smtp_port', '587')) : '' ?>
        </div>
      </div>
    </div>
  </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
