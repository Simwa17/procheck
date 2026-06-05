<?php
// includes/db.php — PDO database connection singleton

require_once __DIR__ . '/../config.php';

function db(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            // Database does not exist yet — send the user to the installer
            if ($e->getCode() === 1049 || str_contains($e->getMessage(), 'Unknown database')) {
                header('Location: ' . APP_URL . '/setup/install.php');
                exit;
            }
            // Any other connection problem — show a clean message
            http_response_code(500);
            exit('<b>Database connection failed.</b> Please check your settings in <code>config.php</code>.<br>Error: ' . htmlspecialchars($e->getMessage()));
        }
    }
    return $pdo;
}
