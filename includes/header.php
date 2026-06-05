<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= isset($page_title) ? h($page_title) . ' — ' : '' ?>ProCheck</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<link rel="stylesheet" href="<?= APP_URL ?>/assets/css/style.css">
<?= $extra_head ?? '' ?>
</head>
<body>
<?php
$_user = auth_user();
$_flash_success = flash_get('success');
$_flash_error   = flash_get('error');
?>
<nav class="navbar navbar-expand-lg navbar-dark bg-primary-dark shadow-sm">
  <div class="container-fluid">
    <a class="navbar-brand fw-bold" href="<?= APP_URL ?>/dashboard.php">
      <i class="bi bi-check2-circle me-2"></i>ProCheck
    </a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navMain">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="navMain">
      <?php if ($_user): ?>
      <ul class="navbar-nav me-auto">
        <li class="nav-item">
          <a class="nav-link <?= str_contains($_SERVER['REQUEST_URI'], 'dashboard') ? 'active' : '' ?>"
             href="<?= APP_URL ?>/dashboard.php"><i class="bi bi-speedometer2 me-1"></i>Dashboard</a>
        </li>
        <li class="nav-item">
          <a class="nav-link <?= str_contains($_SERVER['REQUEST_URI'], 'quotes') ? 'active' : '' ?>"
             href="<?= APP_URL ?>/quotes/index.php"><i class="bi bi-file-earmark-text me-1"></i>Quotes</a>
        </li>
        <li class="nav-item">
          <a class="nav-link <?= str_contains($_SERVER['REQUEST_URI'], 'clients') ? 'active' : '' ?>"
             href="<?= APP_URL ?>/clients/index.php"><i class="bi bi-people me-1"></i>Clients</a>
        </li>
        <?php if ($_user['role'] === 'admin'): ?>
        <li class="nav-item dropdown">
          <a class="nav-link dropdown-toggle <?= str_contains($_SERVER['REQUEST_URI'], 'admin') ? 'active' : '' ?>"
             href="#" data-bs-toggle="dropdown"><i class="bi bi-shield-lock me-1"></i>Admin</a>
          <ul class="dropdown-menu">
            <li><a class="dropdown-item" href="<?= APP_URL ?>/admin/index.php"><i class="bi bi-speedometer2 me-2"></i>Admin Dashboard</a></li>
            <li><hr class="dropdown-divider"></li>
            <li><a class="dropdown-item" href="<?= APP_URL ?>/admin/project_types.php"><i class="bi bi-grid me-2"></i>Project Types</a></li>
            <li><a class="dropdown-item" href="<?= APP_URL ?>/admin/modules.php"><i class="bi bi-puzzle me-2"></i>Modules</a></li>
            <li><a class="dropdown-item" href="<?= APP_URL ?>/admin/rates.php"><i class="bi bi-cash-coin me-2"></i>Developer Rates</a></li>
            <li><a class="dropdown-item" href="<?= APP_URL ?>/admin/users.php"><i class="bi bi-people me-2"></i>Users</a></li>
            <li><hr class="dropdown-divider"></li>
            <li><a class="dropdown-item" href="<?= APP_URL ?>/admin/settings.php"><i class="bi bi-gear me-2"></i>Settings</a></li>
          </ul>
        </li>
        <?php endif; ?>
      </ul>
      <ul class="navbar-nav ms-auto align-items-center gap-2">
        <li class="nav-item">
          <a href="<?= APP_URL ?>/quotes/create.php" class="btn btn-sm btn-success">
            <i class="bi bi-plus-lg me-1"></i>New Quote
          </a>
        </li>
        <li class="nav-item dropdown">
          <a class="nav-link dropdown-toggle d-flex align-items-center" href="#" data-bs-toggle="dropdown">
            <div class="avatar-circle me-2"><?= strtoupper(substr($_user['name'], 0, 1)) ?></div>
            <?= h($_user['name']) ?>
          </a>
          <ul class="dropdown-menu dropdown-menu-end">
            <li><h6 class="dropdown-header"><?= h($_user['email']) ?></h6></li>
            <li><hr class="dropdown-divider"></li>
            <li><a class="dropdown-item text-danger" href="<?= APP_URL ?>/logout.php"><i class="bi bi-box-arrow-right me-2"></i>Logout</a></li>
          </ul>
        </li>
      </ul>
      <?php else: ?>
      <ul class="navbar-nav ms-auto">
        <li class="nav-item"><a class="nav-link" href="<?= APP_URL ?>/login.php">Login</a></li>
      </ul>
      <?php endif; ?>
    </div>
  </div>
</nav>

<div class="container-fluid px-4 py-3">
<?php if ($_flash_success): ?>
<div class="alert alert-success alert-dismissible fade show" role="alert">
  <i class="bi bi-check-circle-fill me-2"></i><?= h($_flash_success) ?>
  <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>
<?php if ($_flash_error): ?>
<div class="alert alert-danger alert-dismissible fade show" role="alert">
  <i class="bi bi-x-circle-fill me-2"></i><?= h($_flash_error) ?>
  <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>
<?php
$_au = auth_user();
if ($_au && isset($_au['email_verified']) && $_au['email_verified'] == 0):
?>
<div class="alert alert-warning alert-dismissible fade show" role="alert">
  <i class="bi bi-envelope-exclamation-fill me-2"></i>
  <strong>Please verify your email address.</strong>
  Check your inbox for a verification link, or
  <a href="<?= APP_URL ?>/resend_verify.php" class="alert-link">resend the verification email</a>.
  <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>
