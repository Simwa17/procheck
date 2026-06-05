<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

session_start_safe();
if (auth_user()) {
    header('Location: ' . APP_URL . '/dashboard.php');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    if (!$email || !$password) {
        $error = 'Please enter your email and password.';
    } elseif (!login_user($email, $password)) {
        $error = 'Invalid email or password.';
    } else {
        header('Location: ' . APP_URL . '/dashboard.php');
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Login — ProCheck</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<link rel="stylesheet" href="<?= APP_URL ?>/assets/css/style.css">
</head>
<body class="auth-page">
<div class="auth-wrapper">
  <div class="auth-card card shadow border-0">
    <div class="card-body p-4 p-md-5">
      <div class="text-center mb-4">
        <div class="logo-circle mx-auto mb-3"><i class="bi bi-check2-circle text-white fs-2"></i></div>
        <h2 class="fw-bold">ProCheck</h2>
        <p class="text-muted">Project Pricing for Malawian Developers</p>
      </div>
      <?php if ($error): ?>
        <div class="alert alert-danger py-2"><i class="bi bi-x-circle me-1"></i><?= h($error) ?></div>
      <?php endif; ?>
      <form method="POST" novalidate>
        <div class="mb-3">
          <label class="form-label fw-medium">Email Address</label>
          <div class="input-group">
            <span class="input-group-text"><i class="bi bi-envelope"></i></span>
            <input type="email" name="email" class="form-control" value="<?= h($_POST['email'] ?? '') ?>"
                   placeholder="you@example.com" required autofocus>
          </div>
        </div>
        <div class="mb-4">
          <label class="form-label fw-medium">Password</label>
          <div class="input-group">
            <span class="input-group-text"><i class="bi bi-lock"></i></span>
            <input type="password" name="password" class="form-control" placeholder="••••••••" required>
          </div>
        </div>
        <button type="submit" class="btn btn-primary w-100 py-2 fw-semibold">
          <i class="bi bi-box-arrow-in-right me-2"></i>Sign In
        </button>
      </form>
      <hr class="my-4">
      <p class="text-center text-muted small mb-0">
        Don't have an account? <a href="<?= APP_URL ?>/register.php">Create one</a>
      </p>
    </div>
  </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
