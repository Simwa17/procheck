<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

$user = require_login();
$page_title = 'Dashboard';

// Stats
$pdo = db();
$total_quotes = $pdo->prepare('SELECT COUNT(*) FROM quotes WHERE user_id = ?');
$total_quotes->execute([$user['id']]);
$total_quotes = (int)$total_quotes->fetchColumn();

$accepted = $pdo->prepare("SELECT COUNT(*) FROM quotes WHERE user_id = ? AND status = 'accepted'");
$accepted->execute([$user['id']]);
$accepted = (int)$accepted->fetchColumn();

$total_mwk = $pdo->prepare("SELECT COALESCE(SUM(total_mwk),0) FROM quotes WHERE user_id = ? AND status = 'accepted'");
$total_mwk->execute([$user['id']]);
$total_mwk = (float)$total_mwk->fetchColumn();

$total_clients = $pdo->prepare('SELECT COUNT(*) FROM clients WHERE user_id = ?');
$total_clients->execute([$user['id']]);
$total_clients = (int)$total_clients->fetchColumn();

// Recent quotes
$recent = $pdo->prepare('
    SELECT q.*, pt.name AS project_type_name, c.name AS client_name
    FROM quotes q
    LEFT JOIN project_types pt ON q.project_type_id = pt.id
    LEFT JOIN clients c ON q.client_id = c.id
    WHERE q.user_id = ?
    ORDER BY q.created_at DESC LIMIT 8
');
$recent->execute([$user['id']]);
$recent_quotes = $recent->fetchAll();

$usd_rate = (float)setting('usd_mwk_rate', '1800');

include __DIR__ . '/includes/header.php';
?>

<div class="page-header mb-4">
  <h4 class="fw-bold mb-0">
    <i class="bi bi-speedometer2 me-2 text-primary"></i>Dashboard
  </h4>
  <p class="text-muted mb-0">Welcome back, <?= h($user['name']) ?> 👋</p>
</div>

<!-- Stats Row -->
<div class="row g-3 mb-4">
  <div class="col-6 col-md-3">
    <div class="stat-card card border-0 shadow-sm h-100">
      <div class="card-body">
        <div class="stat-icon bg-primary-light text-primary"><i class="bi bi-file-earmark-text"></i></div>
        <div class="stat-value"><?= $total_quotes ?></div>
        <div class="stat-label">Total Quotes</div>
      </div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="stat-card card border-0 shadow-sm h-100">
      <div class="card-body">
        <div class="stat-icon bg-success-light text-success"><i class="bi bi-check-circle"></i></div>
        <div class="stat-value"><?= $accepted ?></div>
        <div class="stat-label">Accepted</div>
      </div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="stat-card card border-0 shadow-sm h-100">
      <div class="card-body">
        <div class="stat-icon bg-warning-light text-warning"><i class="bi bi-cash-coin"></i></div>
        <div class="stat-value"><?= 'MWK ' . number_format($total_mwk / 1000, 0) . 'K' ?></div>
        <div class="stat-label">Revenue Earned</div>
      </div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="stat-card card border-0 shadow-sm h-100">
      <div class="card-body">
        <div class="stat-icon bg-info-light text-info"><i class="bi bi-people"></i></div>
        <div class="stat-value"><?= $total_clients ?></div>
        <div class="stat-label">Clients</div>
      </div>
    </div>
  </div>
</div>

<!-- Quick Actions + Recent Quotes -->
<div class="row g-3">
  <div class="col-md-8">
    <div class="card border-0 shadow-sm">
      <div class="card-header d-flex justify-content-between align-items-center bg-white border-0 pb-0">
        <h6 class="fw-semibold mb-0"><i class="bi bi-clock-history me-2 text-primary"></i>Recent Quotes</h6>
        <a href="<?= APP_URL ?>/quotes/index.php" class="btn btn-sm btn-outline-primary">View All</a>
      </div>
      <div class="card-body p-0">
        <?php if (empty($recent_quotes)): ?>
          <div class="text-center py-5 text-muted">
            <i class="bi bi-file-earmark-plus display-5 d-block mb-3 text-secondary"></i>
            No quotes yet. <a href="<?= APP_URL ?>/quotes/create.php">Create your first quote!</a>
          </div>
        <?php else: ?>
        <div class="table-responsive">
          <table class="table table-hover table-sm align-middle mb-0">
            <thead class="table-light">
              <tr>
                <th>Quote #</th>
                <th>Project</th>
                <th>Client</th>
                <th>Total</th>
                <th>Status</th>
                <th></th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($recent_quotes as $q): ?>
              <tr>
                <td><code class="small"><?= h($q['quote_number']) ?></code></td>
                <td>
                  <?= h($q['project_name']) ?>
                  <?php if ($q['project_type_name']): ?>
                    <div class="text-muted small"><?= h($q['project_type_name']) ?></div>
                  <?php endif; ?>
                </td>
                <td class="text-muted small"><?= h($q['client_name'] ?? '—') ?></td>
                <td>
                  <span class="fw-semibold"><?= format_mwk($q['total_mwk']) ?></span>
                  <div class="text-muted small"><?= format_usd($q['total_mwk'] / $usd_rate) ?></div>
                </td>
                <td><?= badge_status($q['status']) ?></td>
                <td>
                  <a href="<?= APP_URL ?>/quotes/view.php?id=<?= $q['id'] ?>" class="btn btn-sm btn-outline-secondary">
                    <i class="bi bi-eye"></i>
                  </a>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <div class="col-md-4">
    <div class="card border-0 shadow-sm mb-3">
      <div class="card-body">
        <h6 class="fw-semibold mb-3"><i class="bi bi-lightning-charge me-2 text-warning"></i>Quick Actions</h6>
        <div class="d-grid gap-2">
          <a href="<?= APP_URL ?>/quotes/create.php" class="btn btn-primary">
            <i class="bi bi-plus-lg me-2"></i>New Quote
          </a>
          <a href="<?= APP_URL ?>/clients/create.php" class="btn btn-outline-secondary">
            <i class="bi bi-person-plus me-2"></i>Add Client
          </a>
          <a href="<?= APP_URL ?>/quotes/index.php" class="btn btn-outline-secondary">
            <i class="bi bi-file-earmark-text me-2"></i>All Quotes
          </a>
        </div>
      </div>
    </div>

    <!-- Developer Rates Card -->
    <div class="card border-0 shadow-sm">
      <div class="card-body">
        <h6 class="fw-semibold mb-3"><i class="bi bi-cash-coin me-2 text-success"></i>Current Rates (MWK/hr)</h6>
        <?php foreach (get_developer_rates() as $rate): ?>
        <div class="d-flex justify-content-between align-items-center mb-2">
          <?= tier_badge($rate['tier']) ?>
          <span class="fw-semibold"><?= format_mwk($rate['hourly_rate_mwk']) ?></span>
        </div>
        <?php endforeach; ?>
        <div class="text-muted small mt-2 pt-2 border-top">
          USD rate: 1 USD = <?= number_format($usd_rate, 0) ?> MWK
        </div>
      </div>
    </div>
  </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
