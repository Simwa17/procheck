<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

$user = require_login();
$pdo  = db();
$id   = (int)($_GET['id'] ?? $_POST['quote_id'] ?? 0);

// ── POST actions ──────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = $_POST['action'] ?? '';

    // Ownership check
    $chk = $pdo->prepare('SELECT user_id FROM quotes WHERE id = ?');
    $chk->execute([$id]);
    $owned = $chk->fetch();
    if (!$owned || (int)$owned['user_id'] !== (int)$user['id']) {
        flash_set('error', 'Access denied.');
        header('Location: ' . APP_URL . '/quotes/index.php');
        exit;
    }

    switch ($action) {
        case 'update_status':
            $status = in_array($_POST['status'] ?? '', ['draft','sent','accepted','rejected']) ? $_POST['status'] : 'draft';
            $pdo->prepare('UPDATE quotes SET status=? WHERE id=?')->execute([$status, $id]);
            flash_set('success', 'Status updated to ' . ucfirst($status) . '.');
            break;
        case 'update_expiry':
            $date = $_POST['valid_until'] ?? '';
            $pdo->prepare('UPDATE quotes SET valid_until=? WHERE id=?')->execute([$date ?: null, $id]);
            flash_set('success', 'Expiry date updated.');
            break;
        case 'update_discount_tax':
            $dt = in_array($_POST['discount_type'] ?? '', ['percent','fixed']) ? $_POST['discount_type'] : 'percent';
            $dv = max(0, (float)($_POST['discount_value'] ?? 0));
            $tr = max(0, min(100, (float)($_POST['tax_rate'] ?? 0)));
            $pdo->prepare('UPDATE quotes SET discount_type=?,discount_value=?,tax_rate=? WHERE id=?')->execute([$dt,$dv,$tr,$id]);
            recalc_quote_totals($id);
            flash_set('success', 'Discount & tax updated.');
            break;
        case 'add_custom_item':
            $name  = trim($_POST['item_name'] ?? '');
            $desc  = trim($_POST['item_desc'] ?? '');
            $price = max(0, (float)($_POST['item_price'] ?? 0));
            if ($name && $price > 0) {
                $pdo->prepare('INSERT INTO quote_items (quote_id,item_type,module_name,description,complexity,hours,rate_mwk,total_mwk) VALUES (?,"custom",?,?,"medium",0,0,?)')->execute([$id,$name,$desc,$price]);
                recalc_quote_totals($id);
                flash_set('success', 'Custom item added.');
            } else { flash_set('error', 'Provide a name and price > 0.'); }
            break;
        case 'delete_item':
            $iid = (int)($_POST['item_id'] ?? 0);
            $pdo->prepare('DELETE FROM quote_items WHERE id=? AND quote_id=?')->execute([$iid,$id]);
            recalc_quote_totals($id);
            flash_set('success', 'Item removed.');
            break;
        case 'duplicate':
            $new_id = duplicate_quote($id, (int)$user['id']);
            flash_set('success', 'Quote duplicated.');
            header('Location: ' . APP_URL . '/quotes/view.php?id=' . $new_id);
            exit;
        case 'generate_portal_token':
            $tok = bin2hex(random_bytes(32));
            $pdo->prepare('UPDATE quotes SET public_token=? WHERE id=?')->execute([$tok,$id]);
            flash_set('success', 'Client portal link generated.');
            break;
        case 'revoke_portal_token':
            $pdo->prepare('UPDATE quotes SET public_token=NULL WHERE id=?')->execute([$id]);
            flash_set('success', 'Portal link revoked.');
            break;
        case 'add_milestone':
            $title = trim($_POST['ms_title'] ?? '');
            $desc  = trim($_POST['ms_desc'] ?? '');
            $pct   = max(0, min(100, (float)($_POST['ms_percent'] ?? 0)));
            $due   = $_POST['ms_due'] ?? '';
            if ($title && $pct > 0) {
                $ord = (int)$pdo->query("SELECT COALESCE(MAX(sort_order),0)+1 FROM quote_milestones WHERE quote_id=$id")->fetchColumn();
                $tot = (float)$pdo->query("SELECT total_mwk FROM quotes WHERE id=$id")->fetchColumn();
                $amt = round($tot * $pct / 100, 2);
                $pdo->prepare('INSERT INTO quote_milestones (quote_id,title,description,percent,amount_mwk,due_date,sort_order) VALUES (?,?,?,?,?,?,?)')->execute([$id,$title,$desc,$pct,$amt,$due ?: null,$ord]);
                flash_set('success', 'Milestone added.');
            } else { flash_set('error', 'Provide a title and a percentage > 0.'); }
            break;
        case 'update_milestone_status':
            $mid  = (int)($_POST['milestone_id'] ?? 0);
            $ms   = in_array($_POST['ms_status'] ?? '', ['pending','invoiced','paid']) ? $_POST['ms_status'] : 'pending';
            $pdo->prepare('UPDATE quote_milestones SET status=? WHERE id=? AND quote_id=?')->execute([$ms,$mid,$id]);
            flash_set('success', 'Milestone status updated.');
            break;
        case 'delete_milestone':
            $mid = (int)($_POST['milestone_id'] ?? 0);
            $pdo->prepare('DELETE FROM quote_milestones WHERE id=? AND quote_id=?')->execute([$mid,$id]);
            flash_set('success', 'Milestone deleted.');
            break;
        case 'save_revision':
            $note = trim($_POST['revision_note'] ?? '');
            save_quote_revision($id, (int)$user['id'], $note);
            flash_set('success', 'Revision snapshot saved.');
            break;
    }
    $tab = $_POST['return_tab'] ?? '';
    header('Location: ' . APP_URL . '/quotes/view.php?id=' . $id . ($tab ? '#' . $tab : ''));
    exit;
}

// ── Load data ─────────────────────────────────────────────────────────────────
$quote = get_quote_with_items($id, (int)$user['id']);
if (!$quote) {
    flash_set('error', 'Quote not found or access denied.');
    header('Location: ' . APP_URL . '/quotes/index.php');
    exit;
}

$fin        = compute_quote_financials($quote);
$milestones = get_quote_milestones($id);
$revisions  = get_quote_revisions($id);
$invoice    = get_invoice_for_quote($id);
$portal_url = $quote['public_token'] ? APP_URL . '/public/quote.php?token=' . $quote['public_token'] : null;
$today      = date('Y-m-d');
$is_expired = $quote['valid_until'] && $quote['valid_until'] < $today && $quote['status'] === 'sent';

$page_title = 'Quote ' . $quote['quote_number'];
include __DIR__ . '/../includes/header.php';
?>

<!-- Page header -->
<div class="page-header mb-4">
  <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
    <div>
      <h4 class="fw-bold mb-0">
        <i class="bi bi-file-earmark-text me-2 text-primary"></i>
        <?= h($quote['quote_number']) ?> <?= badge_status($quote['status']) ?>
        <?php if ($is_expired): ?><span class="badge bg-danger">Expired</span><?php endif; ?>
      </h4>
      <p class="text-muted mb-0"><?= h($quote['project_name']) ?></p>
    </div>
    <div class="d-flex gap-2 flex-wrap">
      <a href="<?= APP_URL ?>/quotes/email.php?id=<?= $id ?>" class="btn btn-outline-primary btn-sm">
        <i class="bi bi-envelope-arrow-up me-1"></i>Email
      </a>
      <a href="<?= APP_URL ?>/quotes/print.php?id=<?= $id ?>" class="btn btn-outline-secondary btn-sm" target="_blank">
        <i class="bi bi-printer me-1"></i>Print
      </a>
      <form method="POST" class="d-inline">
        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
        <input type="hidden" name="action" value="duplicate">
        <button type="submit" class="btn btn-outline-secondary btn-sm"
                onclick="return confirm('Clone this quote as a new draft?')">
          <i class="bi bi-copy me-1"></i>Duplicate
        </button>
      </form>
      <?php if ($quote['status'] === 'accepted' && !$invoice): ?>
      <a href="<?= APP_URL ?>/invoices/create.php?quote_id=<?= $id ?>" class="btn btn-success btn-sm">
        <i class="bi bi-receipt me-1"></i>Generate Invoice
      </a>
      <?php elseif ($invoice): ?>
      <a href="<?= APP_URL ?>/invoices/view.php?id=<?= $invoice['id'] ?>" class="btn btn-outline-success btn-sm">
        <i class="bi bi-receipt me-1"></i>View Invoice
      </a>
      <?php endif; ?>
      <a href="<?= APP_URL ?>/quotes/index.php" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-arrow-left me-1"></i>Back
      </a>
    </div>
  </div>
</div>

<?php if ($is_expired): ?>
<div class="alert alert-warning">
  <i class="bi bi-calendar-x me-2"></i>
  This quote expired on <strong><?= date('d M Y', strtotime($quote['valid_until'])) ?></strong>.
  Duplicate it to send a refreshed version.
</div>
<?php endif; ?>

<div class="row g-3">
<div class="col-lg-8">

  <!-- Project / Client card -->
  <div class="card border-0 shadow-sm mb-3">
    <div class="card-body">
      <div class="row">
        <div class="col-md-6">
          <h6 class="text-muted small text-uppercase fw-semibold">Project</h6>
          <table class="table table-sm table-borderless mb-0">
            <tr><td class="text-muted ps-0" style="width:90px">Type:</td><td><?= h($quote['project_type_name'] ?? 'N/A') ?></td></tr>
            <tr><td class="text-muted ps-0">Developer:</td><td><?= tier_badge($quote['developer_tier']) ?></td></tr>
            <tr><td class="text-muted ps-0">Created:</td><td><?= date('d M Y', strtotime($quote['created_at'])) ?></td></tr>
            <tr>
              <td class="text-muted ps-0">Expires:</td>
              <td>
                <form method="POST" class="d-flex gap-1 align-items-center">
                  <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                  <input type="hidden" name="action" value="update_expiry">
                  <input type="date" name="valid_until" class="form-control form-control-sm"
                         style="width:150px" value="<?= h($quote['valid_until'] ?? '') ?>">
                  <button class="btn btn-sm btn-outline-secondary py-0 px-2">Set</button>
                </form>
              </td>
            </tr>
          </table>
        </div>
        <div class="col-md-6">
          <h6 class="text-muted small text-uppercase fw-semibold">Client</h6>
          <?php if ($quote['client_name']): ?>
            <div class="fw-semibold"><?= h($quote['client_name']) ?></div>
            <?php if ($quote['client_company']): ?><div class="text-muted"><?= h($quote['client_company']) ?></div><?php endif; ?>
            <?php if ($quote['client_email']): ?><div class="small"><i class="bi bi-envelope me-1"></i><?= h($quote['client_email']) ?></div><?php endif; ?>
            <?php if ($quote['client_phone']): ?><div class="small"><i class="bi bi-telephone me-1"></i><?= h($quote['client_phone']) ?></div><?php endif; ?>
          <?php else: ?><span class="text-muted small">No client assigned</span><?php endif; ?>
        </div>
      </div>
      <?php if ($quote['notes']): ?>
      <hr class="my-2">
      <div class="text-muted small"><strong>Notes:</strong> <?= nl2br(h($quote['notes'])) ?></div>
      <?php endif; ?>
    </div>
  </div>

  <!-- Tabs -->
  <ul class="nav nav-tabs" id="quoteTabs">
    <li class="nav-item"><a class="nav-link active" data-bs-toggle="tab" href="#tab-items">
      <i class="bi bi-list-check me-1"></i>Items <span class="badge bg-secondary ms-1"><?= count($quote['items']) ?></span>
    </a></li>
    <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#tab-milestones">
      <i class="bi bi-flag me-1"></i>Milestones<?php if ($milestones): ?> <span class="badge bg-secondary ms-1"><?= count($milestones) ?></span><?php endif; ?>
    </a></li>
    <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#tab-history">
      <i class="bi bi-clock-history me-1"></i>History<?php if ($revisions): ?> <span class="badge bg-secondary ms-1"><?= count($revisions) ?></span><?php endif; ?>
    </a></li>
  </ul>

  <div class="tab-content border border-top-0 rounded-bottom shadow-sm bg-white mb-3">

    <!-- Items tab -->
    <div class="tab-pane fade show active p-0" id="tab-items">
      <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
          <thead class="table-light">
            <tr>
              <th>Module / Feature</th>
              <th class="text-center" style="width:80px">Type</th>
              <th class="text-center" style="width:90px">Complexity</th>
              <th class="text-end" style="width:65px">Hours</th>
              <th class="text-end" style="width:130px">Total (MWK)</th>
              <th style="width:36px"></th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($quote['items'] as $item):
              $is_custom = ($item['item_type'] ?? 'module') === 'custom';
              $cx_map = ['simple'=>'info','medium'=>'warning','complex'=>'danger'];
            ?>
            <tr>
              <td>
                <div class="fw-medium"><?= h($item['module_name']) ?></div>
                <?php if ($item['description']): ?><div class="text-muted small"><?= h($item['description']) ?></div><?php endif; ?>
              </td>
              <td class="text-center">
                <?php if ($is_custom): ?>
                  <span class="badge" style="background:#e9d5ff;color:#6b21a8">Custom</span>
                <?php else: ?>
                  <span class="badge bg-light text-secondary border">Module</span>
                <?php endif; ?>
              </td>
              <td class="text-center">
                <?php if (!$is_custom): ?>
                  <span class="badge bg-<?= $cx_map[$item['complexity'] ?? 'medium'] ?? 'secondary' ?>"><?= ucfirst($item['complexity'] ?? 'medium') ?></span>
                <?php else: ?>—<?php endif; ?>
              </td>
              <td class="text-end"><?= $is_custom ? '—' : number_format((float)$item['hours'], 1) ?></td>
              <td class="text-end fw-semibold"><?= format_mwk((float)$item['total_mwk']) ?></td>
              <td class="text-center">
                <form method="POST" class="d-inline">
                  <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                  <input type="hidden" name="action" value="delete_item">
                  <input type="hidden" name="item_id" value="<?= $item['id'] ?>">
                  <input type="hidden" name="return_tab" value="tab-items">
                  <button type="submit" class="btn btn-link btn-sm text-danger p-0"
                          onclick="return confirm('Remove this item?')">
                    <i class="bi bi-x-circle"></i>
                  </button>
                </form>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
          <tfoot class="table-light fw-semibold">
            <tr>
              <td colspan="3" class="text-muted fw-normal">Total Hours</td>
              <td class="text-end"><?= number_format((float)$quote['total_hours'], 1) ?></td>
              <td colspan="2"></td>
            </tr>
            <tr>
              <td colspan="4" class="text-end text-muted fw-normal">Items Subtotal</td>
              <td class="text-end"><?= format_mwk($fin['subtotal']) ?></td>
              <td></td>
            </tr>
            <?php if ($fin['margin_amount'] > 0): ?>
            <tr>
              <td colspan="4" class="text-end text-muted fw-normal">Margin (<?= number_format((float)$quote['margin_percent'], 0) ?>%)</td>
              <td class="text-end"><?= format_mwk($fin['margin_amount']) ?></td><td></td>
            </tr>
            <?php endif; ?>
            <?php if ($fin['discount_amount'] > 0): ?>
            <tr>
              <td colspan="4" class="text-end text-muted fw-normal">
                Discount <?= ($quote['discount_type'] ?? 'percent') === 'percent' ? '(' . number_format((float)$quote['discount_value'], 1) . '%)' : '(fixed)' ?>
              </td>
              <td class="text-end text-danger">−<?= format_mwk($fin['discount_amount']) ?></td><td></td>
            </tr>
            <?php endif; ?>
            <?php if ($fin['tax_amount'] > 0): ?>
            <tr>
              <td colspan="4" class="text-end text-muted fw-normal">Tax / VAT (<?= number_format((float)$quote['tax_rate'], 1) ?>%)</td>
              <td class="text-end"><?= format_mwk($fin['tax_amount']) ?></td><td></td>
            </tr>
            <?php endif; ?>
            <tr class="table-primary">
              <td colspan="4" class="text-end fw-bold">Grand Total (MWK)</td>
              <td class="text-end fw-bold fs-6"><?= format_mwk($fin['grand_total']) ?></td><td></td>
            </tr>
            <tr>
              <td colspan="4" class="text-end text-muted small fw-normal">≈ USD @ <?= number_format((float)$quote['usd_rate'], 0) ?> MWK</td>
              <td class="text-end text-muted small"><?= format_usd((float)$quote['total_usd']) ?></td><td></td>
            </tr>
          </tfoot>
        </table>
      </div>
      <!-- Add custom item -->
      <div class="p-3 border-top bg-light">
        <button class="btn btn-sm btn-outline-primary" type="button"
                data-bs-toggle="collapse" data-bs-target="#addCustomItem">
          <i class="bi bi-plus-circle me-1"></i>Add Custom Line Item
        </button>
        <div class="collapse mt-2" id="addCustomItem">
          <form method="POST" class="card card-body border-0 shadow-sm p-3">
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
            <input type="hidden" name="action" value="add_custom_item">
            <input type="hidden" name="return_tab" value="tab-items">
            <div class="row g-2 align-items-end">
              <div class="col-md-5">
                <label class="form-label small mb-1">Item name</label>
                <input type="text" name="item_name" class="form-control form-control-sm"
                       placeholder="e.g. Domain registration" required>
              </div>
              <div class="col-md-3">
                <label class="form-label small mb-1">Description</label>
                <input type="text" name="item_desc" class="form-control form-control-sm" placeholder="Optional">
              </div>
              <div class="col-md-3">
                <label class="form-label small mb-1">Price (MWK)</label>
                <div class="input-group input-group-sm">
                  <span class="input-group-text">MWK</span>
                  <input type="number" name="item_price" class="form-control" min="1" step="1" placeholder="0" required>
                </div>
              </div>
              <div class="col-md-1">
                <button type="submit" class="btn btn-primary btn-sm w-100">Add</button>
              </div>
            </div>
          </form>
        </div>
      </div>
    </div><!-- /tab-items -->

    <!-- Milestones tab -->
    <div class="tab-pane fade p-3" id="tab-milestones">
      <p class="text-muted small mb-3">
        Break <strong><?= format_mwk($fin['grand_total']) ?></strong> into payment phases.
        Percentages should total 100%.
      </p>
      <?php if ($milestones):
        $total_pct  = array_sum(array_column($milestones, 'percent'));
        $total_paid = array_sum(array_map(fn($m) => $m['status'] === 'paid' ? (float)$m['amount_mwk'] : 0, $milestones));
      ?>
      <div class="d-flex justify-content-between small text-muted mb-1">
        <span>Allocated: <strong><?= number_format($total_pct, 1) ?>%</strong></span>
        <span>Paid: <strong><?= format_mwk($total_paid) ?></strong></span>
      </div>
      <?php if (abs($total_pct - 100) > 0.5): ?>
      <div class="alert alert-warning py-1 small mb-2">
        <i class="bi bi-exclamation-triangle me-1"></i>Milestones total <?= number_format($total_pct, 1) ?>% (should be 100%).
      </div>
      <?php endif; ?>
      <table class="table table-sm table-hover align-middle mb-3">
        <thead class="table-light">
          <tr><th>Milestone</th><th class="text-end">%</th><th class="text-end">Amount</th><th>Due</th><th>Status</th><th></th></tr>
        </thead>
        <tbody>
          <?php foreach ($milestones as $ms): ?>
          <tr>
            <td>
              <div class="fw-medium"><?= h($ms['title']) ?></div>
              <?php if ($ms['description']): ?><div class="text-muted small"><?= h($ms['description']) ?></div><?php endif; ?>
            </td>
            <td class="text-end"><?= number_format((float)$ms['percent'], 1) ?>%</td>
            <td class="text-end fw-semibold"><?= format_mwk((float)$ms['amount_mwk']) ?></td>
            <td class="small"><?= $ms['due_date'] ? date('d M Y', strtotime($ms['due_date'])) : '—' ?></td>
            <td>
              <form method="POST" class="d-flex gap-1">
                <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                <input type="hidden" name="action" value="update_milestone_status">
                <input type="hidden" name="milestone_id" value="<?= $ms['id'] ?>">
                <input type="hidden" name="return_tab" value="tab-milestones">
                <select name="ms_status" class="form-select form-select-sm" onchange="this.form.submit()">
                  <?php foreach (['pending','invoiced','paid'] as $mst): ?>
                  <option value="<?= $mst ?>" <?= $ms['status'] === $mst ? 'selected' : '' ?>><?= ucfirst($mst) ?></option>
                  <?php endforeach; ?>
                </select>
              </form>
            </td>
            <td>
              <form method="POST" class="d-inline">
                <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                <input type="hidden" name="action" value="delete_milestone">
                <input type="hidden" name="milestone_id" value="<?= $ms['id'] ?>">
                <input type="hidden" name="return_tab" value="tab-milestones">
                <button type="submit" class="btn btn-link btn-sm text-danger p-0"
                        onclick="return confirm('Delete milestone?')"><i class="bi bi-trash"></i></button>
              </form>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      <?php else: ?><p class="text-muted small fst-italic mb-3">No milestones yet.</p><?php endif; ?>
      <form method="POST" class="card card-body border p-3">
        <h6 class="fw-semibold mb-2 small">Add Milestone</h6>
        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
        <input type="hidden" name="action" value="add_milestone">
        <input type="hidden" name="return_tab" value="tab-milestones">
        <div class="row g-2">
          <div class="col-md-4"><input type="text" name="ms_title" class="form-control form-control-sm" placeholder="e.g. Initial Deposit" required></div>
          <div class="col-md-3"><input type="text" name="ms_desc" class="form-control form-control-sm" placeholder="Description"></div>
          <div class="col-md-2">
            <div class="input-group input-group-sm">
              <input type="number" name="ms_percent" class="form-control" min="1" max="100" step="0.1" placeholder="%" required>
              <span class="input-group-text">%</span>
            </div>
          </div>
          <div class="col-md-2"><input type="date" name="ms_due" class="form-control form-control-sm"></div>
          <div class="col-md-1"><button type="submit" class="btn btn-primary btn-sm w-100">Add</button></div>
        </div>
      </form>
    </div><!-- /tab-milestones -->

    <!-- History tab -->
    <div class="tab-pane fade p-3" id="tab-history">
      <p class="text-muted small">Save manual snapshots to track changes over time.</p>
      <form method="POST" class="mb-3 d-flex gap-2">
        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
        <input type="hidden" name="action" value="save_revision">
        <input type="hidden" name="return_tab" value="tab-history">
        <input type="text" name="revision_note" class="form-control form-control-sm"
               placeholder="Describe this change (e.g. Added hosting module, Revised scope)">
        <button type="submit" class="btn btn-outline-primary btn-sm text-nowrap">
          <i class="bi bi-camera me-1"></i>Save Snapshot
        </button>
      </form>
      <?php if ($revisions): ?>
      <table class="table table-sm align-middle">
        <thead class="table-light">
          <tr><th>#</th><th>Note</th><th>By</th><th>Date</th><th></th></tr>
        </thead>
        <tbody>
          <?php foreach ($revisions as $rev): ?>
          <?php $snap = json_decode($rev['snapshot'], true); ?>
          <tr>
            <td><span class="badge bg-secondary">v<?= $rev['revision_number'] ?></span></td>
            <td><?= h($rev['change_note'] ?: '—') ?></td>
            <td><?= h($rev['changed_by_name']) ?></td>
            <td class="small text-muted"><?= date('d M Y H:i', strtotime($rev['changed_at'])) ?></td>
            <td>
              <button class="btn btn-link btn-sm p-0" data-bs-toggle="collapse"
                      data-bs-target="#snap-<?= $rev['id'] ?>">
                <i class="bi bi-eye"></i>
              </button>
            </td>
          </tr>
          <tr class="collapse" id="snap-<?= $rev['id'] ?>">
            <td colspan="5">
              <div class="bg-light rounded p-2 small">
                Total: <?= format_mwk((float)($snap['quote']['total_mwk'] ?? 0)) ?> ·
                Items: <?= count($snap['items'] ?? []) ?> ·
                Status: <?= h($snap['quote']['status'] ?? '?') ?> ·
                Margin: <?= $snap['quote']['margin_percent'] ?? 0 ?>%
              </div>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      <?php else: ?><p class="text-muted small fst-italic">No snapshots saved yet.</p><?php endif; ?>
    </div><!-- /tab-history -->

  </div><!-- /tab-content -->
</div><!-- /col-lg-8 -->

<!-- Sidebar -->
<div class="col-lg-4">

  <!-- Summary -->
  <div class="card border-0 shadow-sm mb-3">
    <div class="card-header bg-primary text-white border-0">
      <h6 class="mb-0"><i class="bi bi-receipt me-2"></i>Quote Summary</h6>
    </div>
    <div class="card-body">
      <div class="d-flex justify-content-between mb-1">
        <span class="text-muted small">Items subtotal:</span>
        <strong><?= format_mwk($fin['subtotal']) ?></strong>
      </div>
      <?php if ($fin['margin_amount'] > 0): ?>
      <div class="d-flex justify-content-between mb-1">
        <span class="text-muted small">Margin (<?= number_format((float)$quote['margin_percent'], 0) ?>%):</span>
        <strong><?= format_mwk($fin['margin_amount']) ?></strong>
      </div>
      <?php endif; ?>
      <?php if ($fin['discount_amount'] > 0): ?>
      <div class="d-flex justify-content-between mb-1">
        <span class="text-muted small text-danger">Discount:</span>
        <strong class="text-danger">−<?= format_mwk($fin['discount_amount']) ?></strong>
      </div>
      <?php endif; ?>
      <?php if ($fin['tax_amount'] > 0): ?>
      <div class="d-flex justify-content-between mb-1">
        <span class="text-muted small">Tax (<?= number_format((float)$quote['tax_rate'], 1) ?>%):</span>
        <strong><?= format_mwk($fin['tax_amount']) ?></strong>
      </div>
      <?php endif; ?>
      <hr class="my-2">
      <div class="d-flex justify-content-between align-items-center">
        <span class="fw-bold">Total (MWK):</span>
        <span class="fw-bold fs-5 text-primary"><?= format_mwk($fin['grand_total']) ?></span>
      </div>
      <div class="d-flex justify-content-between text-muted small mt-1">
        <span>≈ USD:</span><span><?= format_usd((float)$quote['total_usd']) ?></span>
      </div>
    </div>
  </div>

  <!-- Discount & Tax -->
  <div class="card border-0 shadow-sm mb-3">
    <div class="card-body">
      <h6 class="fw-semibold mb-2"><i class="bi bi-percent me-2 text-success"></i>Discount & Tax</h6>
      <form method="POST">
        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
        <input type="hidden" name="action" value="update_discount_tax">
        <label class="form-label small fw-medium mb-1">Discount</label>
        <div class="input-group input-group-sm mb-2">
          <select name="discount_type" class="form-select" style="max-width:75px">
            <option value="percent" <?= ($quote['discount_type'] ?? 'percent') === 'percent' ? 'selected' : '' ?>>%</option>
            <option value="fixed"   <?= ($quote['discount_type'] ?? '') === 'fixed' ? 'selected' : '' ?>>MWK</option>
          </select>
          <input type="number" name="discount_value" class="form-control" min="0" step="0.01"
                 value="<?= h((string)(float)($quote['discount_value'] ?? 0)) ?>">
        </div>
        <label class="form-label small fw-medium mb-1">Tax / VAT (%)</label>
        <div class="input-group input-group-sm mb-2">
          <input type="number" name="tax_rate" class="form-control" min="0" max="100" step="0.01"
                 value="<?= h((string)(float)($quote['tax_rate'] ?? 0)) ?>">
          <span class="input-group-text">%</span>
        </div>
        <button type="submit" class="btn btn-sm btn-outline-success w-100">Apply</button>
      </form>
    </div>
  </div>

  <!-- Client portal -->
  <div class="card border-0 shadow-sm mb-3">
    <div class="card-body">
      <h6 class="fw-semibold mb-1"><i class="bi bi-link-45deg me-2 text-info"></i>Client Portal</h6>
      <p class="text-muted small mb-2">Share a link so your client can view and accept/reject the quote — no login needed.</p>
      <?php if ($portal_url): ?>
        <div class="input-group input-group-sm mb-2">
          <input type="text" class="form-control form-control-sm" id="portalUrl" value="<?= h($portal_url) ?>" readonly>
          <button class="btn btn-outline-secondary btn-sm" type="button"
                  onclick="navigator.clipboard.writeText(document.getElementById('portalUrl').value);this.textContent='Copied!'">Copy</button>
        </div>
        <form method="POST">
          <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
          <input type="hidden" name="action" value="revoke_portal_token">
          <button type="submit" class="btn btn-sm btn-outline-danger w-100"
                  onclick="return confirm('Revoke this link? The current link will stop working.')">
            <i class="bi bi-x-circle me-1"></i>Revoke Link
          </button>
        </form>
      <?php else: ?>
        <form method="POST">
          <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
          <input type="hidden" name="action" value="generate_portal_token">
          <button type="submit" class="btn btn-sm btn-outline-info w-100">
            <i class="bi bi-link-45deg me-1"></i>Generate Portal Link
          </button>
        </form>
      <?php endif; ?>
    </div>
  </div>

  <!-- Status -->
  <div class="card border-0 shadow-sm mb-3">
    <div class="card-body">
      <h6 class="fw-semibold mb-2"><i class="bi bi-arrow-repeat me-2 text-warning"></i>Update Status</h6>
      <form method="POST">
        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
        <input type="hidden" name="action" value="update_status">
        <select name="status" class="form-select form-select-sm mb-2">
          <?php foreach (['draft','sent','accepted','rejected'] as $s): ?>
          <option value="<?= $s ?>" <?= $quote['status'] === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
          <?php endforeach; ?>
        </select>
        <button type="submit" class="btn btn-warning btn-sm w-100">Update Status</button>
      </form>
    </div>
  </div>

  <!-- Invoice panel -->
  <?php if ($invoice): ?>
  <div class="card border-0 shadow-sm mb-3" style="border-left:4px solid #22c55e!important">
    <div class="card-body">
      <h6 class="fw-semibold mb-2"><i class="bi bi-receipt me-2 text-success"></i>Invoice</h6>
      <div class="d-flex justify-content-between small mb-1">
        <span class="text-muted"><?= h($invoice['invoice_number']) ?></span>
        <?= badge_invoice_status($invoice['status']) ?>
      </div>
      <div class="d-flex justify-content-between small mb-1">
        <span class="text-muted">Paid:</span>
        <strong><?= format_mwk((float)$invoice['amount_paid_mwk']) ?> / <?= format_mwk((float)$invoice['amount_mwk']) ?></strong>
      </div>
      <?php $pct_paid = $invoice['amount_mwk'] > 0 ? round((float)$invoice['amount_paid_mwk'] / (float)$invoice['amount_mwk'] * 100) : 0; ?>
      <div class="progress my-2" style="height:6px"><div class="progress-bar bg-success" style="width:<?= $pct_paid ?>%"></div></div>
      <a href="<?= APP_URL ?>/invoices/view.php?id=<?= $invoice['id'] ?>" class="btn btn-sm btn-outline-success w-100 mt-1">
        <i class="bi bi-arrow-right me-1"></i>Manage Invoice
      </a>
    </div>
  </div>
  <?php endif; ?>

  <div class="d-grid gap-2">
    <a href="<?= APP_URL ?>/quotes/print.php?id=<?= $id ?>" class="btn btn-primary" target="_blank">
      <i class="bi bi-printer me-2"></i>Print / Export PDF
    </a>
    <a href="<?= APP_URL ?>/quotes/create.php" class="btn btn-outline-primary">
      <i class="bi bi-plus me-2"></i>New Quote
    </a>
  </div>

</div><!-- /col-lg-4 -->
</div><!-- /row -->

<?php include __DIR__ . '/../includes/footer.php'; ?>
