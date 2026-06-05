<?php
// includes/auth.php — Authentication helpers

require_once __DIR__ . '/db.php';

function session_start_safe(): void {
    if (session_status() === PHP_SESSION_NONE) {
        session_set_cookie_params([
            'lifetime' => SESSION_LIFETIME,
            'path'     => '/',
            'secure'   => false,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();
    }
}

function auth_user(): ?array {
    session_start_safe();
    return $_SESSION['user'] ?? null;
}

function require_login(): array {
    $user = auth_user();
    if (!$user) {
        header('Location: ' . APP_URL . '/login.php');
        exit;
    }
    return $user;
}

function require_admin(): array {
    $user = require_login();
    if ($user['role'] !== 'admin') {
        http_response_code(403);
        include __DIR__ . '/../includes/header.php';
        echo '<div class="container py-5 text-center"><h3 class="text-danger">Access Denied</h3><p>You need administrator privileges for this page.</p><a href="' . APP_URL . '/dashboard.php" class="btn btn-primary">Go to Dashboard</a></div>';
        include __DIR__ . '/../includes/footer.php';
        exit;
    }
    return $user;
}

function login_user(string $email, string $password): bool {
    $stmt = db()->prepare('SELECT * FROM users WHERE email = ?');
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    if ($user && password_verify($password, $user['password'])) {
        session_start_safe();
        session_regenerate_id(true);
        $_SESSION['user'] = [
            'id'    => $user['id'],
            'name'  => $user['name'],
            'email' => $user['email'],
            'role'  => $user['role'],
        ];
        return true;
    }
    return false;
}

function logout_user(): void {
    session_start_safe();
    session_destroy();
    setcookie(session_name(), '', time() - 3600, '/');
}

function csrf_token(): string {
    session_start_safe();
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_verify(): void {
    session_start_safe();
    $token = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
        http_response_code(403);
        exit('Invalid CSRF token. Please go back and try again.');
    }
}
