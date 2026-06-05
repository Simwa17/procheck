<?php
/**
 * ProCheck — Email Feature Migration
 * Visit http://localhost/procheck/setup/migrate_email.php once to add email columns and settings.
 */

require_once __DIR__ . '/../config.php';

$dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
$pdo = new PDO($dsn, DB_USER, DB_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

$steps = [];

// 1. Add email verification columns to users
$cols = $pdo->query('SHOW COLUMNS FROM `users`')->fetchAll(PDO::FETCH_COLUMN);
foreach ([
    'email_verified'      => 'ALTER TABLE `users` ADD COLUMN `email_verified` TINYINT(1) NOT NULL DEFAULT 0 AFTER `role`',
    'email_token'         => 'ALTER TABLE `users` ADD COLUMN `email_token` VARCHAR(64) DEFAULT NULL AFTER `email_verified`',
    'email_token_expires' => 'ALTER TABLE `users` ADD COLUMN `email_token_expires` DATETIME DEFAULT NULL AFTER `email_token`',
] as $col => $sql) {
    if (!in_array($col, $cols)) {
        $pdo->exec($sql);
        $steps[] = "Added column <code>users.{$col}</code>.";
    } else {
        $steps[] = "Column <code>users.{$col}</code> already exists — skipped.";
    }
}

// 2. Mark existing users as verified (they registered before email was introduced)
$pdo->exec("UPDATE `users` SET `email_verified` = 1 WHERE `email_verified` = 0");
$steps[] = "Marked all existing users as email-verified.";

// 3. Seed new settings keys
$newSettings = [
    'mail_from_name'   => setting_raw($pdo, 'company_name') ?: 'ProCheck',
    'mail_from_email'  => '',
    'smtp_host'        => '',
    'smtp_port'        => '587',
    'smtp_user'        => '',
    'smtp_pass'        => '',
    'smtp_encryption'  => 'tls',
];

$ins = $pdo->prepare("INSERT IGNORE INTO `settings` (`setting_key`, `setting_value`) VALUES (?, ?)");
foreach ($newSettings as $key => $val) {
    $ins->execute([$key, $val]);
    $steps[] = "Setting <code>{$key}</code> inserted (if not present).";
}

function setting_raw(PDO $pdo, string $key): string {
    $r = $pdo->prepare('SELECT setting_value FROM settings WHERE setting_key = ?');
    $r->execute([$key]);
    return (string)($r->fetchColumn() ?: '');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>ProCheck — Email Migration</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
</head>
<body class="bg-light">
<div class="container py-5" style="max-width:600px">
  <h4 class="fw-bold mb-4">ProCheck — Email Migration</h4>
  <div class="card border-0 shadow-sm">
    <div class="card-body">
      <?php foreach ($steps as $s): ?>
        <div class="d-flex gap-2 mb-2">
          <span class="text-success">✓</span>
          <span class="small"><?= $s ?></span>
        </div>
      <?php endforeach; ?>
      <hr>
      <div class="alert alert-success mb-0">
        Migration complete! <a href="../admin/settings.php">Configure SMTP settings</a> to enable email sending.
      </div>
    </div>
  </div>
  <p class="text-danger small mt-3">Delete or restrict access to the <code>setup/</code> folder in production.</p>
</div>
</body>
</html>
