<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

session_start_safe();

$token   = trim($_GET['token'] ?? '');
$success = false;
$error   = '';

if (!$token) {
    $error = 'Invalid or missing verification link.';
} else {
    $stmt = db()->prepare(
        'SELECT id, name, email, email_verified, email_token_expires
         FROM users WHERE email_token = ? LIMIT 1'
    );
    $stmt->execute([$token]);
    $user = $stmt->fetch();

    if (!$user) {
        $error = 'This verification link is invalid or has already been used.';
    } elseif ((int)$user['email_verified'] === 1) {
        $error = 'Your email is already verified. <a href="' . APP_URL . '/login.php">Sign in</a>';
    } elseif ($user['email_token_expires'] && strtotime($user['email_token_expires']) < time()) {
        $error = 'This verification link has expired. Please register again or contact support.';
    } else {
        // Mark verified, clear token
        db()->prepare(
            'UPDATE users SET email_verified = 1, email_token = NULL, email_token_expires = NULL WHERE id = ?'
        )->execute([$user['id']]);
        $success = true;

        // Log the user in automatically
        login_user($user['email'], ''); // can't re-use password here; use direct session
        session_start_safe();
        session_regenerate_id(true);
        $_SESSION['user'] = [
            'id'             => $user['id'],
            'name'           => $user['name'],
            'email'          => $user['email'],
            'role'           => 'user',
            'email_verified' => 1,
        ];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Verify Email — ProCheck</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<link rel="stylesheet" href="<?= APP_URL ?>/assets/css/style.css">
<?php if ($success): ?>
<meta http-equiv="refresh" content="3;url=<?= APP_URL ?>/dashboard.php">
<?php endif; ?>
</head>
<body class="auth-page">
<div class="auth-wrapper">
  <div class="auth-card card shadow border-0">
    <div class="card-body p-4 p-md-5 text-center">
      <div class="logo-circle mx-auto mb-3">
        <i class="bi bi-<?= $success ? 'check2-circle' : 'x-circle' ?> text-white fs-2"></i>
      </div>
      <?php if ($success): ?>
        <h3 class="fw-bold mb-2 text-success">Email Verified!</h3>
        <p class="text-muted mb-4">
          Welcome to ProCheck, <strong><?= h($user['name']) ?></strong>!<br>
          Your account is now active.
        </p>
        <div class="spinner-border spinner-border-sm text-primary me-2" role="status"></div>
        <span class="text-muted small">Redirecting to your dashboard…</span>
        <div class="mt-4">
          <a href="<?= APP_URL ?>/dashboard.php" class="btn btn-primary">
            <i class="bi bi-speedometer2 me-2"></i>Go to Dashboard
          </a>
        </div>
      <?php else: ?>
        <h3 class="fw-bold mb-2 text-danger">Verification Failed</h3>
        <div class="alert alert-danger text-start">
          <i class="bi bi-x-circle me-2"></i><?= $error ?>
        </div>
        <a href="<?= APP_URL ?>/register.php" class="btn btn-primary">
          <i class="bi bi-person-plus me-2"></i>Back to Register
        </a>
      <?php endif; ?>
    </div>
  </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
