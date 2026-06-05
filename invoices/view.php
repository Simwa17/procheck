<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

$user = require_login();
$pdo  = db();
$id   = (int)($_GET['id'] ?? 0);

// POST: record payment or update status
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = $_POST['action'] ?? '';

    // Ownership check
    $chk = $pdo->prepare('SELECT user_id FROM invoices WHERE id = ?');
    $chk->execute([$id]);
    $owned = $chk->fetch();
    if (!$owned || (int)$owned['user_id'] !== (int)$user['id']) {
        flash_set('error', 'Access denied.');
        header('Location: ' . APP_URL . '/invoices/index.php');
        exit;
    }

    if ($action === 'add_payment') {
        $amt    = max(0, (float)($_POST['amount_mwk'] ?? 0));
        $date   = $_POST['payment_date'] ?? date('Y-m-d');
        $method = trim($_POST['method'] ?? 'Cash');
        $ref    = trim($_POST['reference'] ?? '');
        $notes  = trim($_POST['notes'] ?? '');

        if ($amt > 0) {
            $pdo->prepare('INSERT INTO payments (invoice_id, amount_mwk, payment_date, method, reference, notes)
                           VALUES (?,?,?,?,?,?)')
                ->execute([$id, $amt, $date, $method, $ref, $notes]);

            // Update amount_paid_mwk and derive status
            $inv = $pdo->prepare('SELECT amount_mwk FROM invoices WHERE id = ?');
            $inv->execute([$id]);
            $total_inv = (float)$inv->fetch()['amount_mwk'];

            $paid_sum = (float)$pdo->query("SELECT COALESCE(SUM(amount_mwk),0) FROM payments WHERE invoice_id=$id")->fetchColumn();
            $new_status = $paid_sum <= 0 ? 'unpaid' : ($paid_sum >= $total_inv ? 'paid' : 'partial');

            $pdo->prepare('UPDATE invoices SET amount_paid_mwk=?, status=? WHERE id=?')
                ->execute([$paid_sum, $new_status, $id]);

            flash_set('success', 'Payment of ' . format_mwk($amt) . ' recorded.');
        } else {
            flash_set('error', 'Amount must be greater than 0.');
        }

    } elseif ($action === 'delete_payment') {
        $pid = (int)($_POST['payment_id'] ?? 0);
        $pdo->prepare('DELETE FROM payments WHERE id = ? AND invoice_id = ?')->execute([$pid, $id]);

        // Recalc
        $inv = $pdo->prepare('SELECT amount_mwk FROM invoices WHERE id = ?');
        $inv->execute([$id]);
        $total_inv = (float)$inv->fetch()['amount_mwk'];
        $paid_sum  = (float)$pdo->query("SELECT COALESCE(SUM(amount_mwk),0) FROM payments WHERE invoice_id=$id")->fetchColumn();
        $new_status = $paid_sum <= 0 ? 'unpaid' : ($paid_sum >= $total_inv ? 'paid' : 'partial');
        $pdo->prepare('UPDATE invoices SET amount_paid_mwk=?, status=? WHERE id=?')
            ->execute([$paid_sum, $new_status, $id]);

        flash_set('success', 'Payment deleted.');

    } elseif ($action === 'update_due_date') {
        $due = $_POST['due_date'] ?? '';
        $pdo->prepare('UPDATE invoices SET due_date=? WHERE id=?')->execute([$due ?: null, $id]);
        flash_set('success', 'Due date updated.');
    }

    header('Location: ' . APP_URL . '/invoices/view.php?id=' . $id);
    exit;
}

$invoice = get_invoice($id, (int)$user['id']);
if (!$invoice) {
    flash_set('error', 'Invoice not found.');
    header('Location: ' . APP_URL . '/invoices/index.php');
    exit;
}

$pct_paid   = $invoice['amount_mwk'] > 0
                ? min(100, round((float)$invoice['amount_paid_mwk'] / (float)$invoice['amount_mwk'] * 100))
                : 0;
$balance    = max(0, (float)$invoice['amount_mwk'] - (float)$invoice['amount_paid_mwk']);
$is_overdue = $invoice['due_date'] && $invoice['due_date'] < date('Y-m-d') && $invoice['status'] !== 'paid';

$page_title = 'Invoice ' . $invoice['invoice_number'];
include __DIR__ . '/../includes/header.php';
?>

<div class="page-header mb-4">
  <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
    <div>
      <h4 class="fw-bold mb-0">
        <i class="bi bi-receipt me-2 text-success"></i><?= h($invoice['invoice_number']) ?>
        <?= badge_invoice_status($invoice['status']) ?>
        <?php if ($is_overdue): ?><span class="badge bg-danger">Overdue</span><?php endif; ?>
      </h4>
      <p class="text-muted mb-0"><?= h($invoice['project_name']) ?></p>
    </div>
    <div class="d-flex gap-2 flex-wrap">
      <a href="<?= APP_URL ?>/quotes/view.php?id=<?= $invoice['quote_id'] ?>" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-file-earmark-text me-1"></i>View Quote
      </a>
      <a href="<?= APP_URL ?>/invoices/index.php" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-arrow-left me-1"></i>All Invoices
      </a>
    </div>
  </div>
</div>

<div class="row g-3">
<div class="col-lg-8">

  <!-- Invoice detail -->
  <div class="card border-0 shadow-sm mb-3">
    <div class="card-body">
      <div class="row">
        <div class="col-md-6">
          <h6 class="text-muted small text-uppercase fw-semibold">Invoice Details</h6>
          <table class="table table-sm table-borderless mb-0">
            <tr><td class="text-muted ps-0" style="width:100px">Number:</td><td><?= h($invoice['invoice_number']) ?></td></tr>
            <tr><td class="text-muted ps-0">Quote:</td><td><?= h($invoice['quote_number']) ?></td></tr>
            <tr><td class="text-muted ps-0">Issued:</td><td><?= date('d M Y', strtotime($invoice['issued_at'])) ?></td></tr>
            <tr>
              <td class="text-muted ps-0">Due:</td>
              <td>
                <form method="POST" class="d-flex gap-1 align-items-center">
                  <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                  <input type="hidden" name="action" value="update_due_date">
                  <input type="date" name="due_date" class="form-control form-control-sm"
                         style="width:145px" value="<?= h($invoice['due_date'] ?? '') ?>">
                  <button class="btn btn-sm btn-outline-secondary py-0 px-2">Set</button>
                </form>
              </td>
            </tr>
          </table>
        </div>
        <div class="col-md-6">
          <h6 class="text-muted small text-uppercase fw-semibold">Bill To</h6>
          <?php if ($invoice['client_name']): ?>
            <div class="fw-semibold"><?= h($invoice['client_name']) ?></div>
            <?php if ($invoice['client_company']): ?><div class="text-muted"><?= h($invoice['client_company']) ?></div><?php endif; ?>
            <?php if ($invoice['client_email']): ?><div class="small"><i class="bi bi-envelope me-1"></i><?= h($invoice['client_email']) ?></div><?php endif; ?>
            <?php if ($invoice['client_phone']): ?><div class="small"><i class="bi bi-telephone me-1"></i><?= h($invoice['client_phone']) ?></div><?php endif; ?>
          <?php else: ?><span class="text-muted small">No client on record</span><?php endif; ?>
        </div>
      </div>
      <?php if ($invoice['notes']): ?>
      <hr class="my-2">
      <div class="text-muted small"><strong>Notes:</strong> <?= nl2br(h($invoice['notes'])) ?></div>
      <?php endif; ?>
    </div>
  </div>

  <!-- Payment history -->
  <div class="card border-0 shadow-sm mb-3">
    <div class="card-header bg-white border-0 fw-semibold">
      <i class="bi bi-clock-history me-2 text-primary"></i>Payment History
    </div>
    <div class="card-body p-0">
      <?php if ($invoice['payments']): ?>
      <table class="table table-hover align-middle mb-0">
        <thead class="table-light">
          <tr>
            <th>Date</th>
            <th>Method</th>
            <th>Reference</th>
            <th class="text-end">Amount (MWK)</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($invoice['payments'] as $pay): ?>
          <tr>
            <td><?= date('d M Y', strtotime($pay['payment_date'])) ?></td>
            <td><?= h($pay['method']) ?></td>
            <td class="text-muted small"><?= h($pay['reference'] ?: '—') ?></td>
            <td class="text-end fw-semibold text-success"><?= format_mwk((float)$pay['amount_mwk']) ?></td>
            <td>
              <form method="POST" class="d-inline">
                <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                <input type="hidden" name="action" value="delete_payment">
                <input type="hidden" name="payment_id" value="<?= $pay['id'] ?>">
                <button type="submit" class="btn btn-link btn-sm text-danger p-0"
                        onclick="return confirm('Delete this payment record?')">
                  <i class="bi bi-trash"></i>
                </button>
              </form>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      <?php else: ?>
      <p class="text-muted small p-3 mb-0 fst-italic">No payments recorded yet.</p>
      <?php endif; ?>
    </div>
  </div>

  <!-- Record payment form -->
  <?php if ($invoice['status'] !== 'paid'): ?>
  <div class="card border-0 shadow-sm">
    <div class="card-body">
      <h6 class="fw-semibold mb-3"><i class="bi bi-plus-circle me-2 text-success"></i>Record Payment</h6>
      <form method="POST">
        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
        <input type="hidden" name="action" value="add_payment">
        <div class="row g-2">
          <div class="col-md-3">
            <label class="form-label small fw-medium">Amount (MWK)</label>
            <input type="number" name="amount_mwk" class="form-control form-control-sm"
                   min="1" step="1" value="<?= round($balance) ?>" required>
          </div>
          <div class="col-md-2">
            <label class="form-label small fw-medium">Date</label>
            <input type="date" name="payment_date" class="form-control form-control-sm" value="<?= date('Y-m-d') ?>">
          </div>
          <div class="col-md-3">
            <label class="form-label small fw-medium">Method</label>
            <select name="method" class="form-select form-select-sm">
              <option>Cash</option>
              <option>Airtel Money</option>
              <option>TNM Mpamba</option>
              <option>Bank Transfer</option>
              <option>Cheque</option>
              <option>Other</option>
            </select>
          </div>
          <div class="col-md-4">
            <label class="form-label small fw-medium">Reference / Receipt #</label>
            <input type="text" name="reference" class="form-control form-control-sm" placeholder="Optional">
          </div>
          <div class="col-12">
            <label class="form-label small fw-medium">Notes</label>
            <input type="text" name="notes" class="form-control form-control-sm" placeholder="Optional notes">
          </div>
          <div class="col-12">
            <button type="submit" class="btn btn-success btn-sm px-4">
              <i class="bi bi-check2 me-1"></i>Record Payment
            </button>
          </div>
        </div>
      </form>
    </div>
  </div>
  <?php endif; ?>

</div>
<div class="col-lg-4">

  <!-- Status summary -->
  <div class="card border-0 shadow-sm mb-3">
    <div class="card-header bg-success text-white border-0">
      <h6 class="mb-0"><i class="bi bi-cash-coin me-2"></i>Payment Status</h6>
    </div>
    <div class="card-body">
      <div class="d-flex justify-content-between mb-1">
        <span class="text-muted small">Invoice total:</span>
        <strong><?= format_mwk((float)$invoice['amount_mwk']) ?></strong>
      </div>
      <div class="d-flex justify-content-between mb-1">
        <span class="text-muted small">Paid so far:</span>
        <strong class="text-success"><?= format_mwk((float)$invoice['amount_paid_mwk']) ?></strong>
      </div>
      <div class="d-flex justify-content-between mb-2">
        <span class="text-muted small">Balance due:</span>
        <strong class="<?= $balance > 0 ? 'text-danger' : 'text-success' ?>"><?= format_mwk($balance) ?></strong>
      </div>
      <div class="progress mb-2" style="height:10px">
        <div class="progress-bar bg-success" style="width:<?= $pct_paid ?>%"></div>
      </div>
      <div class="text-center small text-muted"><?= $pct_paid ?>% paid</div>
    </div>
  </div>

  <?php if ($is_overdue): ?>
  <div class="alert alert-danger small">
    <i class="bi bi-exclamation-triangle-fill me-1"></i>
    <strong>Overdue</strong> — payment was due <?= date('d M Y', strtotime($invoice['due_date'])) ?>.
  </div>
  <?php endif; ?>

</div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
