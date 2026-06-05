<?php
/**
 * ProCheck — Migration v2
 * Adds: discount, tax, public token, custom items, invoices, payments,
 *       milestones, revision history tables and settings.
 * Visit once: http://localhost/procheck/setup/migrate_v2.php
 */
require_once __DIR__ . '/../config.php';

try {
    $pdo = new PDO(
        'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET,
        DB_USER, DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
} catch (PDOException $e) {
    die('Cannot connect: ' . htmlspecialchars($e->getMessage()));
}

$results = [];

function safe_exec(PDO $pdo, string $sql, string $label, array &$results): void {
    try {
        $pdo->exec($sql);
        $results[] = ['ok', $label];
    } catch (PDOException $e) {
        // 1060 = duplicate column, 1061 = duplicate key name, 1050 = table exists
        if (in_array((int)$e->errorInfo[1], [1060, 1061, 1050])) {
            $results[] = ['skip', "$label — already exists, skipped"];
        } else {
            $results[] = ['fail', "$label — " . $e->getMessage()];
        }
    }
}

// ── Quotes table ──────────────────────────────────────────────────────────────
$alters = [
    "ALTER TABLE `quotes` ADD COLUMN `discount_type` ENUM('percent','fixed') DEFAULT 'percent' AFTER `margin_percent`",
    "ALTER TABLE `quotes` ADD COLUMN `discount_value` DECIMAL(10,2) DEFAULT 0 AFTER `discount_type`",
    "ALTER TABLE `quotes` ADD COLUMN `tax_rate` DECIMAL(5,2) DEFAULT 0 AFTER `discount_value`",
    "ALTER TABLE `quotes` ADD COLUMN `public_token` VARCHAR(64) UNIQUE DEFAULT NULL",
    "ALTER TABLE `quotes` ADD COLUMN `parent_quote_id` INT DEFAULT NULL",
    "ALTER TABLE `quotes` ADD COLUMN `revision_number` TINYINT DEFAULT 1",
];
foreach ($alters as $sql) {
    preg_match('/COLUMN `(\w+)`/', $sql, $m);
    safe_exec($pdo, $sql, 'quotes: add ' . ($m[1] ?? '?'), $results);
}

// ── Quote items table ─────────────────────────────────────────────────────────
safe_exec(
    $pdo,
    "ALTER TABLE `quote_items` ADD COLUMN `item_type` ENUM('module','custom') DEFAULT 'module' AFTER `quote_id`",
    'quote_items: add item_type',
    $results
);

// ── New tables ────────────────────────────────────────────────────────────────
$tables = [
    'invoices' => "CREATE TABLE IF NOT EXISTS `invoices` (
        `id`              INT AUTO_INCREMENT PRIMARY KEY,
        `quote_id`        INT NOT NULL,
        `user_id`         INT NOT NULL,
        `invoice_number`  VARCHAR(30) UNIQUE NOT NULL,
        `status`          ENUM('unpaid','partial','paid') DEFAULT 'unpaid',
        `amount_mwk`      DECIMAL(14,2) NOT NULL,
        `amount_paid_mwk` DECIMAL(14,2) DEFAULT 0,
        `due_date`        DATE DEFAULT NULL,
        `issued_at`       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `notes`           TEXT,
        KEY `fk_inv_quote` (`quote_id`),
        KEY `fk_inv_user`  (`user_id`),
        CONSTRAINT `fk_inv_quote` FOREIGN KEY (`quote_id`) REFERENCES `quotes`(`id`) ON DELETE CASCADE,
        CONSTRAINT `fk_inv_user`  FOREIGN KEY (`user_id`)  REFERENCES `users`(`id`)  ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    'payments' => "CREATE TABLE IF NOT EXISTS `payments` (
        `id`           INT AUTO_INCREMENT PRIMARY KEY,
        `invoice_id`   INT NOT NULL,
        `amount_mwk`   DECIMAL(14,2) NOT NULL,
        `payment_date` DATE NOT NULL,
        `method`       VARCHAR(50) DEFAULT 'Cash',
        `reference`    VARCHAR(100),
        `notes`        TEXT,
        `recorded_at`  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        KEY `fk_pay_inv` (`invoice_id`),
        CONSTRAINT `fk_pay_inv` FOREIGN KEY (`invoice_id`) REFERENCES `invoices`(`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    'quote_milestones' => "CREATE TABLE IF NOT EXISTS `quote_milestones` (
        `id`          INT AUTO_INCREMENT PRIMARY KEY,
        `quote_id`    INT NOT NULL,
        `title`       VARCHAR(200) NOT NULL,
        `description` TEXT,
        `percent`     DECIMAL(5,2) DEFAULT 0,
        `amount_mwk`  DECIMAL(14,2) DEFAULT 0,
        `due_date`    DATE DEFAULT NULL,
        `status`      ENUM('pending','invoiced','paid') DEFAULT 'pending',
        `sort_order`  TINYINT DEFAULT 0,
        KEY `fk_ms_quote` (`quote_id`),
        CONSTRAINT `fk_ms_quote` FOREIGN KEY (`quote_id`) REFERENCES `quotes`(`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    'quote_revisions' => "CREATE TABLE IF NOT EXISTS `quote_revisions` (
        `id`              INT AUTO_INCREMENT PRIMARY KEY,
        `quote_id`        INT NOT NULL,
        `revision_number` TINYINT NOT NULL,
        `changed_by`      INT NOT NULL,
        `change_note`     VARCHAR(255),
        `snapshot`        JSON,
        `changed_at`      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        KEY `fk_rev_quote` (`quote_id`),
        CONSTRAINT `fk_rev_quote` FOREIGN KEY (`quote_id`) REFERENCES `quotes`(`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
];
foreach ($tables as $name => $sql) {
    safe_exec($pdo, $sql, "Create table: $name", $results);
}

// ── Settings ──────────────────────────────────────────────────────────────────
$settings = [
    ['quote_validity_days', '30'],
    ['default_tax_rate',    '0'],
    ['invoice_prefix',      'INV'],
    // Mail settings (in case migrate_email was skipped)
    ['mail_from_name',   ''],
    ['mail_from_email',  ''],
    ['smtp_host',        ''],
    ['smtp_port',        '587'],
    ['smtp_user',        ''],
    ['smtp_pass',        ''],
    ['smtp_encryption',  'tls'],
];
foreach ($settings as [$key, $val]) {
    safe_exec(
        $pdo,
        "INSERT IGNORE INTO `settings` (`setting_key`, `setting_value`) VALUES (" . $pdo->quote($key) . ", " . $pdo->quote($val) . ")",
        "Setting: $key",
        $results
    );
}

// ── Email columns on users (in case migrate_email was skipped) ────────────────
$email_alters = [
    "ALTER TABLE `users` ADD COLUMN `email_verified`      TINYINT(1) DEFAULT 1",
    "ALTER TABLE `users` ADD COLUMN `email_token`         VARCHAR(64)  DEFAULT NULL",
    "ALTER TABLE `users` ADD COLUMN `email_token_expires` DATETIME     DEFAULT NULL",
];
foreach ($email_alters as $sql) {
    preg_match('/COLUMN `(\w+)`/', $sql, $m);
    safe_exec($pdo, $sql, 'users: add ' . ($m[1] ?? '?'), $results);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>ProCheck — Migration v2</title>
<style>
  body { font-family: sans-serif; max-width: 700px; margin: 40px auto; padding: 0 20px; }
  h2 { color: #2563eb; }
  .ok   { color: #16a34a; } .skip { color: #b45309; } .fail { color: #dc2626; }
  li   { margin: 4px 0; font-size: 14px; }
  a.btn { display:inline-block; margin-top:20px; padding:10px 24px; background:#2563eb; color:#fff; border-radius:6px; text-decoration:none; }
</style>
</head>
<body>
<h2>ProCheck — Migration v2</h2>
<ul>
<?php foreach ($results as [$status, $msg]): ?>
  <li class="<?= $status ?>">
    <?= $status === 'ok' ? '✅' : ($status === 'skip' ? '⏭' : '❌') ?>
    <?= htmlspecialchars($msg) ?>
  </li>
<?php endforeach; ?>
</ul>
<?php $failed = array_filter($results, fn($r) => $r[0] === 'fail'); ?>
<?php if (empty($failed)): ?>
<p style="color:#16a34a;font-weight:bold">Migration complete.</p>
<a class="btn" href="<?= APP_URL ?>/dashboard.php">Go to Dashboard</a>
<?php else: ?>
<p style="color:#dc2626;font-weight:bold">Some steps failed. Check the errors above and fix before continuing.</p>
<?php endif; ?>
</body>
</html>
