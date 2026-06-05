<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/mailer.php';

session_start_safe();
if (auth_user()) {
    header('Location: ' . APP_URL . '/dashboard.php');
    exit;
}

$errors      = [];
$registered  = false;
$mailSent    = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name     = trim($_POST['name'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm  = $_POST['confirm'] ?? '';

    if (!$name)                                      $errors[] = 'Full name is required.';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL))  $errors[] = 'Valid email is required.';
    if (strlen($password) < 8)                       $errors[] = 'Password must be at least 8 characters.';
    if ($password !== $confirm)                      $errors[] = 'Passwords do not match.';

    if (!$errors) {
        $check = db()->prepare('SELECT id FROM users WHERE email = ?');
        $check->execute([$email]);
        if ($check->fetch()) {
            $errors[] = 'An account with that email already exists.';
        } else {
            $hash  = password_hash($password, PASSWORD_BCRYPT);
            $token = bin2hex(random_bytes(32));
            $expires = date('Y-m-d H:i:s', strtotime('+48 hours'));

            // Try to insert with email columns; fallback if columns don't exist yet
            try {
                $ins = db()->prepare(
                    'INSERT INTO users (name, email, password, email_token, email_token_expires, email_verified)
                     VALUES (?, ?, ?, ?, ?, 0)'
                );
                $ins->execute([$name, $email, $hash, $token, $expires]);
            } catch (\PDOException $e) {
                // email columns not yet migrated — insert without them
                $ins = db()->prepare('INSERT INTO users (name, email, password) VALUES (?, ?, ?)');
                $ins->execute([$name, $email, $hash]);
                $token = null;
            }

            // Send verification email if mailer is configured
            if ($token && setting('mail_from_email')) {
                $link = APP_URL . '/verify.php?token=' . urlencode($token);
                $mailSent = Mailer::send(
                    $email, $name,
                    'Verify your ProCheck account',
                    Mailer::verificationBody($name, $link)
                );
            }

            $registered = true;

            // If mail is not configured, log straight in
            if (!$mailSent) {
                // Mark as verified since we can't send email
                try {
                    db()->prepare('UPDATE users SET email_verified = 1 WHERE email = ?')->execute([$email]);
                } catch (\PDOException $e) { /* columns not yet added */ }
                login_user($email, $password);
                header('Location: ' . APP_URL . '/dashboard.php');
                exit;
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Register — ProCheck</title>
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
        <?php if ($registered && $mailSent): ?>
          <h2 class="fw-bold">Check Your Email</h2>
          <p class="text-muted">Account created successfully!</p>
        <?php else: ?>
          <h2 class="fw-bold">Create Account</h2>
          <p class="text-muted">Join ProCheck &mdash; Start pricing projects</p>
        <?php endif; ?>
      </div>

      <?php if ($registered && $mailSent): ?>
        <div class="alert alert-success text-center">
          <i class="bi bi-envelope-check-fill fs-2 d-block mb-2"></i>
          <strong>Verification email sent to:</strong><br>
          <span class="text-primary"><?= h($email) ?></span>
        </div>
        <p class="text-muted small text-center mb-3">
          Click the link in the email to activate your account. The link expires in <strong>48 hours</strong>.
        </p>
        <a href="<?= APP_URL ?>/login.php" class="btn btn-primary w-100">
          <i class="bi bi-box-arrow-in-right me-2"></i>Go to Login
        </a>
        <p class="text-center text-muted small mt-3 mb-0">
          Didn't receive it? Check your spam folder or
          <a href="<?= APP_URL ?>/register.php?resend=<?= urlencode($email) ?>">resend</a>.
        </p>
      <?php else: ?>
        <?php foreach ($errors as $e): ?>
          <div class="alert alert-danger py-2"><i class="bi bi-x-circle me-1"></i><?= h($e) ?></div>
        <?php endforeach; ?>
        <form method="POST" novalidate>
          <div class="mb-3">
            <label class="form-label fw-medium">Full Name</label>
            <div class="input-group">
              <span class="input-group-text"><i class="bi bi-person"></i></span>
              <input type="text" name="name" class="form-control" value="<?= h($_POST['name'] ?? '') ?>"
                     placeholder="Chisomo Banda" required autofocus>
            </div>
          </div>
          <div class="mb-3">
            <label class="form-label fw-medium">Email Address</label>
            <div class="input-group">
              <span class="input-group-text"><i class="bi bi-envelope"></i></span>
              <input type="email" name="email" class="form-control" value="<?= h($_POST['email'] ?? '') ?>"
                     placeholder="you@example.com" required>
            </div>
          </div>
          <div class="mb-3">
            <label class="form-label fw-medium">Password <span class="text-muted small">(min 8 chars)</span></label>
            <div class="input-group">
              <span class="input-group-text"><i class="bi bi-lock"></i></span>
              <input type="password" name="password" class="form-control" minlength="8" placeholder="••••••••" required>
            </div>
          </div>
          <div class="mb-4">
            <label class="form-label fw-medium">Confirm Password</label>
            <div class="input-group">
              <span class="input-group-text"><i class="bi bi-lock-fill"></i></span>
              <input type="password" name="confirm" class="form-control" placeholder="••••••••" required>
            </div>
          </div>
          <button type="submit" class="btn btn-primary w-100 py-2 fw-semibold">
            <i class="bi bi-person-plus me-2"></i>Create Account
          </button>
        </form>
        <hr class="my-4">
        <p class="text-center text-muted small mb-0">
          Already have an account? <a href="<?= APP_URL ?>/login.php">Sign in</a>
        </p>
      <?php endif; ?>
    </div>
  </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
