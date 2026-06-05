<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

$user  = require_login();
$id    = (int)($_GET['id'] ?? 0);
$quote = get_quote_with_items($id, $user['id']);

if (!$quote) {
    echo '<p>Quote not found.</p>';
    exit;
}

$company_name    = setting('company_name', 'My Dev Studio');
$company_email   = setting('company_email', '');
$company_phone   = setting('company_phone', '');
$company_address = setting('company_address', 'Malawi');
$quote_footer    = setting('quote_footer', '');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Quote <?= h($quote['quote_number']) ?></title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
<style>
  @media print {
    body { font-size: 11pt; }
    .no-print { display: none !important; }
    .card { border: 1px solid #dee2e6 !important; box-shadow: none !important; }
    @page { margin: 15mm; }
  }
  body { background: #f8f9fa; }
  .print-page { max-width: 900px; margin: 0 auto; background: #fff; padding: 2rem; border-radius: 8px; }
  .logo-bar { border-bottom: 3px solid #2563eb; padding-bottom: 1rem; margin-bottom: 1.5rem; }
  .total-row td { font-size: 1.1rem; font-weight: 700; background: #eff6ff; }
  tfoot tr:last-child td { border-top: 2px solid #2563eb; }
</style>
</head>
<body>
<div class="no-print py-3 text-center bg-light border-bottom">
  <button onclick="window.print()" class="btn btn-primary me-2">
    <i class="bi bi-printer me-1"></i>Print / Save as PDF
  </button>
  <a href="<?= APP_URL ?>/quotes/view.php?id=<?= $quote['id'] ?>" class="btn btn-outline-secondary">
    &larr; Back to Quote
  </a>
</div>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

<div class="print-page my-4">
  <!-- Header -->
  <div class="logo-bar d-flex justify-content-between align-items-start">
    <div>
      <h2 class="fw-bold text-primary mb-0"><?= h($company_name) ?></h2>
      <?php if ($company_address): ?><div class="text-muted small"><?= h($company_address) ?></div><?php endif; ?>
      <?php if ($company_email): ?><div class="text-muted small"><i class="bi bi-envelope me-1"></i><?= h($company_email) ?></div><?php endif; ?>
      <?php if ($company_phone): ?><div class="text-muted small"><i class="bi bi-telephone me-1"></i><?= h($company_phone) ?></div><?php endif; ?>
    </div>
    <div class="text-end">
      <div class="fs-4 fw-bold text-primary">QUOTATION</div>
      <div class="text-muted"><?= h($quote['quote_number']) ?></div>
      <div class="text-muted small">Date: <?= date('d F Y', strtotime($quote['created_at'])) ?></div>
      <?php if ($quote['valid_until']): ?>
      <div class="text-muted small">Valid Until: <?= date('d F Y', strtotime($quote['valid_until'])) ?></div>
      <?php endif; ?>
    </div>
  </div>

  <!-- Bill To / Project Info -->
  <div class="row mb-4">
    <div class="col-6">
      <h6 class="text-uppercase small fw-semibold text-muted mb-2">Bill To</h6>
      <?php if ($quote['client_name']): ?>
        <div class="fw-semibold"><?= h($quote['client_name']) ?></div>
        <?php if ($quote['client_company']): ?><div><?= h($quote['client_company']) ?></div><?php endif; ?>
        <?php if ($quote['client_email']): ?><div class="text-muted small"><?= h($quote['client_email']) ?></div><?php endif; ?>
        <?php if ($quote['client_phone']): ?><div class="text-muted small"><?= h($quote['client_phone']) ?></div><?php endif; ?>
        <?php if ($quote['client_address']): ?><div class="text-muted small"><?= nl2br(h($quote['client_address'])) ?></div><?php endif; ?>
      <?php else: ?>
        <span class="text-muted">No client specified</span>
      <?php endif; ?>
    </div>
    <div class="col-6">
      <h6 class="text-uppercase small fw-semibold text-muted mb-2">Project Details</h6>
      <table class="table table-sm table-borderless mb-0">
        <tr><td class="text-muted ps-0 small">Project:</td><td class="fw-semibold"><?= h($quote['project_name']) ?></td></tr>
        <?php if ($quote['project_type_name']): ?>
        <tr><td class="text-muted ps-0 small">Type:</td><td><?= h($quote['project_type_name']) ?></td></tr>
        <?php endif; ?>
        <tr><td class="text-muted ps-0 small">Developer:</td><td><?= ucfirst($quote['developer_tier']) ?></td></tr>
        <tr><td class="text-muted ps-0 small">Prepared by:</td><td><?= h($quote['creator_name']) ?></td></tr>
      </table>
    </div>
  </div>

  <?php if ($quote['notes']): ?>
  <div class="alert alert-light mb-4 small">
    <strong>Notes:</strong> <?= nl2br(h($quote['notes'])) ?>
  </div>
  <?php endif; ?>

  <!-- Line Items -->
  <table class="table table-bordered align-middle mb-4">
    <thead class="table-primary">
      <tr>
        <th style="width:40%">Module / Feature</th>
        <th class="text-center" style="width:12%">Complexity</th>
        <th class="text-end" style="width:10%">Hours</th>
        <th class="text-end" style="width:18%">Rate (MWK/hr)</th>
        <th class="text-end" style="width:20%">Amount (MWK)</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($quote['items'] as $i => $item): ?>
      <tr>
        <td>
          <div class="fw-medium"><?= h($item['module_name']) ?></div>
          <?php if ($item['description']): ?><div class="text-muted small"><?= h($item['description']) ?></div><?php endif; ?>
        </td>
        <td class="text-center"><?= ucfirst($item['complexity']) ?></td>
        <td class="text-end"><?= number_format($item['hours'], 1) ?></td>
        <td class="text-end"><?= number_format($item['rate_mwk'], 0, '.', ',') ?></td>
        <td class="text-end"><?= number_format($item['total_mwk'], 2, '.', ',') ?></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
    <tfoot>
      <tr>
        <td colspan="2" class="text-muted small text-end">Total Estimated Hours:</td>
        <td class="text-end fw-bold"><?= number_format($quote['total_hours'], 1) ?></td>
        <td colspan="2"></td>
      </tr>
      <tr>
        <td colspan="4" class="text-end text-muted">Subtotal</td>
        <td class="text-end"><?= number_format($quote['subtotal_mwk'], 2, '.', ',') ?></td>
      </tr>
      <?php if ($quote['margin_percent'] > 0): ?>
      <tr>
        <td colspan="4" class="text-end text-muted">Business Margin (<?= number_format($quote['margin_percent'], 0) ?>%)</td>
        <td class="text-end"><?= number_format($quote['total_mwk'] - $quote['subtotal_mwk'], 2, '.', ',') ?></td>
      </tr>
      <?php endif; ?>
      <tr class="total-row">
        <td colspan="4" class="text-end">TOTAL (MWK)</td>
        <td class="text-end">MWK <?= number_format($quote['total_mwk'], 2, '.', ',') ?></td>
      </tr>
      <tr>
        <td colspan="4" class="text-end text-muted small">≈ Equivalent in USD (1 USD = <?= number_format($quote['usd_rate'], 0) ?> MWK)</td>
        <td class="text-end text-muted small">USD <?= number_format($quote['total_usd'], 2, '.', ',') ?></td>
      </tr>
    </tfoot>
  </table>

  <!-- Footer -->
  <?php if ($quote_footer): ?>
  <div class="border-top pt-3 text-muted small mt-4">
    <?= nl2br(h($quote_footer)) ?>
  </div>
  <?php endif; ?>

  <div class="text-muted small mt-4 text-center">
    Generated by ProCheck &mdash; Project Pricing for Malawian Developers
  </div>
</div>

<script>
// Auto-trigger print if ?print=1
if (new URLSearchParams(window.location.search).get('print') === '1') {
    window.onload = () => window.print();
}
</script>
</body>
</html>
