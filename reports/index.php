<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

$user = require_login();
$pdo  = db();
$uid  = (int)$user['id'];
$is_admin = $user['role'] === 'admin';

// ── Monthly revenue (last 12 months) ─────────────────────────────────────────
$monthly = $pdo->prepare("
    SELECT DATE_FORMAT(created_at, '%Y-%m') AS month,
           DATE_FORMAT(created_at, '%b %Y')  AS label,
           COUNT(*) AS total_quotes,
           SUM(CASE WHEN status='accepted' THEN 1 ELSE 0 END) AS accepted,
           SUM(CASE WHEN status='accepted' THEN total_mwk ELSE 0 END) AS revenue_mwk
    FROM quotes
    WHERE (" . ($is_admin ? '1=1' : 'user_id = ?') . ")
      AND created_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
    GROUP BY month
    ORDER BY month ASC
");
$monthly->execute($is_admin ? [] : [$uid]);
$monthly_data = $monthly->fetchAll();

// ── Conversion rate ───────────────────────────────────────────────────────────
$conv_stmt = $pdo->prepare("
    SELECT COUNT(*) AS total,
           SUM(CASE WHEN status='accepted' THEN 1 ELSE 0 END) AS accepted
    FROM quotes WHERE " . ($is_admin ? '1=1' : 'user_id = ?'));
$conv_stmt->execute($is_admin ? [] : [$uid]);
$conv = $conv_stmt->fetch();
$conv_rate = $conv['total'] > 0 ? round((float)$conv['accepted'] / (float)$conv['total'] * 100, 1) : 0;

// ── Top modules ───────────────────────────────────────────────────────────────
$top_mods_stmt = $pdo->prepare("
    SELECT qi.module_name, COUNT(*) AS cnt,
           SUM(qi.total_mwk) AS total_mwk
    FROM quote_items qi
    JOIN quotes q ON qi.quote_id = q.id
    WHERE " . ($is_admin ? '1=1' : 'q.user_id = ?') . "
    GROUP BY qi.module_name
    ORDER BY cnt DESC
    LIMIT 10
");
$top_mods_stmt->execute($is_admin ? [] : [$uid]);
$top_modules = $top_mods_stmt->fetchAll();

// ── Revenue by project type ───────────────────────────────────────────────────
$by_type_stmt = $pdo->prepare("
    SELECT pt.name AS type_name, COUNT(q.id) AS cnt,
           SUM(CASE WHEN q.status='accepted' THEN q.total_mwk ELSE 0 END) AS revenue_mwk
    FROM quotes q
    LEFT JOIN project_types pt ON q.project_type_id = pt.id
    WHERE " . ($is_admin ? '1=1' : 'q.user_id = ?') . "
    GROUP BY pt.id, pt.name
    ORDER BY revenue_mwk DESC
");
$by_type_stmt->execute($is_admin ? [] : [$uid]);
$by_type = $by_type_stmt->fetchAll();

// ── Invoice summary (table may not exist until migrate_v2 is run) ─────────────
$inv_summary = ['cnt' => 0, 'billed' => 0, 'collected' => 0];
try {
    $inv_stmt = $pdo->prepare("
        SELECT COUNT(*) AS cnt,
               SUM(amount_mwk) AS billed,
               SUM(amount_paid_mwk) AS collected
        FROM invoices WHERE " . ($is_admin ? '1=1' : 'user_id = ?'));
    $inv_stmt->execute($is_admin ? [] : [$uid]);
    $inv_summary = $inv_stmt->fetch() ?: $inv_summary;
} catch (PDOException $e) { /* table not yet created */ }

// ── Top clients ───────────────────────────────────────────────────────────────
$top_clients_stmt = $pdo->prepare("
    SELECT c.name, c.company, COUNT(q.id) AS quote_cnt,
           SUM(CASE WHEN q.status='accepted' THEN q.total_mwk ELSE 0 END) AS revenue_mwk
    FROM clients c
    LEFT JOIN quotes q ON q.client_id = c.id
    WHERE " . ($is_admin ? '1=1' : 'c.user_id = ?') . "
    GROUP BY c.id, c.name, c.company
    ORDER BY revenue_mwk DESC
    LIMIT 8
");
$top_clients_stmt->execute($is_admin ? [] : [$uid]);
$top_clients = $top_clients_stmt->fetchAll();

// Bar chart scale
$max_revenue = max(array_column($monthly_data, 'revenue_mwk') ?: [1]);

$page_title = 'Reports';
include __DIR__ . '/../includes/header.php';
?>

<div class="page-header mb-4">
  <h4 class="fw-bold mb-0"><i class="bi bi-bar-chart-line me-2 text-primary"></i>Reports & Analytics</h4>
  <p class="text-muted mb-0">Business performance overview</p>
</div>

<!-- KPI row -->
<div class="row g-3 mb-4">
  <div class="col-md-3">
    <div class="card border-0 shadow-sm">
      <div class="card-body">
        <div class="text-muted small">Quote Conversion</div>
        <div class="fw-bold fs-3 text-primary"><?= $conv_rate ?>%</div>
        <div class="text-muted small"><?= $conv['accepted'] ?> / <?= $conv['total'] ?> quotes accepted</div>
      </div>
    </div>
  </div>
  <div class="col-md-3">
    <div class="card border-0 shadow-sm">
      <div class="card-body">
        <div class="text-muted small">Total Billed</div>
        <div class="fw-bold fs-5 text-success"><?= format_mwk((float)($inv_summary['billed'] ?? 0)) ?></div>
        <div class="text-muted small"><?= (int)($inv_summary['cnt'] ?? 0) ?> invoice(s)</div>
      </div>
    </div>
  </div>
  <div class="col-md-3">
    <div class="card border-0 shadow-sm">
      <div class="card-body">
        <div class="text-muted small">Collected</div>
        <div class="fw-bold fs-5 text-success"><?= format_mwk((float)($inv_summary['collected'] ?? 0)) ?></div>
        <?php
          $outstanding = max(0, (float)($inv_summary['billed'] ?? 0) - (float)($inv_summary['collected'] ?? 0));
        ?>
        <div class="text-muted small">Balance: <?= format_mwk($outstanding) ?></div>
      </div>
    </div>
  </div>
  <div class="col-md-3">
    <div class="card border-0 shadow-sm">
      <div class="card-body">
        <div class="text-muted small">Accepted Revenue (all time)</div>
        <?php $total_rev = array_sum(array_column($monthly_data, 'revenue_mwk')); ?>
        <div class="fw-bold fs-5 text-primary"><?= format_mwk($total_rev) ?></div>
        <div class="text-muted small">Last 12 months</div>
      </div>
    </div>
  </div>
</div>

<div class="row g-3 mb-4">
  <!-- Monthly revenue chart -->
  <div class="col-lg-8">
    <div class="card border-0 shadow-sm">
      <div class="card-header bg-white border-0 fw-semibold">
        <i class="bi bi-bar-chart me-2 text-primary"></i>Monthly Revenue (Accepted Quotes)
      </div>
      <div class="card-body">
        <?php if ($monthly_data): ?>
        <div class="d-flex align-items-end gap-1" style="height:200px;overflow-x:auto">
          <?php foreach ($monthly_data as $m):
            $h = $max_revenue > 0 ? max(4, round((float)$m['revenue_mwk'] / $max_revenue * 180)) : 4;
          ?>
          <div class="d-flex flex-column align-items-center flex-shrink-0" style="min-width:50px">
            <div class="small text-muted mb-1" style="font-size:10px">
              <?= number_format((float)$m['revenue_mwk'] / 1000, 0) ?>K
            </div>
            <div class="bg-primary rounded-top" style="width:30px;height:<?= $h ?>px"
                 title="<?= h($m['label']) ?>: <?= format_mwk((float)$m['revenue_mwk']) ?>"></div>
            <div class="mt-1 text-muted text-center" style="font-size:9px;line-height:1.1">
              <?= h(substr($m['label'], 0, 3)) ?><br><?= substr($m['label'], -4) ?>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
        <?php else: ?>
        <p class="text-muted text-center py-4">No quote data yet.</p>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- Monthly table -->
  <div class="col-lg-4">
    <div class="card border-0 shadow-sm">
      <div class="card-header bg-white border-0 fw-semibold">
        <i class="bi bi-table me-2"></i>Monthly Summary
      </div>
      <div class="card-body p-0">
        <table class="table table-sm table-hover mb-0">
          <thead class="table-light">
            <tr><th>Month</th><th class="text-end">Quotes</th><th class="text-end">Revenue</th></tr>
          </thead>
          <tbody>
            <?php foreach (array_reverse($monthly_data) as $m): ?>
            <tr>
              <td class="small"><?= h($m['label']) ?></td>
              <td class="text-end small"><?= $m['accepted'] ?>/<?= $m['total_quotes'] ?></td>
              <td class="text-end small fw-semibold"><?= format_mwk((float)$m['revenue_mwk']) ?></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<div class="row g-3 mb-4">
  <!-- Top modules -->
  <div class="col-lg-6">
    <div class="card border-0 shadow-sm">
      <div class="card-header bg-white border-0 fw-semibold">
        <i class="bi bi-puzzle me-2 text-warning"></i>Most Quoted Modules
      </div>
      <div class="card-body p-0">
        <?php if ($top_modules): ?>
        <table class="table table-sm table-hover mb-0">
          <thead class="table-light">
            <tr><th>Module</th><th class="text-center">Times used</th><th class="text-end">Total value</th></tr>
          </thead>
          <tbody>
            <?php foreach ($top_modules as $mod): ?>
            <tr>
              <td><?= h($mod['module_name']) ?></td>
              <td class="text-center"><?= $mod['cnt'] ?></td>
              <td class="text-end small"><?= format_mwk((float)$mod['total_mwk']) ?></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
        <?php else: ?>
        <p class="text-muted small p-3">No modules quoted yet.</p>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- Revenue by project type -->
  <div class="col-lg-6">
    <div class="card border-0 shadow-sm">
      <div class="card-header bg-white border-0 fw-semibold">
        <i class="bi bi-grid me-2 text-info"></i>Revenue by Project Type
      </div>
      <div class="card-body">
        <?php if ($by_type):
          $max_type_rev = max(array_column($by_type, 'revenue_mwk') ?: [1]);
          foreach ($by_type as $t):
            $bar_w = $max_type_rev > 0 ? max(2, round((float)$t['revenue_mwk'] / $max_type_rev * 100)) : 2;
        ?>
        <div class="mb-2">
          <div class="d-flex justify-content-between small mb-1">
            <span><?= h($t['type_name'] ?? 'Unknown') ?></span>
            <span class="fw-semibold"><?= format_mwk((float)$t['revenue_mwk']) ?> <span class="text-muted">(<?= $t['cnt'] ?> quotes)</span></span>
          </div>
          <div class="progress" style="height:8px">
            <div class="progress-bar bg-info" style="width:<?= $bar_w ?>%"></div>
          </div>
        </div>
        <?php endforeach; else: ?>
        <p class="text-muted small">No data yet.</p>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<!-- Top clients -->
<?php if ($top_clients): ?>
<div class="card border-0 shadow-sm mb-3">
  <div class="card-header bg-white border-0 fw-semibold">
    <i class="bi bi-people me-2 text-success"></i>Top Clients by Revenue
  </div>
  <div class="card-body p-0">
    <table class="table table-hover align-middle mb-0">
      <thead class="table-light">
        <tr><th>Client</th><th>Company</th><th class="text-center">Quotes</th><th class="text-end">Accepted Revenue</th></tr>
      </thead>
      <tbody>
        <?php foreach ($top_clients as $c): ?>
        <tr>
          <td class="fw-medium"><?= h($c['name']) ?></td>
          <td class="text-muted"><?= h($c['company'] ?: '—') ?></td>
          <td class="text-center"><?= $c['quote_cnt'] ?></td>
          <td class="text-end fw-semibold"><?= format_mwk((float)$c['revenue_mwk']) ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>

<?php include __DIR__ . '/../includes/footer.php'; ?>
