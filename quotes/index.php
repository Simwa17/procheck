<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

$user = require_login();
$page_title = 'Quotes';

// Filters
$status_filter = $_GET['status'] ?? '';
$search        = trim($_GET['q'] ?? '');

$where  = ['q.user_id = ?'];
$params = [$user['id']];

if ($status_filter && in_array($status_filter, ['draft','sent','accepted','rejected'])) {
    $where[]  = 'q.status = ?';
    $params[] = $status_filter;
}
if ($search) {
    $where[]  = '(q.project_name LIKE ? OR q.quote_number LIKE ? OR c.name LIKE ?)';
    $like = '%' . $search . '%';
    $params[] = $like; $params[] = $like; $params[] = $like;
}

$sql = '
    SELECT q.*, pt.name AS project_type_name, c.name AS client_name
    FROM quotes q
    LEFT JOIN project_types pt ON q.project_type_id = pt.id
    LEFT JOIN clients c ON q.client_id = c.id
    WHERE ' . implode(' AND ', $where) . '
    ORDER BY q.created_at DESC
';
$stmt = db()->prepare($sql);
$stmt->execute($params);
$quotes   = $stmt->fetchAll();
$usd_rate = (float)setting('usd_mwk_rate', '1800');

include __DIR__ . '/../includes/header.php';
?>

<div class="page-header mb-4">
  <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
    <div>
      <h4 class="fw-bold mb-0"><i class="bi bi-file-earmark-text me-2 text-primary"></i>Quotes</h4>
      <p class="text-muted mb-0"><?= count($quotes) ?> quote<?= count($quotes) !== 1 ? 's' : '' ?> found</p>
    </div>
    <a href="<?= APP_URL ?>/quotes/create.php" class="btn btn-primary">
      <i class="bi bi-plus-lg me-1"></i>New Quote
    </a>
  </div>
</div>

<!-- Filters -->
<div class="card border-0 shadow-sm mb-3">
  <div class="card-body py-2">
    <form method="GET" class="row g-2 align-items-center">
      <div class="col-md-5">
        <div class="input-group">
          <span class="input-group-text"><i class="bi bi-search"></i></span>
          <input type="text" name="q" class="form-control" placeholder="Search by project, quote #, or client..."
                 value="<?= h($search) ?>">
        </div>
      </div>
      <div class="col-auto">
        <select name="status" class="form-select">
          <option value="">All Statuses</option>
          <?php foreach (['draft','sent','accepted','rejected'] as $s): ?>
          <option value="<?= $s ?>" <?= $status_filter === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-auto">
        <button type="submit" class="btn btn-outline-primary">Filter</button>
        <?php if ($search || $status_filter): ?>
          <a href="<?= APP_URL ?>/quotes/index.php" class="btn btn-outline-secondary">Clear</a>
        <?php endif; ?>
      </div>
    </form>
  </div>
</div>

<div class="card border-0 shadow-sm">
  <div class="card-body p-0">
    <?php if (empty($quotes)): ?>
    <div class="text-center py-5 text-muted">
      <i class="bi bi-file-earmark-plus display-5 d-block mb-3 text-secondary"></i>
      No quotes found.
      <a href="<?= APP_URL ?>/quotes/create.php">Create your first quote!</a>
    </div>
    <?php else: ?>
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead class="table-light">
          <tr>
            <th>Quote #</th>
            <th>Project</th>
            <th>Client</th>
            <th>Tier</th>
            <th class="text-end">Hours</th>
            <th class="text-end">Total (MWK)</th>
            <th>Status</th>
            <th>Date</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($quotes as $q): ?>
          <tr>
            <td><a href="<?= APP_URL ?>/quotes/view.php?id=<?= $q['id'] ?>" class="fw-semibold text-decoration-none"><code><?= h($q['quote_number']) ?></code></a></td>
            <td>
              <?= h($q['project_name']) ?>
              <?php if ($q['project_type_name']): ?>
                <div class="text-muted small"><?= h($q['project_type_name']) ?></div>
              <?php endif; ?>
            </td>
            <td class="text-muted"><?= h($q['client_name'] ?? '—') ?></td>
            <td><?= tier_badge($q['developer_tier']) ?></td>
            <td class="text-end"><?= number_format($q['total_hours'], 1) ?></td>
            <td class="text-end">
              <span class="fw-semibold"><?= format_mwk($q['total_mwk']) ?></span>
              <div class="text-muted small"><?= format_usd($q['total_mwk'] / $usd_rate) ?></div>
            </td>
            <td><?= badge_status($q['status']) ?></td>
            <td class="text-muted small"><?= date('d M Y', strtotime($q['created_at'])) ?></td>
            <td>
              <div class="btn-group btn-group-sm">
                <a href="<?= APP_URL ?>/quotes/view.php?id=<?= $q['id'] ?>" class="btn btn-outline-primary" title="View"><i class="bi bi-eye"></i></a>
                <a href="<?= APP_URL ?>/quotes/print.php?id=<?= $q['id'] ?>" class="btn btn-outline-secondary" title="Print" target="_blank"><i class="bi bi-printer"></i></a>
              </div>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>
  </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
