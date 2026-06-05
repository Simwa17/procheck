<?php
/**
 * ProCheck Database Installer
 * Visit http://localhost/procheck/setup/install.php to set up the database.
 */

require_once __DIR__ . '/../config.php';

$step = $_GET['step'] ?? 'check';
$errors = [];
$success = '';

function run_install(array &$errors): bool {
    $dsn = 'mysql:host=' . DB_HOST . ';charset=' . DB_CHARSET;
    try {
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    } catch (PDOException $e) {
        $errors[] = 'Cannot connect to MySQL: ' . htmlspecialchars($e->getMessage());
        return false;
    }

    // Create database
    $pdo->exec('CREATE DATABASE IF NOT EXISTS `' . DB_NAME . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    $pdo->exec('USE `' . DB_NAME . '`');

    $sql = <<<SQL
CREATE TABLE IF NOT EXISTS `users` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(100) NOT NULL,
  `email` VARCHAR(150) UNIQUE NOT NULL,
  `password` VARCHAR(255) NOT NULL,
  `role` ENUM('user','admin') DEFAULT 'user',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `project_types` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(100) NOT NULL,
  `description` TEXT,
  `icon` VARCHAR(50) DEFAULT 'bi-grid',
  `base_hours` DECIMAL(6,2) DEFAULT 0,
  `is_active` TINYINT(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `module_categories` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(100) NOT NULL,
  `project_type_id` INT DEFAULT NULL,
  KEY `fk_mc_pt` (`project_type_id`),
  CONSTRAINT `fk_mc_pt` FOREIGN KEY (`project_type_id`) REFERENCES `project_types`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `modules` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `category_id` INT DEFAULT NULL,
  `name` VARCHAR(150) NOT NULL,
  `description` TEXT,
  `simple_hours` DECIMAL(6,2) DEFAULT 4,
  `medium_hours` DECIMAL(6,2) DEFAULT 8,
  `complex_hours` DECIMAL(6,2) DEFAULT 16,
  `is_active` TINYINT(1) DEFAULT 1,
  KEY `fk_mod_cat` (`category_id`),
  CONSTRAINT `fk_mod_cat` FOREIGN KEY (`category_id`) REFERENCES `module_categories`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `developer_rates` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `tier` ENUM('junior','mid','senior') NOT NULL UNIQUE,
  `hourly_rate_mwk` DECIMAL(10,2) NOT NULL,
  `description` VARCHAR(255),
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `clients` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `name` VARCHAR(150) NOT NULL,
  `company` VARCHAR(150),
  `email` VARCHAR(150),
  `phone` VARCHAR(50),
  `address` TEXT,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  KEY `fk_client_user` (`user_id`),
  CONSTRAINT `fk_client_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `quotes` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `client_id` INT DEFAULT NULL,
  `quote_number` VARCHAR(20) UNIQUE NOT NULL,
  `project_name` VARCHAR(200) NOT NULL,
  `project_type_id` INT DEFAULT NULL,
  `developer_tier` ENUM('junior','mid','senior') NOT NULL DEFAULT 'mid',
  `total_hours` DECIMAL(8,2) DEFAULT 0,
  `subtotal_mwk` DECIMAL(14,2) DEFAULT 0,
  `margin_percent` DECIMAL(5,2) DEFAULT 0,
  `total_mwk` DECIMAL(14,2) DEFAULT 0,
  `usd_rate` DECIMAL(10,2) DEFAULT 1800,
  `total_usd` DECIMAL(14,2) DEFAULT 0,
  `notes` TEXT,
  `status` ENUM('draft','sent','accepted','rejected') DEFAULT 'draft',
  `valid_until` DATE DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY `fk_quote_user` (`user_id`),
  KEY `fk_quote_client` (`client_id`),
  KEY `fk_quote_pt` (`project_type_id`),
  CONSTRAINT `fk_quote_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_quote_client` FOREIGN KEY (`client_id`) REFERENCES `clients`(`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_quote_pt` FOREIGN KEY (`project_type_id`) REFERENCES `project_types`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `quote_items` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `quote_id` INT NOT NULL,
  `module_id` INT DEFAULT NULL,
  `module_name` VARCHAR(150) NOT NULL,
  `description` TEXT,
  `complexity` ENUM('simple','medium','complex') DEFAULT 'medium',
  `hours` DECIMAL(6,2) DEFAULT 0,
  `rate_mwk` DECIMAL(10,2) DEFAULT 0,
  `total_mwk` DECIMAL(14,2) DEFAULT 0,
  KEY `fk_qi_quote` (`quote_id`),
  CONSTRAINT `fk_qi_quote` FOREIGN KEY (`quote_id`) REFERENCES `quotes`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `settings` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `setting_key` VARCHAR(100) UNIQUE NOT NULL,
  `setting_value` TEXT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
SQL;

    try {
        foreach (array_filter(array_map('trim', explode(';', $sql))) as $stmt) {
            if ($stmt !== '') $pdo->exec($stmt);
        }
    } catch (PDOException $e) {
        $errors[] = 'Schema error: ' . htmlspecialchars($e->getMessage());
        return false;
    }

    // Seed default data
    $seeds = [
        // Developer rates (MWK per hour — Malawian market rates)
        "INSERT IGNORE INTO `developer_rates` (`tier`,`hourly_rate_mwk`,`description`) VALUES
          ('junior', 3500, '0–2 years experience'),
          ('mid', 8000, '2–5 years experience'),
          ('senior', 18000, '5+ years experience')",

        // Default settings
        "INSERT IGNORE INTO `settings` (`setting_key`,`setting_value`) VALUES
          ('usd_mwk_rate', '1800'),
          ('company_name', 'My Dev Studio'),
          ('company_email', ''),
          ('company_phone', ''),
          ('company_address', 'Lilongwe, Malawi'),
          ('quote_prefix', 'QT'),
          ('quote_footer', 'This quote is valid for 30 days from the date of issue. Prices are in Malawian Kwacha (MWK).')",

        // Project types
        "INSERT IGNORE INTO `project_types` (`id`,`name`,`description`,`icon`,`base_hours`) VALUES
          (1,'Web Application','Custom web-based applications and portals','bi-window',16),
          (2,'E-Commerce Store','Online stores with payment integration','bi-shop',24),
          (3,'Mobile App','Android / iOS mobile applications','bi-phone',32),
          (4,'REST API / Backend','Server-side APIs and microservices','bi-server',12),
          (5,'Static Website','Brochure/marketing websites','bi-globe',8),
          (6,'Desktop Application','Windows/cross-platform desktop apps','bi-pc-display',20),
          (7,'Data Dashboard','Analytics and reporting dashboards','bi-bar-chart',16)",

        // Module categories
        "INSERT IGNORE INTO `module_categories` (`id`,`name`,`project_type_id`) VALUES
          (1,'Authentication & Users',NULL),
          (2,'Content Management',NULL),
          (3,'E-Commerce',2),
          (4,'API & Integrations',NULL),
          (5,'UI / Frontend',NULL),
          (6,'Reporting & Analytics',NULL),
          (7,'Mobile-Specific',3),
          (8,'Admin & Back-office',NULL),
          (9,'Communication',NULL)",

        // Modules
        "INSERT IGNORE INTO `modules` (`id`,`category_id`,`name`,`description`,`simple_hours`,`medium_hours`,`complex_hours`) VALUES
          (1,1,'User Registration & Login','Sign-up, login, logout',4,8,16),
          (2,1,'Social Login','Google / Facebook OAuth',4,8,12),
          (3,1,'Password Reset','Email-based password recovery',2,4,6),
          (4,1,'Role & Permission System','Multi-role access control',8,16,32),
          (5,1,'Two-Factor Authentication','SMS / TOTP 2FA',4,8,16),
          (6,2,'Blog / News Module','Create, edit, publish articles',8,16,24),
          (7,2,'Media Library','Upload and manage files/images',4,8,16),
          (8,2,'CMS Page Builder','Drag-and-drop page builder',16,32,60),
          (9,3,'Product Catalog','Products, categories, variants',8,16,32),
          (10,3,'Shopping Cart & Checkout','Cart, order flow',12,24,40),
          (11,3,'Payment Integration','TNM Mpamba, Airtel Money, Stripe',8,16,24),
          (12,3,'Order Management','Orders, fulfillment, tracking',8,16,24),
          (13,4,'RESTful API Development','CRUD endpoints',8,16,32),
          (14,4,'Third-party API Integration','Integrate external APIs',4,8,16),
          (15,4,'Webhook Support','Send/receive webhooks',4,8,12),
          (16,5,'Responsive UI Design','Mobile-first responsive design',8,16,32),
          (17,5,'Custom Theme / Branding','Brand colors, fonts, styling',4,8,16),
          (18,5,'Multi-language Support','i18n / Chichewa + English',8,16,24),
          (19,6,'Dashboard & Charts','KPI cards, charts, graphs',8,16,32),
          (20,6,'PDF Report Generation','Exportable PDF reports',4,8,16),
          (21,6,'Excel / CSV Export','Data export functionality',2,4,8),
          (22,7,'Push Notifications','Mobile push notifications',4,8,16),
          (23,7,'Offline Mode','Local storage / sync',8,16,32),
          (24,7,'GPS / Location Services','Maps, geolocation',8,16,24),
          (25,8,'Admin Panel','Full CRUD back-office',16,32,48),
          (26,8,'Audit Logs','Track user actions',4,8,12),
          (27,9,'Email Notifications','Transactional emails',4,8,12),
          (28,9,'SMS Notifications','SMS via local gateway',4,8,12),
          (29,9,'In-app Messaging','Chat / notifications',8,16,32),
          (30,NULL,'Custom Feature','A bespoke feature not listed above',8,16,40)"
    ];

    foreach ($seeds as $s) {
        try { $pdo->exec($s); } catch (PDOException $e) { /* ignore duplicates */ }
    }

    // Admin user
    $admin_pass = isset($_POST['admin_pass']) ? $_POST['admin_pass'] : 'admin123';
    $admin_email = isset($_POST['admin_email']) ? $_POST['admin_email'] : 'admin@procheck.mw';
    $admin_name  = isset($_POST['admin_name'])  ? $_POST['admin_name']  : 'Administrator';

    $hash = password_hash($admin_pass, PASSWORD_BCRYPT);
    $stmt = $pdo->prepare("INSERT IGNORE INTO `users` (`name`,`email`,`password`,`role`) VALUES (?,?,?,'admin')");
    $stmt->execute([$admin_name, $admin_email, $hash]);

    return true;
}

$installed = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (empty($_POST['admin_email']) || empty($_POST['admin_pass'])) {
        $errors[] = 'Admin email and password are required.';
    } elseif (strlen($_POST['admin_pass']) < 8) {
        $errors[] = 'Admin password must be at least 8 characters.';
    } else {
        $installed = run_install($errors);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>ProCheck — Install</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<style>
body{background:#f0f4f8;display:flex;align-items:center;justify-content:center;min-height:100vh}
.install-card{max-width:520px;width:100%}
.logo-circle{width:64px;height:64px;border-radius:50%;background:linear-gradient(135deg,#2563eb,#7c3aed);display:flex;align-items:center;justify-content:center;margin:0 auto 1rem}
</style>
</head>
<body>
<div class="install-card mx-3">
  <div class="card shadow-sm border-0">
    <div class="card-body p-4">
      <div class="text-center mb-4">
        <div class="logo-circle"><i class="bi bi-check2-circle text-white fs-2"></i></div>
        <h3 class="fw-bold mb-0">ProCheck</h3>
        <p class="text-muted small">Project Pricing System — Installation</p>
      </div>

      <?php if ($installed): ?>
        <div class="alert alert-success">
          <i class="bi bi-check-circle-fill me-2"></i>
          <strong>Installation complete!</strong> Your database and default data have been set up.
        </div>
        <p class="text-muted small">Default modules, developer rates, and project types have been seeded.</p>
        <a href="../index.php" class="btn btn-primary w-100">Go to ProCheck &rarr;</a>
        <p class="text-danger small mt-3"><i class="bi bi-exclamation-triangle-fill me-1"></i>Delete or restrict access to the <code>setup/</code> folder after installation.</p>
      <?php else: ?>
        <?php foreach ($errors as $e): ?>
          <div class="alert alert-danger py-2"><i class="bi bi-x-circle me-1"></i><?= $e ?></div>
        <?php endforeach; ?>
        <form method="POST">
          <h6 class="text-muted text-uppercase small fw-semibold mb-3">Database Connection</h6>
          <div class="row g-2 mb-3">
            <div class="col-8">
              <label class="form-label small">MySQL Host</label>
              <input type="text" class="form-control" value="<?= DB_HOST ?>" disabled>
            </div>
            <div class="col-4">
              <label class="form-label small">Database</label>
              <input type="text" class="form-control" value="<?= DB_NAME ?>" disabled>
            </div>
          </div>
          <p class="text-muted small">Edit <code>config.php</code> to change connection settings.</p>
          <hr>
          <h6 class="text-muted text-uppercase small fw-semibold mb-3">Admin Account</h6>
          <div class="mb-3">
            <label class="form-label">Full Name</label>
            <input type="text" name="admin_name" class="form-control" value="<?= htmlspecialchars($_POST['admin_name'] ?? 'Administrator') ?>" required>
          </div>
          <div class="mb-3">
            <label class="form-label">Email Address</label>
            <input type="email" name="admin_email" class="form-control" value="<?= htmlspecialchars($_POST['admin_email'] ?? '') ?>" required>
          </div>
          <div class="mb-4">
            <label class="form-label">Password <span class="text-muted small">(min 8 chars)</span></label>
            <input type="password" name="admin_pass" class="form-control" minlength="8" required>
          </div>
          <button type="submit" class="btn btn-primary w-100 py-2">
            <i class="bi bi-database-fill-gear me-2"></i>Install ProCheck
          </button>
        </form>
      <?php endif; ?>
    </div>
  </div>
  <p class="text-center text-muted small mt-3">ProCheck v<?= APP_VERSION ?></p>
</div>
</body>
</html>
