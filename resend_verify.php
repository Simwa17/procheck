<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/mailer.php';

$user = require_login();

// Already verified — redirect silently
if (($user['email_verified'] ?? 1) == 1) {
    header('Location: ' . APP_URL . '/dashboard.php');
    exit;
}

$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $token   = bin2hex(random_bytes(32));
    $expires = date('Y-m-d H:i:s', time() + 48 * 3600);
    $pdo->prepare('UPDATE users SET email_token=?, email_token_expires=? WHERE id=?')
        ->execute([$token, $expires, $user['id']]);

    $link = APP_URL . '/verify.php?token=' . urlencode($token);
    $sent = Mailer::send(
        $user['email'],
        $user['name'],
        'Verify your ProCheck account',
        Mailer::verificationBody($user['name'], $link)
    );

    flash_set('success', $sent
        ? 'Verification email resent — please check your inbox.'
        : 'Could not send email. Please ask an admin to configure SMTP settings.');
    header('Location: ' . APP_URL . '/dashboard.php');
    exit;
}

$page_title = 'Resend Verification';
include __DIR__ . '/includes/header.php';
?>

<div class="row justify-content-center mt-5">
  <div class="col-md-5">
    <div class="card border-0 shadow-sm">
      <div class="card-body p-4 text-center">
        <i class="bi bi-envelope-at display-4 text-warning mb-3 d-block"></i>
        <h5 class="fw-bold mb-2">Resend Verification Email</h5>
        <p class="text-muted mb-4">We'll send a fresh verification link to <strong><?= h($user['email']) ?></strong>.</p>
        <form method="POST">
          <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
          <button type="submit" class="btn btn-warning px-5">
            <i class="bi bi-send me-2"></i>Resend Email
          </button>
        </form>
        <a href="<?= APP_URL ?>/dashboard.php" class="btn btn-link btn-sm mt-3">Skip for now</a>
      </div>
    </div>
  </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
