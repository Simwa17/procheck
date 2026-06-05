<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

$user = require_admin();
$pdo  = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = $_POST['action'] ?? '';

    if ($action === 'toggle_role') {
        $id = (int)$_POST['id'];
        if ($id !== $user['id']) { // Cannot change own role
            $stmt = $pdo->prepare("UPDATE users SET role = CASE WHEN role='admin' THEN 'user' ELSE 'admin' END WHERE id = ?");
            $stmt->execute([$id]);
        }
    } elseif ($action === 'delete') {
        $id = (int)$_POST['id'];
        if ($id !== $user['id']) {
            $pdo->prepare('DELETE FROM users WHERE id = ?')->execute([$id]);
            flash_set('success', 'User deleted.');
        }
    } elseif ($action === 'reset_password') {
        $id   = (int)$_POST['id'];
        $pass = trim($_POST['new_password'] ?? '');
        if (strlen($pass) >= 8) {
            $hash = password_hash($pass, PASSWORD_BCRYPT);
            $pdo->prepare('UPDATE users SET password = ? WHERE id = ?')->execute([$hash, $id]);
            flash_set('success', 'Password reset.');
        } else {
            flash_set('error', 'Password must be at least 8 characters.');
        }
    }
    header('Location: ' . APP_URL . '/admin/users.php');
    exit;
}

$users = $pdo->query('
    SELECT u.*, COUNT(q.id) AS quote_count
    FROM users u
    LEFT JOIN quotes q ON q.user_id = u.id
    GROUP BY u.id
    ORDER BY u.created_at DESC
')->fetchAll();

$page_title = 'Users';
include __DIR__ . '/../includes/header.php';
?>

<div class="page-header mb-4">
  <div class="d-flex justify-content-between align-items-center">
    <div>
      <h4 class="fw-bold mb-0"><i class="bi bi-people me-2 text-primary"></i>Users</h4>
      <p class="text-muted mb-0"><?= count($users) ?> registered users</p>
    </div>
    <a href="<?= APP_URL ?>/admin/index.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i>Back</a>
  </div>
</div>

<div class="card border-0 shadow-sm">
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead class="table-light">
          <tr><th>User</th><th>Email</th><th class="text-center">Quotes</th><th class="text-center">Role</th><th>Joined</th><th>Actions</th></tr>
        </thead>
        <tbody>
          <?php foreach ($users as $u): ?>
          <tr>
            <td>
              <div class="d-flex align-items-center gap-2">
                <div class="avatar-circle avatar-sm"><?= strtoupper(substr($u['name'], 0, 1)) ?></div>
                <div>
                  <div class="fw-medium"><?= h($u['name']) ?></div>
                  <?php if ($u['id'] === $user['id']): ?><span class="badge bg-secondary" style="font-size:.65rem">You</span><?php endif; ?>
                </div>
              </div>
            </td>
            <td class="text-muted"><?= h($u['email']) ?></td>
            <td class="text-center"><?= $u['quote_count'] ?></td>
            <td class="text-center">
              <form method="POST" class="d-inline">
                <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                <input type="hidden" name="action" value="toggle_role">
                <input type="hidden" name="id" value="<?= $u['id'] ?>">
                <button type="submit" class="btn btn-sm <?= $u['role']==='admin' ? 'btn-danger' : 'btn-outline-secondary' ?>"
                        <?= $u['id']==$user['id'] ? 'disabled' : '' ?>>
                  <?= ucfirst($u['role']) ?>
                </button>
              </form>
            </td>
            <td class="text-muted small"><?= date('d M Y', strtotime($u['created_at'])) ?></td>
            <td>
              <div class="btn-group btn-group-sm">
                <!-- Reset Password -->
                <button type="button" class="btn btn-outline-warning" data-bs-toggle="modal" data-bs-target="#pwModal<?= $u['id'] ?>">
                  <i class="bi bi-key"></i>
                </button>
                <!-- Delete -->
                <?php if ($u['id'] !== $user['id']): ?>
                <form method="POST" class="d-inline" onsubmit="return confirm('Delete user <?= addslashes($u['name']) ?>?')">
                  <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                  <input type="hidden" name="action" value="delete">
                  <input type="hidden" name="id" value="<?= $u['id'] ?>">
                  <button type="submit" class="btn btn-outline-danger"><i class="bi bi-trash"></i></button>
                </form>
                <?php endif; ?>
              </div>
            </td>
          </tr>

          <!-- Password Reset Modal -->
          <div class="modal fade" id="pwModal<?= $u['id'] ?>" tabindex="-1">
            <div class="modal-dialog modal-sm">
              <div class="modal-content">
                <div class="modal-header"><h6 class="modal-title">Reset Password for <?= h($u['name']) ?></h6>
                  <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                  <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                    <input type="hidden" name="action" value="reset_password">
                    <input type="hidden" name="id" value="<?= $u['id'] ?>">
                    <label class="form-label small">New Password (min 8 chars)</label>
                    <input type="password" name="new_password" class="form-control" minlength="8" required>
                  </div>
                  <div class="modal-footer">
                    <button type="submit" class="btn btn-warning btn-sm">Reset</button>
                  </div>
                </form>
              </div>
            </div>
          </div>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
