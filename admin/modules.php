<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

$user = require_admin();
$pdo  = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $category_id  = (int)($_POST['category_id'] ?? 0) ?: null;
        $name         = trim($_POST['name'] ?? '');
        $description  = trim($_POST['description'] ?? '');
        $simple_hours = max(0, (float)($_POST['simple_hours'] ?? 4));
        $medium_hours = max(0, (float)($_POST['medium_hours'] ?? 8));
        $complex_hours= max(0, (float)($_POST['complex_hours'] ?? 16));
        if ($name) {
            $pdo->prepare('INSERT INTO modules (category_id,name,description,simple_hours,medium_hours,complex_hours) VALUES (?,?,?,?,?,?)')
                ->execute([$category_id, $name, $description ?: null, $simple_hours, $medium_hours, $complex_hours]);
            flash_set('success', 'Module added.');
        }
    } elseif ($action === 'toggle') {
        $id = (int)$_POST['id'];
        $pdo->prepare('UPDATE modules SET is_active = NOT is_active WHERE id = ?')->execute([$id]);
    } elseif ($action === 'delete') {
        $id = (int)$_POST['id'];
        $pdo->prepare('DELETE FROM modules WHERE id = ?')->execute([$id]);
        flash_set('success', 'Module deleted.');
    } elseif ($action === 'add_category') {
        $name = trim($_POST['cat_name'] ?? '');
        if ($name) {
            $pdo->prepare('INSERT INTO module_categories (name) VALUES (?)')->execute([$name]);
            flash_set('success', 'Category added.');
        }
    }
    header('Location: ' . APP_URL . '/admin/modules.php');
    exit;
}

$modules = $pdo->query('
    SELECT m.*, mc.name AS category_name
    FROM modules m
    LEFT JOIN module_categories mc ON m.category_id = mc.id
    ORDER BY mc.name, m.name
')->fetchAll();

$categories = $pdo->query('SELECT * FROM module_categories ORDER BY name')->fetchAll();

$page_title = 'Modules';
include __DIR__ . '/../includes/header.php';
?>

<div class="page-header mb-4">
  <div class="d-flex justify-content-between align-items-center">
    <div>
      <h4 class="fw-bold mb-0"><i class="bi bi-puzzle me-2 text-primary"></i>Modules</h4>
      <p class="text-muted mb-0">Manage project feature modules and their estimated hours</p>
    </div>
    <a href="<?= APP_URL ?>/admin/index.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i>Back</a>
  </div>
</div>

<div class="row g-3">
  <div class="col-md-9">
    <div class="card border-0 shadow-sm">
      <div class="card-header bg-white border-0">
        <span class="fw-semibold"><?= count($modules) ?> modules</span>
      </div>
      <div class="card-body p-0">
        <div class="table-responsive">
          <table class="table table-hover table-sm align-middle mb-0">
            <thead class="table-light">
              <tr>
                <th>Module</th>
                <th>Category</th>
                <th class="text-center">Simple (h)</th>
                <th class="text-center">Medium (h)</th>
                <th class="text-center">Complex (h)</th>
                <th class="text-center">Active</th>
                <th></th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($modules as $m): ?>
              <tr class="<?= !$m['is_active'] ? 'table-secondary text-muted' : '' ?>">
                <td>
                  <div class="fw-medium"><?= h($m['name']) ?></div>
                  <?php if ($m['description']): ?><div class="text-muted" style="font-size:.75rem"><?= h($m['description']) ?></div><?php endif; ?>
                </td>
                <td class="text-muted small"><?= h($m['category_name'] ?? 'Uncategorized') ?></td>
                <td class="text-center"><?= $m['simple_hours'] ?></td>
                <td class="text-center"><?= $m['medium_hours'] ?></td>
                <td class="text-center"><?= $m['complex_hours'] ?></td>
                <td class="text-center">
                  <form method="POST" class="d-inline">
                    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                    <input type="hidden" name="action" value="toggle">
                    <input type="hidden" name="id" value="<?= $m['id'] ?>">
                    <button type="submit" class="btn btn-xs <?= $m['is_active'] ? 'btn-success' : 'btn-outline-secondary' ?> btn-sm">
                      <?= $m['is_active'] ? '✓' : '✗' ?>
                    </button>
                  </form>
                </td>
                <td>
                  <form method="POST" class="d-inline" onsubmit="return confirm('Delete module?')">
                    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" value="<?= $m['id'] ?>">
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
  </div>

  <div class="col-md-3">
    <!-- Add Module -->
    <div class="card border-0 shadow-sm mb-3">
      <div class="card-header bg-white border-0"><h6 class="fw-semibold mb-0 small"><i class="bi bi-plus me-1"></i>Add Module</h6></div>
      <div class="card-body p-3">
        <form method="POST">
          <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
          <input type="hidden" name="action" value="add">
          <div class="mb-2">
            <input type="text" name="name" class="form-control form-control-sm" placeholder="Module name *" required>
          </div>
          <div class="mb-2">
            <input type="text" name="description" class="form-control form-control-sm" placeholder="Description">
          </div>
          <div class="mb-2">
            <select name="category_id" class="form-select form-select-sm">
              <option value="">No category</option>
              <?php foreach ($categories as $cat): ?>
              <option value="<?= $cat['id'] ?>"><?= h($cat['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="row g-1 mb-2">
            <div class="col"><input type="number" name="simple_hours" class="form-control form-control-sm" placeholder="Sim.hrs" value="4" min="0" step="0.5"></div>
            <div class="col"><input type="number" name="medium_hours" class="form-control form-control-sm" placeholder="Med.hrs" value="8" min="0" step="0.5"></div>
            <div class="col"><input type="number" name="complex_hours" class="form-control form-control-sm" placeholder="Cmp.hrs" value="16" min="0" step="0.5"></div>
          </div>
          <div class="form-text mb-2">Hours: Simple / Medium / Complex</div>
          <button type="submit" class="btn btn-primary btn-sm w-100">Add Module</button>
        </form>
      </div>
    </div>

    <!-- Add Category -->
    <div class="card border-0 shadow-sm">
      <div class="card-header bg-white border-0"><h6 class="fw-semibold mb-0 small"><i class="bi bi-folder-plus me-1"></i>Add Category</h6></div>
      <div class="card-body p-3">
        <form method="POST">
          <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
          <input type="hidden" name="action" value="add_category">
          <div class="mb-2">
            <input type="text" name="cat_name" class="form-control form-control-sm" placeholder="Category name *" required>
          </div>
          <button type="submit" class="btn btn-outline-primary btn-sm w-100">Add Category</button>
        </form>
      </div>
    </div>
  </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
