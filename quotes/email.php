<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/mailer.php';

$user  = require_login();
$id    = (int)($_GET['id'] ?? 0);
$quote = get_quote_with_items($id, $user['id']);

if (!$quote) {
    flash_set('error', 'Quote not found.');
    header('Location: ' . APP_URL . '/quotes/index.php');
    exit;
}

$errors  = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $to_email = trim($_POST['to_email'] ?? '');
    $to_name  = trim($_POST['to_name']  ?? '');
    $message  = trim($_POST['message']  ?? '');

    if (!filter_var($to_email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'A valid recipient email address is required.';
    }
    if (!setting('mail_from_email')) {
        $errors[] = 'SMTP / mail is not configured yet. Go to Admin → Settings → Mail to set it up.';
    }

    if (!$errors) {
        $viewLink = APP_URL . '/quotes/print.php?id=' . $quote['id'];

        $sent = Mailer::send(
            $to_email,
            $to_name ?: ($quote['client_name'] ?? $to_email),
            'Quotation ' . $quote['quote_number'] . ' from ' . setting('company_name', 'ProCheck'),
            Mailer::quoteEmailBody($quote, $message, $viewLink)
        );

        if ($sent) {
            // Update status to "sent" if it was draft
            if ($quote['status'] === 'draft') {
                db()->prepare('UPDATE quotes SET status = ? WHERE id = ?')->execute(['sent', $quote['id']]);
            }
            flash_set('success', 'Quote emailed successfully to ' . $to_email);
            header('Location: ' . APP_URL . '/quotes/view.php?id=' . $quote['id']);
            exit;
        } else {
            $errors[] = 'Failed to send email. Check Admin → Settings → Mail and review your server error log.';
        }
    }
}

// Pre-fill from client
$default_email = $quote['client_email'] ?? '';
$default_name  = $quote['client_name']  ?? '';

$page_title = 'Email Quote';
include __DIR__ . '/../includes/header.php';
?>

<div class="page-header mb-4">
  <div class="d-flex justify-content-between align-items-center">
    <div>
      <h4 class="fw-bold mb-0"><i class="bi bi-envelope-arrow-up me-2 text-primary"></i>Email Quote to Client</h4>
      <p class="text-muted mb-0"><?= h($quote['quote_number']) ?> — <?= h($quote['project_name']) ?></p>
    </div>
    <a href="<?= APP_URL ?>/quotes/view.php?id=<?= $quote['id'] ?>" class="btn btn-outline-secondary btn-sm">
      <i class="bi bi-arrow-left me-1"></i>Back to Quote
    </a>
  </div>
</div>

<div class="row g-3 justify-content-center">
  <div class="col-md-7">
    <div class="card border-0 shadow-sm">
      <div class="card-body p-4">

        <?php if (!setting('mail_from_email')): ?>
        <div class="alert alert-warning">
          <i class="bi bi-exclamation-triangle-fill me-2"></i>
          <strong>Email not configured.</strong>
          <a href="<?= APP_URL ?>/admin/settings.php#mail">Go to Admin → Settings</a> and fill in your SMTP details first.
        </div>
        <?php endif; ?>

        <?php foreach ($errors as $e): ?>
          <div class="alert alert-danger py-2"><i class="bi bi-x-circle me-1"></i><?= h($e) ?></div>
        <?php endforeach; ?>

        <form method="POST">
          <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">

          <div class="mb-3">
            <label class="form-label fw-medium">Recipient Email <span class="text-danger">*</span></label>
            <div class="input-group">
              <span class="input-group-text"><i class="bi bi-envelope"></i></span>
              <input type="email" name="to_email" class="form-control"
                     value="<?= h($_POST['to_email'] ?? $default_email) ?>"
                     placeholder="client@example.com" required autofocus>
            </div>
          </div>

          <div class="mb-3">
            <label class="form-label fw-medium">Recipient Name</label>
            <input type="text" name="to_name" class="form-control"
                   value="<?= h($_POST['to_name'] ?? $default_name) ?>"
                   placeholder="Client name (optional)">
          </div>

          <div class="mb-4">
            <label class="form-label fw-medium">Personal Message <span class="text-muted small">(optional)</span></label>
            <textarea name="message" class="form-control" rows="4"
                      placeholder="Hi, please find attached your project quotation. We look forward to working with you!"><?= h($_POST['message'] ?? '') ?></textarea>
            <div class="form-text">This appears above the quote table in the email.</div>
          </div>

          <div class="d-flex gap-2">
            <button type="submit" class="btn btn-primary px-4" <?= !setting('mail_from_email') ? 'disabled' : '' ?>>
              <i class="bi bi-send me-2"></i>Send Email
            </button>
            <a href="<?= APP_URL ?>/quotes/view.php?id=<?= $quote['id'] ?>" class="btn btn-outline-secondary">
              Cancel
            </a>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- Quote summary sidebar -->
  <div class="col-md-4">
    <div class="card border-0 shadow-sm">
      <div class="card-header bg-primary text-white border-0">
        <h6 class="mb-0"><i class="bi bi-receipt me-2"></i>Quote Summary</h6>
      </div>
      <div class="card-body small">
        <div class="d-flex justify-content-between mb-1"><span class="text-muted">Ref:</span><strong><?= h($quote['quote_number']) ?></strong></div>
        <div class="d-flex justify-content-between mb-1"><span class="text-muted">Project:</span><span><?= h($quote['project_name']) ?></span></div>
        <div class="d-flex justify-content-between mb-1"><span class="text-muted">Modules:</span><span><?= count($quote['items']) ?></span></div>
        <div class="d-flex justify-content-between mb-1"><span class="text-muted">Hours:</span><span><?= number_format($quote['total_hours'], 1) ?> hrs</span></div>
        <hr class="my-2">
        <div class="d-flex justify-content-between"><span class="fw-semibold">Total:</span><strong class="text-primary"><?= format_mwk($quote['total_mwk']) ?></strong></div>
        <div class="d-flex justify-content-between text-muted"><span>≈</span><span><?= format_usd($quote['total_usd']) ?></span></div>
      </div>
    </div>
  </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
