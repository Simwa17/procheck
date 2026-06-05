<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

$user   = require_login();
$id     = (int)($_GET['id'] ?? 0);
$errors = [];

$stmt = db()->prepare('SELECT * FROM clients WHERE id = ? AND user_id = ?');
$stmt->execute([$id, $user['id']]);
$client = $stmt->fetch();

if (!$client) {
    flash_set('error', 'Client not found.');
    header('Location: ' . APP_URL . '/clients/index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $name    = trim($_POST['name'] ?? '');
    $company = trim($_POST['company'] ?? '');
    $email   = trim($_POST['email'] ?? '');
    $phone   = trim($_POST['phone'] ?? '');
    $address = trim($_POST['address'] ?? '');

    if (!$name) $errors[] = 'Client name is required.';
    if ($email && !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Invalid email.';

    if (!$errors) {
        $upd = db()->prepare('UPDATE clients SET name=?,company=?,email=?,phone=?,address=? WHERE id=? AND user_id=?');
        $upd->execute([$name, $company ?: null, $email ?: null, $phone ?: null, $address ?: null, $id, $user['id']]);
        flash_set('success', 'Client updated.');
        header('Location: ' . APP_URL . '/clients/index.php');
        exit;
    }
    $client = array_merge($client, $_POST);
}

$page_title = 'Edit Client';
include __DIR__ . '/../includes/header.php';
?>
<div class="page-header mb-4">
  <h4 class="fw-bold mb-0"><i class="bi bi-pencil me-2 text-primary"></i>Edit Client</h4>
</div>
<div class="row justify-content-center">
  <div class="col-md-7">
    <div class="card border-0 shadow-sm">
      <div class="card-body p-4">
        <?php foreach ($errors as $e): ?>
          <div class="alert alert-danger py-2"><?= h($e) ?></div>
        <?php endforeach; ?>
        <form method="POST">
          <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
          <div class="mb-3">
            <label class="form-label fw-medium">Full Name <span class="text-danger">*</span></label>
            <input type="text" name="name" class="form-control" value="<?= h($client['name']) ?>" required>
          </div>
          <div class="mb-3">
            <label class="form-label fw-medium">Company / Organisation</label>
            <input type="text" name="company" class="form-control" value="<?= h($client['company'] ?? '') ?>">
          </div>
          <div class="row g-3 mb-3">
            <div class="col-md-6">
              <label class="form-label fw-medium">Email</label>
              <input type="email" name="email" class="form-control" value="<?= h($client['email'] ?? '') ?>">
            </div>
            <div class="col-md-6">
              <label class="form-label fw-medium">Phone</label>
              <input type="tel" name="phone" class="form-control" value="<?= h($client['phone'] ?? '') ?>">
            </div>
          </div>
          <div class="mb-4">
            <label class="form-label fw-medium">Address</label>
            <textarea name="address" class="form-control" rows="3"><?= h($client['address'] ?? '') ?></textarea>
          </div>
          <div class="d-flex gap-2">
            <button type="submit" class="btn btn-primary"><i class="bi bi-save me-1"></i>Update Client</button>
            <a href="<?= APP_URL ?>/clients/index.php" class="btn btn-outline-secondary">Cancel</a>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
