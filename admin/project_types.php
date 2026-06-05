<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

$user = require_admin();
$pdo  = db();

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $name        = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $icon        = trim($_POST['icon'] ?? 'bi-grid');
        $base_hours  = max(0, (float)($_POST['base_hours'] ?? 0));
        if ($name) {
            $pdo->prepare('INSERT INTO project_types (name,description,icon,base_hours) VALUES (?,?,?,?)')
                ->execute([$name, $description ?: null, $icon, $base_hours]);
            flash_set('success', 'Project type added.');
        }
    } elseif ($action === 'toggle') {
        $id = (int)$_POST['id'];
        $pdo->prepare('UPDATE project_types SET is_active = NOT is_active WHERE id = ?')->execute([$id]);
    } elseif ($action === 'delete') {
        $id = (int)$_POST['id'];
        $pdo->prepare('DELETE FROM project_types WHERE id = ?')->execute([$id]);
        flash_set('success', 'Project type deleted.');
    }
    header('Location: ' . APP_URL . '/admin/project_types.php');
    exit;
}

$types = $pdo->query('SELECT * FROM project_types ORDER BY name')->fetchAll();
$page_title = 'Project Types';
include __DIR__ . '/../includes/header.php';
?>

<div class="page-header mb-4">
  <div class="d-flex justify-content-between align-items-center">
    <div>
      <h4 class="fw-bold mb-0"><i class="bi bi-grid me-2 text-primary"></i>Project Types</h4>
      <p class="text-muted mb-0">Manage the types of projects developers can price</p>
    </div>
    <a href="<?= APP_URL ?>/admin/index.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i>Back</a>
  </div>
</div>

<div class="row g-3">
  <div class="col-md-8">
    <div class="card border-0 shadow-sm">
      <div class="card-body p-0">
        <table class="table table-hover align-middle mb-0">
          <thead class="table-light">
            <tr><th>Type</th><th>Description</th><th class="text-center">Base Hrs</th><th class="text-center">Active</th><th></th></tr>
          </thead>
          <tbody>
            <?php foreach ($types as $t): ?>
            <tr class="<?= !$t['is_active'] ? 'table-secondary text-muted' : '' ?>">
              <td>
                <i class="<?= h($t['icon']) ?> me-2 text-primary"></i>
                <strong><?= h($t['name']) ?></strong>
              </td>
              <td class="small"><?= h($t['description'] ?? '—') ?></td>
              <td class="text-center"><?= $t['base_hours'] ?>h</td>
              <td class="text-center">
                <form method="POST" class="d-inline">
                  <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                  <input type="hidden" name="action" value="toggle">
                  <input type="hidden" name="id" value="<?= $t['id'] ?>">
                  <button type="submit" class="btn btn-sm <?= $t['is_active'] ? 'btn-success' : 'btn-outline-secondary' ?>">
                    <?= $t['is_active'] ? 'Active' : 'Inactive' ?>
                  </button>
                </form>
              </td>
              <td>
                <form method="POST" class="d-inline" onsubmit="return confirm('Delete this project type?')">
                  <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                  <input type="hidden" name="action" value="delete">
                  <input type="hidden" name="id" value="<?= $t['id'] ?>">
                  <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                </form>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <div class="col-md-4">
    <div class="card border-0 shadow-sm">
      <div class="card-header bg-white border-0"><h6 class="fw-semibold mb-0"><i class="bi bi-plus me-2"></i>Add Project Type</h6></div>
      <div class="card-body">
        <form method="POST">
          <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
          <input type="hidden" name="action" value="add">
          <div class="mb-3">
            <label class="form-label small fw-medium">Name <span class="text-danger">*</span></label>
            <input type="text" name="name" class="form-control form-control-sm" placeholder="e.g. Mobile App" required>
          </div>
          <div class="mb-3">
            <label class="form-label small fw-medium">Description</label>
            <input type="text" name="description" class="form-control form-control-sm" placeholder="Short description">
          </div>
          <div class="mb-3">
            <label class="form-label small fw-medium">Bootstrap Icon class</label>
            <input type="text" name="icon" class="form-control form-control-sm" value="bi-grid" placeholder="bi-grid">
            <div class="form-text"><a href="https://icons.getbootstrap.com/" target="_blank">Browse icons</a></div>
          </div>
          <div class="mb-3">
            <label class="form-label small fw-medium">Base Hours</label>
            <input type="number" name="base_hours" class="form-control form-control-sm" value="0" min="0">
          </div>
          <button type="submit" class="btn btn-primary btn-sm w-100">Add Type</button>
        </form>
      </div>
    </div>
  </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
