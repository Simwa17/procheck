<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

$user = require_login();
$id   = (int)($_GET['id'] ?? 0);

$stmt = db()->prepare('DELETE FROM clients WHERE id = ? AND user_id = ?');
$stmt->execute([$id, $user['id']]);

flash_set($stmt->rowCount() ? 'success' : 'error',
          $stmt->rowCount() ? 'Client deleted.' : 'Client not found.');
header('Location: ' . APP_URL . '/clients/index.php');
exit;
