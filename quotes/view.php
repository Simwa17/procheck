<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

$user = require_login();

// Handle status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    csrf_verify();
    $id = (int)($_POST['quote_id'] ?? 0);
    if ($_POST['action'] === 'update_status') {
        $status = in_array($_POST['status'], ['draft','sent','accepted','rejected']) ? $_POST['status'] : 'draft';
        $stmt = db()->prepare('UPDATE quotes SET status = ? WHERE id = ? AND user_id = ?');
        $stmt->execute([$status, $id, $user['id']]);
        flash_set('success', 'Quote status updated.');
    }
    header('Location: ' . APP_URL . '/quotes/view.php?id=' . $id);
    exit;
}

$id    = (int)($_GET['id'] ?? 0);
$quote = get_quote_with_items($id, $user['id']);

if (!$quote) {
    flash_set('error', 'Quote not found or access denied.');
    header('Location: ' . APP_URL . '/quotes/index.php');
    exit;
}

$page_title = 'Quote ' . $quote['quote_number'];
$usd_rate   = (float)setting('usd_mwk_rate', '1800');
include __DIR__ . '/../includes/header.php';
?>

<div class="page-header mb-4">
  <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
    <div>
      <h4 class="fw-bold mb-0">
        <i class="bi bi-file-earmark-text me-2 text-primary"></i>
        <?= h($quote['quote_number']) ?>
      </h4>
      <p class="text-muted mb-0"><?= h($quote['project_name']) ?></p>
    </div>
    <div class="d-flex gap-2 flex-wrap">
      <a href="<?= APP_URL ?>/quotes/print.php?id=<?= $quote['id'] ?>" class="btn btn-outline-secondary" target="_blank">
        <i class="bi bi-printer me-1"></i>Print / PDF
      </a>
      <a href="<?= APP_URL ?>/quotes/index.php" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>Back
      </a>
    </div>
  </div>
</div>

<div class="row g-3">
  <!-- Main quote content -->
  <div class="col-lg-8">
    <!-- Header info -->
    <div class="card border-0 shadow-sm mb-3">
      <div class="card-body">
        <div class="row">
          <div class="col-md-6">
            <h6 class="text-muted small text-uppercase fw-semibold">Project Details</h6>
            <table class="table table-sm table-borderless mb-0">
              <tr><td class="text-muted ps-0">Type:</td><td><?= h($quote['project_type_name'] ?? 'N/A') ?></td></tr>
              <tr><td class="text-muted ps-0">Developer:</td><td><?= tier_badge($quote['developer_tier']) ?></td></tr>
              <tr><td class="text-muted ps-0">Status:</td><td><?= badge_status($quote['status']) ?></td></tr>
              <tr><td class="text-muted ps-0">Created:</td><td><?= date('d M Y', strtotime($quote['created_at'])) ?></td></tr>
              <?php if ($quote['valid_until']): ?>
              <tr><td class="text-muted ps-0">Valid Until:</td><td><?= date('d M Y', strtotime($quote['valid_until'])) ?></td></tr>
              <?php endif; ?>
            </table>
          </div>
          <div class="col-md-6">
            <h6 class="text-muted small text-uppercase fw-semibold">Client</h6>
            <?php if ($quote['client_name']): ?>
              <div class="fw-semibold"><?= h($quote['client_name']) ?></div>
              <?php if ($quote['client_company']): ?><div class="text-muted"><?= h($quote['client_company']) ?></div><?php endif; ?>
              <?php if ($quote['client_email']): ?><div class="small"><i class="bi bi-envelope me-1"></i><?= h($quote['client_email']) ?></div><?php endif; ?>
              <?php if ($quote['client_phone']): ?><div class="small"><i class="bi bi-telephone me-1"></i><?= h($quote['client_phone']) ?></div><?php endif; ?>
              <?php if ($quote['client_address']): ?><div class="small text-muted"><?= h($quote['client_address']) ?></div><?php endif; ?>
            <?php else: ?>
              <span class="text-muted">No client assigned</span>
            <?php endif; ?>
          </div>
        </div>
        <?php if ($quote['notes']): ?>
        <hr>
        <div class="text-muted small"><strong>Notes:</strong> <?= nl2br(h($quote['notes'])) ?></div>
        <?php endif; ?>
      </div>
    </div>

    <!-- Line Items -->
    <div class="card border-0 shadow-sm mb-3">
      <div class="card-header bg-white border-0">
        <h6 class="fw-semibold mb-0"><i class="bi bi-list-check me-2 text-primary"></i>Project Modules & Breakdown</h6>
      </div>
      <div class="card-body p-0">
        <div class="table-responsive">
          <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
              <tr>
                <th>Module / Feature</th>
                <th class="text-center">Complexity</th>
                <th class="text-end">Hours</th>
                <th class="text-end">Rate (MWK/hr)</th>
                <th class="text-end">Subtotal (MWK)</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($quote['items'] as $item): ?>
              <tr>
                <td>
                  <div class="fw-medium"><?= h($item['module_name']) ?></div>
                  <?php if ($item['description']): ?>
                    <div class="text-muted small"><?= h($item['description']) ?></div>
                  <?php endif; ?>
                </td>
                <td class="text-center">
                  <?php
                  $cx_map = ['simple'=>'info','medium'=>'warning','complex'=>'danger'];
                  $cx_col = $cx_map[$item['complexity']] ?? 'secondary';
                  ?>
                  <span class="badge bg-<?= $cx_col ?>"><?= ucfirst($item['complexity']) ?></span>
                </td>
                <td class="text-end"><?= number_format($item['hours'], 1) ?></td>
                <td class="text-end"><?= number_format($item['rate_mwk'], 0, '.', ',') ?></td>
                <td class="text-end fw-semibold"><?= format_mwk($item['total_mwk']) ?></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
            <tfoot class="table-light">
              <tr>
                <td colspan="2" class="text-muted">Total Hours</td>
                <td class="text-end fw-bold"><?= number_format($quote['total_hours'], 1) ?></td>
                <td colspan="2"></td>
              </tr>
              <tr>
                <td colspan="4" class="text-end text-muted">Subtotal</td>
                <td class="text-end fw-semibold"><?= format_mwk($quote['subtotal_mwk']) ?></td>
              </tr>
              <?php if ($quote['margin_percent'] > 0): ?>
              <tr>
                <td colspan="4" class="text-end text-muted">Margin (<?= number_format($quote['margin_percent'], 0) ?>%)</td>
                <td class="text-end"><?= format_mwk($quote['total_mwk'] - $quote['subtotal_mwk']) ?></td>
              </tr>
              <?php endif; ?>
              <tr class="table-primary">
                <td colspan="4" class="text-end fw-bold fs-6">Total (MWK)</td>
                <td class="text-end fw-bold fs-5"><?= format_mwk($quote['total_mwk']) ?></td>
              </tr>
              <tr>
                <td colspan="4" class="text-end text-muted small">≈ USD (@ 1 USD = <?= number_format($quote['usd_rate'], 0) ?> MWK)</td>
                <td class="text-end text-muted small fw-semibold"><?= format_usd($quote['total_usd']) ?></td>
              </tr>
            </tfoot>
          </table>
        </div>
      </div>
    </div>
  </div>

  <!-- Sidebar -->
  <div class="col-lg-4">
    <!-- Summary card -->
    <div class="card border-0 shadow-sm mb-3">
      <div class="card-header bg-primary text-white border-0">
        <h6 class="mb-0"><i class="bi bi-receipt me-2"></i>Quote Summary</h6>
      </div>
      <div class="card-body">
        <div class="d-flex justify-content-between mb-2">
          <span class="text-muted">Total Hours:</span>
          <strong><?= number_format($quote['total_hours'], 1) ?> hrs</strong>
        </div>
        <div class="d-flex justify-content-between mb-2">
          <span class="text-muted">Developer:</span>
          <?= tier_badge($quote['developer_tier']) ?>
        </div>
        <div class="d-flex justify-content-between mb-2">
          <span class="text-muted">Subtotal:</span>
          <strong><?= format_mwk($quote['subtotal_mwk']) ?></strong>
        </div>
        <?php if ($quote['margin_percent'] > 0): ?>
        <div class="d-flex justify-content-between mb-2">
          <span class="text-muted">Margin (<?= number_format($quote['margin_percent'], 0) ?>%):</span>
          <strong><?= format_mwk($quote['total_mwk'] - $quote['subtotal_mwk']) ?></strong>
        </div>
        <?php endif; ?>
        <hr>
        <div class="d-flex justify-content-between align-items-center">
          <span class="fw-bold">Total (MWK):</span>
          <span class="fw-bold fs-5 text-primary"><?= format_mwk($quote['total_mwk']) ?></span>
        </div>
        <div class="d-flex justify-content-between text-muted small mt-1">
          <span>≈ USD:</span>
          <span><?= format_usd($quote['total_usd']) ?></span>
        </div>
      </div>
    </div>

    <!-- Status Update -->
    <div class="card border-0 shadow-sm mb-3">
      <div class="card-body">
        <h6 class="fw-semibold mb-3"><i class="bi bi-arrow-repeat me-2 text-warning"></i>Update Status</h6>
        <form method="POST">
          <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
          <input type="hidden" name="action" value="update_status">
          <input type="hidden" name="quote_id" value="<?= $quote['id'] ?>">
          <select name="status" class="form-select mb-2">
            <?php foreach (['draft','sent','accepted','rejected'] as $s): ?>
            <option value="<?= $s ?>" <?= $quote['status'] === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
            <?php endforeach; ?>
          </select>
          <button type="submit" class="btn btn-warning w-100 btn-sm">Update Status</button>
        </form>
      </div>
    </div>

    <div class="d-grid gap-2">
      <a href="<?= APP_URL ?>/quotes/print.php?id=<?= $quote['id'] ?>" class="btn btn-primary" target="_blank">
        <i class="bi bi-printer me-2"></i>Print / Export PDF
      </a>
      <a href="<?= APP_URL ?>/quotes/create.php" class="btn btn-outline-primary">
        <i class="bi bi-plus me-2"></i>New Quote
      </a>
    </div>
  </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
