<?php
/**
 * Client Portal — public quote view
 * No authentication required. Uses a secure random token.
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/functions.php';

$token = trim($_GET['token'] ?? '');
if (!$token || strlen($token) < 32) {
    http_response_code(404);
    die('Invalid or expired link.');
}

$pdo = db();

// Load quote by token
$stmt = $pdo->prepare('
    SELECT q.*, pt.name AS project_type_name,
           c.name AS client_name, c.company AS client_company,
           c.email AS client_email, c.phone AS client_phone,
           u.name AS creator_name, u.email AS creator_email
    FROM quotes q
    LEFT JOIN project_types pt ON q.project_type_id = pt.id
    LEFT JOIN clients c ON q.client_id = c.id
    LEFT JOIN users u ON q.user_id = u.id
    WHERE q.public_token = ?
    LIMIT 1
');
$stmt->execute([$token]);
$quote = $stmt->fetch();

if (!$quote) {
    http_response_code(404);
    die('This link is invalid or has been revoked.');
}

// Load items
$items_stmt = $pdo->prepare('SELECT * FROM quote_items WHERE quote_id = ? ORDER BY id');
$items_stmt->execute([$quote['id']]);
$quote['items'] = $items_stmt->fetchAll();

$fin = compute_quote_financials($quote);

// Handle accept / reject
$action_msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = $_POST['client_action'] ?? '';
    if ($act === 'accept' && $quote['status'] === 'sent') {
        $pdo->prepare('UPDATE quotes SET status="accepted" WHERE id=?')->execute([$quote['id']]);
        $quote['status'] = 'accepted';
        $action_msg = 'accepted';
    } elseif ($act === 'reject' && $quote['status'] === 'sent') {
        $pdo->prepare('UPDATE quotes SET status="rejected" WHERE id=?')->execute([$quote['id']]);
        $quote['status'] = 'rejected';
        $action_msg = 'rejected';
    }
}

$company   = setting('company_name', 'ProCheck');
$is_expired = $quote['valid_until'] && $quote['valid_until'] < date('Y-m-d');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Quote <?= h($quote['quote_number']) ?> — <?= h($company) ?></title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<style>
body { background:#f8fafc; }
.portal-brand { background:#2563eb; color:#fff; padding:16px 24px; }
.total-box { background:#2563eb; color:#fff; border-radius:8px; padding:20px; }
</style>
</head>
<body>
<div class="portal-brand d-flex justify-content-between align-items-center">
  <div>
    <strong><?= h($company) ?></strong>
    <?php if (setting('company_email')): ?><span class="ms-3 small opacity-75"><?= h(setting('company_email')) ?></span><?php endif; ?>
  </div>
  <div class="small opacity-75">Client Quote Portal</div>
</div>

<div class="container py-4" style="max-width:860px">

  <?php if ($action_msg): ?>
  <div class="alert alert-<?= $action_msg === 'accepted' ? 'success' : 'danger' ?> alert-dismissible">
    <i class="bi bi-<?= $action_msg === 'accepted' ? 'check-circle-fill' : 'x-circle-fill' ?> me-2"></i>
    <strong>Quote <?= $action_msg ?>.</strong>
    <?= $action_msg === 'accepted' ? 'Thank you! The team will be in touch shortly.' : 'The team has been notified.' ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
  </div>
  <?php endif; ?>

  <!-- Header -->
  <div class="card border-0 shadow-sm mb-4">
    <div class="card-body">
      <div class="d-flex justify-content-between align-items-start flex-wrap gap-3">
        <div>
          <h4 class="fw-bold mb-1"><?= h($quote['quote_number']) ?></h4>
          <h5 class="text-muted fw-normal mb-2"><?= h($quote['project_name']) ?></h5>
          <div class="d-flex flex-wrap gap-3 small text-muted">
            <span><i class="bi bi-calendar me-1"></i>Issued: <?= date('d M Y', strtotime($quote['created_at'])) ?></span>
            <?php if ($quote['valid_until']): ?>
            <span class="<?= $is_expired ? 'text-danger fw-semibold' : '' ?>">
              <i class="bi bi-calendar-x me-1"></i>
              <?= $is_expired ? 'Expired: ' : 'Valid until: ' ?><?= date('d M Y', strtotime($quote['valid_until'])) ?>
            </span>
            <?php endif; ?>
            <?php if ($quote['project_type_name']): ?>
            <span><i class="bi bi-tag me-1"></i><?= h($quote['project_type_name']) ?></span>
            <?php endif; ?>
          </div>
        </div>
        <div>
          <?php
          $sc = ['draft'=>'secondary','sent'=>'primary','accepted'=>'success','rejected'=>'danger'];
          ?>
          <span class="badge bg-<?= $sc[$quote['status']] ?? 'secondary' ?> fs-6 px-3 py-2">
            <?= ucfirst($quote['status']) ?>
          </span>
        </div>
      </div>

      <?php if ($quote['client_name']): ?>
      <hr class="my-3">
      <div class="small">
        <strong>Prepared for:</strong> <?= h($quote['client_name']) ?>
        <?php if ($quote['client_company']): ?>, <?= h($quote['client_company']) ?><?php endif; ?>
      </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- Items table -->
  <div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white border-0 fw-semibold">
      <i class="bi bi-list-check me-2 text-primary"></i>Scope of Work
    </div>
    <div class="card-body p-0">
      <table class="table table-hover align-middle mb-0">
        <thead class="table-light">
          <tr>
            <th>Feature / Deliverable</th>
            <th class="text-center">Complexity</th>
            <th class="text-end">Hours</th>
            <th class="text-end">Amount (MWK)</th>
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
              <?php if ($is_custom): ?>—<?php else: ?>
                <span class="badge bg-<?= $cx_map[$item['complexity'] ?? 'medium'] ?? 'secondary' ?>"><?= ucfirst($item['complexity'] ?? 'medium') ?></span>
              <?php endif; ?>
            </td>
            <td class="text-end"><?= $is_custom ? '—' : number_format((float)$item['hours'], 1) ?></td>
            <td class="text-end fw-semibold"><?= format_mwk((float)$item['total_mwk']) ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
        <tfoot class="table-light">
          <tr>
            <td colspan="3" class="text-end text-muted">Subtotal</td>
            <td class="text-end fw-semibold"><?= format_mwk($fin['subtotal']) ?></td>
          </tr>
          <?php if ($fin['margin_amount'] > 0): ?>
          <tr>
            <td colspan="3" class="text-end text-muted">Service margin</td>
            <td class="text-end"><?= format_mwk($fin['margin_amount']) ?></td>
          </tr>
          <?php endif; ?>
          <?php if ($fin['discount_amount'] > 0): ?>
          <tr>
            <td colspan="3" class="text-end text-muted">Discount</td>
            <td class="text-end text-danger">−<?= format_mwk($fin['discount_amount']) ?></td>
          </tr>
          <?php endif; ?>
          <?php if ($fin['tax_amount'] > 0): ?>
          <tr>
            <td colspan="3" class="text-end text-muted">Tax / VAT</td>
            <td class="text-end"><?= format_mwk($fin['tax_amount']) ?></td>
          </tr>
          <?php endif; ?>
          <tr>
            <td colspan="4" class="p-0">
              <div class="total-box m-2 d-flex justify-content-between align-items-center">
                <div>
                  <div class="fs-5 fw-bold">Total Amount</div>
                  <div class="opacity-75 small">≈ <?= format_usd((float)$quote['total_usd']) ?></div>
                </div>
                <div class="fs-3 fw-bold"><?= format_mwk($fin['grand_total']) ?></div>
              </div>
            </td>
          </tr>
        </tfoot>
      </table>
    </div>
  </div>

  <?php if ($quote['notes']): ?>
  <div class="card border-0 shadow-sm mb-4">
    <div class="card-body">
      <h6 class="fw-semibold mb-1"><i class="bi bi-chat-text me-2"></i>Notes</h6>
      <p class="text-muted mb-0"><?= nl2br(h($quote['notes'])) ?></p>
    </div>
  </div>
  <?php endif; ?>

  <!-- Accept / Reject -->
  <?php if ($quote['status'] === 'sent' && !$is_expired): ?>
  <div class="card border-0 shadow-sm mb-4">
    <div class="card-body text-center py-4">
      <h5 class="fw-semibold mb-1">Ready to proceed?</h5>
      <p class="text-muted mb-3">
        Accept this quote to let <strong><?= h($quote['creator_name'] ?? $company) ?></strong> know you're on board,
        or reject it if you'd like to discuss changes.
      </p>
      <form method="POST" class="d-flex justify-content-center gap-3 flex-wrap">
        <button type="submit" name="client_action" value="accept"
                class="btn btn-success btn-lg px-5"
                onclick="return confirm('Accept this quote?')">
          <i class="bi bi-check-circle-fill me-2"></i>Accept Quote
        </button>
        <button type="submit" name="client_action" value="reject"
                class="btn btn-outline-danger btn-lg px-5"
                onclick="return confirm('Reject this quote?')">
          <i class="bi bi-x-circle me-2"></i>Reject
        </button>
      </form>
    </div>
  </div>
  <?php elseif ($quote['status'] === 'accepted'): ?>
  <div class="alert alert-success text-center">
    <i class="bi bi-check-circle-fill fs-4 d-block mb-2"></i>
    <strong>You have accepted this quote.</strong><br>
    <span class="text-muted small">The team will be in touch with next steps.</span>
  </div>
  <?php elseif ($quote['status'] === 'rejected'): ?>
  <div class="alert alert-secondary text-center">
    <i class="bi bi-x-circle fs-4 d-block mb-2"></i>
    <strong>This quote was declined.</strong>
  </div>
  <?php elseif ($is_expired): ?>
  <div class="alert alert-warning text-center">
    <i class="bi bi-calendar-x fs-4 d-block mb-2"></i>
    <strong>This quote has expired.</strong><br>
    <span class="text-muted small">Please contact <?= h($quote['creator_name'] ?? $company) ?> to request a new quote.</span>
  </div>
  <?php endif; ?>

  <?php if (setting('quote_footer')): ?>
  <div class="text-center text-muted small mt-4 pb-3"><?= nl2br(h(setting('quote_footer'))) ?></div>
  <?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
