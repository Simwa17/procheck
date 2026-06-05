<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

$user = require_login();
$invoices = get_invoices((int)$user['id'], $user['role'] === 'admin');

$page_title = 'Invoices';
include __DIR__ . '/../includes/header.php';
?>

<div class="page-header mb-4">
  <div class="d-flex justify-content-between align-items-center">
    <div>
      <h4 class="fw-bold mb-0"><i class="bi bi-receipt me-2 text-success"></i>Invoices</h4>
      <p class="text-muted mb-0">Track payment for accepted quotes</p>
    </div>
  </div>
</div>

<?php if ($invoices): ?>
<div class="card border-0 shadow-sm">
  <div class="card-body p-0">
    <table class="table table-hover align-middle mb-0">
      <thead class="table-light">
        <tr>
          <th>Invoice</th>
          <th>Quote / Project</th>
          <th>Client</th>
          <th>Status</th>
          <th class="text-end">Total</th>
          <th class="text-end">Paid</th>
          <th>Due</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($invoices as $inv):
          $overdue = $inv['due_date'] && $inv['due_date'] < date('Y-m-d') && $inv['status'] !== 'paid';
          $pct     = $inv['amount_mwk'] > 0 ? min(100, round((float)$inv['amount_paid_mwk'] / (float)$inv['amount_mwk'] * 100)) : 0;
        ?>
        <tr>
          <td class="fw-semibold"><?= h($inv['invoice_number']) ?></td>
          <td>
            <div><?= h($inv['project_name']) ?></div>
            <div class="text-muted small"><?= h($inv['quote_number']) ?></div>
          </td>
          <td><?= h($inv['client_name'] ?: '—') ?></td>
          <td>
            <?= badge_invoice_status($inv['status']) ?>
            <?php if ($overdue): ?><span class="badge bg-danger ms-1">Overdue</span><?php endif; ?>
          </td>
          <td class="text-end"><?= format_mwk((float)$inv['amount_mwk']) ?></td>
          <td class="text-end">
            <div class="fw-semibold text-success"><?= format_mwk((float)$inv['amount_paid_mwk']) ?></div>
            <div class="progress" style="height:4px;width:70px;margin-left:auto">
              <div class="progress-bar bg-success" style="width:<?= $pct ?>%"></div>
            </div>
          </td>
          <td class="small <?= $overdue ? 'text-danger fw-semibold' : 'text-muted' ?>">
            <?= $inv['due_date'] ? date('d M Y', strtotime($inv['due_date'])) : '—' ?>
          </td>
          <td>
            <a href="<?= APP_URL ?>/invoices/view.php?id=<?= $inv['id'] ?>"
               class="btn btn-sm btn-outline-success">
              <i class="bi bi-arrow-right"></i>
            </a>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php else: ?>
<div class="text-center py-5">
  <i class="bi bi-receipt display-4 text-muted d-block mb-3"></i>
  <h5 class="text-muted">No invoices yet</h5>
  <p class="text-muted">Accept a quote and click <strong>Generate Invoice</strong> on the quote page.</p>
  <a href="<?= APP_URL ?>/quotes/index.php" class="btn btn-primary">View Quotes</a>
</div>
<?php endif; ?>

<?php include __DIR__ . '/../includes/footer.php'; ?>
