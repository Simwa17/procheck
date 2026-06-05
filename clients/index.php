<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

$user = require_login();
$page_title = 'Clients';

$clients = get_clients($user['id']);
include __DIR__ . '/../includes/header.php';
?>

<div class="page-header mb-4">
  <div class="d-flex justify-content-between align-items-center">
    <div>
      <h4 class="fw-bold mb-0"><i class="bi bi-people me-2 text-primary"></i>Clients</h4>
      <p class="text-muted mb-0"><?= count($clients) ?> client<?= count($clients) !== 1 ? 's' : '' ?></p>
    </div>
    <a href="<?= APP_URL ?>/clients/create.php" class="btn btn-primary">
      <i class="bi bi-person-plus me-1"></i>Add Client
    </a>
  </div>
</div>

<div class="card border-0 shadow-sm">
  <div class="card-body p-0">
    <?php if (empty($clients)): ?>
    <div class="text-center py-5 text-muted">
      <i class="bi bi-person-plus display-5 d-block mb-3 text-secondary"></i>
      No clients yet. <a href="<?= APP_URL ?>/clients/create.php">Add your first client!</a>
    </div>
    <?php else: ?>
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead class="table-light">
          <tr>
            <th>Name</th>
            <th>Company</th>
            <th>Email</th>
            <th>Phone</th>
            <th>Added</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($clients as $c): ?>
          <tr>
            <td class="fw-medium"><?= h($c['name']) ?></td>
            <td class="text-muted"><?= h($c['company'] ?? '—') ?></td>
            <td><?= h($c['email'] ?? '—') ?></td>
            <td><?= h($c['phone'] ?? '—') ?></td>
            <td class="text-muted small"><?= date('d M Y', strtotime($c['created_at'])) ?></td>
            <td>
              <div class="btn-group btn-group-sm">
                <a href="<?= APP_URL ?>/clients/edit.php?id=<?= $c['id'] ?>" class="btn btn-outline-secondary" title="Edit"><i class="bi bi-pencil"></i></a>
                <a href="<?= APP_URL ?>/clients/delete.php?id=<?= $c['id'] ?>" class="btn btn-outline-danger" title="Delete"
                   onclick="return confirm('Delete this client?')"><i class="bi bi-trash"></i></a>
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
