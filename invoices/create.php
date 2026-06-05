<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

$user = require_login();
$pdo  = db();

// Generate invoice from an accepted quote
$quote_id = (int)($_GET['quote_id'] ?? 0);
$quote    = get_quote_with_items($quote_id, (int)$user['id']);

if (!$quote) {
    flash_set('error', 'Quote not found.');
    header('Location: ' . APP_URL . '/quotes/index.php');
    exit;
}
if ($quote['status'] !== 'accepted') {
    flash_set('error', 'Only accepted quotes can be converted to invoices.');
    header('Location: ' . APP_URL . '/quotes/view.php?id=' . $quote_id);
    exit;
}
if (get_invoice_for_quote($quote_id)) {
    flash_set('error', 'An invoice already exists for this quote.');
    header('Location: ' . APP_URL . '/quotes/view.php?id=' . $quote_id);
    exit;
}

$fin = compute_quote_financials($quote);
$default_due = date('Y-m-d', strtotime('+30 days'));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $due_date = $_POST['due_date'] ?? $default_due;
    $notes    = trim($_POST['notes'] ?? '');
    $inv_num  = next_invoice_number();

    $pdo->prepare('INSERT INTO invoices (quote_id, user_id, invoice_number, amount_mwk, due_date, notes)
                   VALUES (?,?,?,?,?,?)')
        ->execute([$quote_id, $user['id'], $inv_num, $fin['grand_total'], $due_date ?: null, $notes]);
    $inv_id = (int)$pdo->lastInsertId();

    flash_set('success', 'Invoice ' . $inv_num . ' created.');
    header('Location: ' . APP_URL . '/invoices/view.php?id=' . $inv_id);
    exit;
}

$page_title = 'Generate Invoice';
include __DIR__ . '/../includes/header.php';
?>

<div class="page-header mb-4">
  <div class="d-flex justify-content-between align-items-center">
    <h4 class="fw-bold mb-0"><i class="bi bi-receipt me-2 text-success"></i>Generate Invoice</h4>
    <a href="<?= APP_URL ?>/quotes/view.php?id=<?= $quote_id ?>" class="btn btn-outline-secondary btn-sm">
      <i class="bi bi-arrow-left me-1"></i>Back to Quote
    </a>
  </div>
</div>

<div class="row justify-content-center">
  <div class="col-md-7">
    <div class="card border-0 shadow-sm mb-3">
      <div class="card-body">
        <h6 class="fw-semibold mb-3">Invoice Summary</h6>
        <div class="d-flex justify-content-between mb-1">
          <span class="text-muted">Quote:</span><strong><?= h($quote['quote_number']) ?></strong>
        </div>
        <div class="d-flex justify-content-between mb-1">
          <span class="text-muted">Project:</span><strong><?= h($quote['project_name']) ?></strong>
        </div>
        <?php if ($quote['client_name']): ?>
        <div class="d-flex justify-content-between mb-1">
          <span class="text-muted">Client:</span><strong><?= h($quote['client_name']) ?></strong>
        </div>
        <?php endif; ?>
        <hr class="my-2">
        <div class="d-flex justify-content-between align-items-center">
          <span class="fw-bold">Amount:</span>
          <span class="fw-bold fs-5 text-success"><?= format_mwk($fin['grand_total']) ?></span>
        </div>
      </div>
    </div>

    <div class="card border-0 shadow-sm">
      <div class="card-body">
        <form method="POST">
          <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
          <div class="mb-3">
            <label class="form-label fw-medium">Due Date</label>
            <input type="date" name="due_date" class="form-control" value="<?= $default_due ?>">
            <div class="form-text">Defaults to 30 days from today.</div>
          </div>
          <div class="mb-3">
            <label class="form-label fw-medium">Notes (optional)</label>
            <textarea name="notes" class="form-control" rows="3"
                      placeholder="e.g. Payment via Airtel Money: 0999 xxx xxx"><?= h($quote['notes'] ?? '') ?></textarea>
          </div>
          <button type="submit" class="btn btn-success w-100">
            <i class="bi bi-receipt me-2"></i>Create Invoice
          </button>
        </form>
      </div>
    </div>
  </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
