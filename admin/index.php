<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

$user = require_admin();
$page_title = 'Admin Dashboard';

$pdo = db();
$total_users  = (int)$pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
$total_quotes = (int)$pdo->query('SELECT COUNT(*) FROM quotes')->fetchColumn();
$total_mwk    = (float)$pdo->query("SELECT COALESCE(SUM(total_mwk),0) FROM quotes WHERE status='accepted'")->fetchColumn();
$total_modules = (int)$pdo->query('SELECT COUNT(*) FROM modules')->fetchColumn();

$recent = $pdo->query('
    SELECT q.*, u.name AS user_name, c.name AS client_name
    FROM quotes q
    JOIN users u ON q.user_id = u.id
    LEFT JOIN clients c ON q.client_id = c.id
    ORDER BY q.created_at DESC LIMIT 10
')->fetchAll();

include __DIR__ . '/../includes/header.php';
?>

<div class="page-header mb-4">
  <h4 class="fw-bold mb-0"><i class="bi bi-shield-lock me-2 text-danger"></i>Admin Dashboard</h4>
  <p class="text-muted mb-0">System overview and management</p>
</div>

<div class="row g-3 mb-4">
  <div class="col-6 col-md-3">
    <div class="stat-card card border-0 shadow-sm h-100">
      <div class="card-body">
        <div class="stat-icon bg-primary-light text-primary"><i class="bi bi-people"></i></div>
        <div class="stat-value"><?= $total_users ?></div>
        <div class="stat-label">Users</div>
        <a href="<?= APP_URL ?>/admin/users.php" class="stretched-link"></a>
      </div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="stat-card card border-0 shadow-sm h-100">
      <div class="card-body">
        <div class="stat-icon bg-info-light text-info"><i class="bi bi-file-earmark-text"></i></div>
        <div class="stat-value"><?= $total_quotes ?></div>
        <div class="stat-label">Total Quotes</div>
      </div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="stat-card card border-0 shadow-sm h-100">
      <div class="card-body">
        <div class="stat-icon bg-success-light text-success"><i class="bi bi-cash-stack"></i></div>
        <div class="stat-value"><?= 'MWK ' . number_format($total_mwk / 1000, 0) . 'K' ?></div>
        <div class="stat-label">Accepted Revenue</div>
      </div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="stat-card card border-0 shadow-sm h-100">
      <div class="card-body">
        <div class="stat-icon bg-warning-light text-warning"><i class="bi bi-puzzle"></i></div>
        <div class="stat-value"><?= $total_modules ?></div>
        <div class="stat-label">Modules</div>
        <a href="<?= APP_URL ?>/admin/modules.php" class="stretched-link"></a>
      </div>
    </div>
  </div>
</div>

<div class="row g-3">
  <div class="col-md-8">
    <div class="card border-0 shadow-sm">
      <div class="card-header bg-white border-0"><h6 class="fw-semibold mb-0">Recent System Quotes</h6></div>
      <div class="card-body p-0">
        <div class="table-responsive">
          <table class="table table-hover table-sm mb-0">
            <thead class="table-light">
              <tr><th>Quote #</th><th>Project</th><th>User</th><th>Total (MWK)</th><th>Status</th></tr>
            </thead>
            <tbody>
              <?php foreach ($recent as $q): ?>
              <tr>
                <td><a href="<?= APP_URL ?>/quotes/view.php?id=<?= $q['id'] ?>"><code class="small"><?= h($q['quote_number']) ?></code></a></td>
                <td><?= h($q['project_name']) ?></td>
                <td class="text-muted"><?= h($q['user_name']) ?></td>
                <td><?= format_mwk($q['total_mwk']) ?></td>
                <td><?= badge_status($q['status']) ?></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
  <div class="col-md-4">
    <div class="card border-0 shadow-sm">
      <div class="card-body">
        <h6 class="fw-semibold mb-3">Admin Quick Links</h6>
        <div class="d-grid gap-2">
          <a href="<?= APP_URL ?>/admin/rates.php" class="btn btn-outline-primary btn-sm text-start">
            <i class="bi bi-cash-coin me-2"></i>Manage Developer Rates
          </a>
          <a href="<?= APP_URL ?>/admin/modules.php" class="btn btn-outline-primary btn-sm text-start">
            <i class="bi bi-puzzle me-2"></i>Manage Modules
          </a>
          <a href="<?= APP_URL ?>/admin/project_types.php" class="btn btn-outline-primary btn-sm text-start">
            <i class="bi bi-grid me-2"></i>Project Types
          </a>
          <a href="<?= APP_URL ?>/admin/users.php" class="btn btn-outline-primary btn-sm text-start">
            <i class="bi bi-people me-2"></i>Manage Users
          </a>
          <a href="<?= APP_URL ?>/admin/settings.php" class="btn btn-outline-secondary btn-sm text-start">
            <i class="bi bi-gear me-2"></i>System Settings
          </a>
        </div>
      </div>
    </div>
  </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
