<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

$user = require_login();
$pdo  = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = $_POST['action'] ?? '';

    if ($action === 'update_profile') {
        $name  = trim($_POST['name'] ?? '');
        $email = strtolower(trim($_POST['email'] ?? ''));

        if (!$name || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            flash_set('error', 'Please provide a valid name and email address.');
        } else {
            // Check email not already used by another user
            $chk = $pdo->prepare('SELECT id FROM users WHERE email = ? AND id != ?');
            $chk->execute([$email, $user['id']]);
            if ($chk->fetch()) {
                flash_set('error', 'That email address is already taken.');
            } else {
                $pdo->prepare('UPDATE users SET name=?, email=? WHERE id=?')
                    ->execute([$name, $email, $user['id']]);
                // Update session
                $_SESSION['user']['name']  = $name;
                $_SESSION['user']['email'] = $email;
                flash_set('success', 'Profile updated.');
            }
        }

    } elseif ($action === 'change_password') {
        $current  = $_POST['current_password'] ?? '';
        $new_pw   = $_POST['new_password'] ?? '';
        $confirm  = $_POST['confirm_password'] ?? '';

        // Load current hash
        $row = $pdo->prepare('SELECT password FROM users WHERE id = ?');
        $row->execute([$user['id']]);
        $hash = $row->fetch()['password'] ?? '';

        if (!password_verify($current, $hash)) {
            flash_set('error', 'Current password is incorrect.');
        } elseif (strlen($new_pw) < 8) {
            flash_set('error', 'New password must be at least 8 characters.');
        } elseif ($new_pw !== $confirm) {
            flash_set('error', 'New passwords do not match.');
        } else {
            $pdo->prepare('UPDATE users SET password=? WHERE id=?')
                ->execute([password_hash($new_pw, PASSWORD_BCRYPT), $user['id']]);
            flash_set('success', 'Password changed successfully.');
        }
    }

    header('Location: ' . APP_URL . '/profile.php');
    exit;
}

// Load fresh user data
$row = $pdo->prepare('SELECT * FROM users WHERE id = ?');
$row->execute([$user['id']]);
$me = $row->fetch();

// Stats
$quote_cnt = (int)$pdo->prepare('SELECT COUNT(*) FROM quotes WHERE user_id = ?')->execute([$user['id']]) ? $pdo->query("SELECT COUNT(*) FROM quotes WHERE user_id={$user['id']}")->fetchColumn() : 0;
$stmt = $pdo->prepare('SELECT COUNT(*) AS cnt, SUM(CASE WHEN status="accepted" THEN 1 ELSE 0 END) AS acc FROM quotes WHERE user_id=?');
$stmt->execute([$user['id']]);
$stats = $stmt->fetch();

$page_title = 'My Profile';
include __DIR__ . '/includes/header.php';
?>

<div class="page-header mb-4">
  <h4 class="fw-bold mb-0"><i class="bi bi-person-circle me-2 text-primary"></i>My Profile</h4>
</div>

<div class="row g-3">
  <div class="col-md-4">

    <!-- Avatar card -->
    <div class="card border-0 shadow-sm mb-3 text-center">
      <div class="card-body py-4">
        <div class="rounded-circle bg-primary text-white d-inline-flex align-items-center justify-content-center mb-3"
             style="width:80px;height:80px;font-size:2rem;font-weight:600">
          <?= strtoupper(substr($me['name'], 0, 1)) ?>
        </div>
        <h5 class="fw-bold mb-1"><?= h($me['name']) ?></h5>
        <div class="text-muted small mb-2"><?= h($me['email']) ?></div>
        <span class="badge <?= $me['role'] === 'admin' ? 'bg-danger' : 'bg-secondary' ?>">
          <?= ucfirst($me['role']) ?>
        </span>
        <hr class="my-3">
        <div class="row text-center g-2">
          <div class="col-6">
            <div class="fw-bold fs-5 text-primary"><?= $stats['cnt'] ?></div>
            <div class="text-muted small">Quotes</div>
          </div>
          <div class="col-6">
            <div class="fw-bold fs-5 text-success"><?= $stats['acc'] ?></div>
            <div class="text-muted small">Accepted</div>
          </div>
        </div>
        <div class="text-muted small mt-2">
          Member since <?= date('M Y', strtotime($me['created_at'])) ?>
        </div>
      </div>
    </div>

  </div>
  <div class="col-md-8">

    <!-- Update profile -->
    <div class="card border-0 shadow-sm mb-3">
      <div class="card-body">
        <h6 class="fw-semibold mb-3"><i class="bi bi-pencil me-2"></i>Update Profile</h6>
        <form method="POST">
          <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
          <input type="hidden" name="action" value="update_profile">
          <div class="mb-3">
            <label class="form-label fw-medium">Full Name</label>
            <input type="text" name="name" class="form-control" value="<?= h($me['name']) ?>" required>
          </div>
          <div class="mb-3">
            <label class="form-label fw-medium">Email Address</label>
            <input type="email" name="email" class="form-control" value="<?= h($me['email']) ?>" required>
            <?php if (isset($me['email_verified']) && !$me['email_verified']): ?>
            <div class="form-text text-warning">
              <i class="bi bi-exclamation-triangle me-1"></i>Email not verified.
              <a href="<?= APP_URL ?>/resend_verify.php">Resend verification</a>
            </div>
            <?php endif; ?>
          </div>
          <button type="submit" class="btn btn-primary">Save Profile</button>
        </form>
      </div>
    </div>

    <!-- Change password -->
    <div class="card border-0 shadow-sm">
      <div class="card-body">
        <h6 class="fw-semibold mb-3"><i class="bi bi-key me-2"></i>Change Password</h6>
        <form method="POST">
          <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
          <input type="hidden" name="action" value="change_password">
          <div class="mb-3">
            <label class="form-label fw-medium">Current Password</label>
            <input type="password" name="current_password" class="form-control" autocomplete="current-password" required>
          </div>
          <div class="mb-3">
            <label class="form-label fw-medium">New Password</label>
            <input type="password" name="new_password" class="form-control" autocomplete="new-password" required minlength="8">
            <div class="form-text">At least 8 characters.</div>
          </div>
          <div class="mb-3">
            <label class="form-label fw-medium">Confirm New Password</label>
            <input type="password" name="confirm_password" class="form-control" autocomplete="new-password" required>
          </div>
          <button type="submit" class="btn btn-outline-primary">Change Password</button>
        </form>
      </div>
    </div>

  </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
