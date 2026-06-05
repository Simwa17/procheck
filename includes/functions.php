<?php
// includes/functions.php — General helper functions

require_once __DIR__ . '/db.php';

function setting(string $key, string $default = ''): string {
    static $cache = [];
    if (!isset($cache[$key])) {
        $stmt = db()->prepare('SELECT setting_value FROM settings WHERE setting_key = ?');
        $stmt->execute([$key]);
        $row = $stmt->fetch();
        $cache[$key] = $row ? (string)$row['setting_value'] : $default;
    }
    return $cache[$key];
}

function format_mwk(float $amount): string {
    return 'MWK ' . number_format($amount, 2);
}

function format_usd(float $amount): string {
    return 'USD ' . number_format($amount, 2);
}

function next_quote_number(): string {
    $prefix = setting('quote_prefix', 'QT');
    $year   = date('Y');
    $stmt   = db()->prepare(
        "SELECT COUNT(*) AS cnt FROM quotes WHERE YEAR(created_at) = ?"
    );
    $stmt->execute([$year]);
    $cnt = (int)$stmt->fetch()['cnt'] + 1;
    return sprintf('%s-%s-%04d', $prefix, $year, $cnt);
}

function get_developer_rates(): array {
    return db()->query('SELECT * FROM developer_rates ORDER BY FIELD(tier,"junior","mid","senior")')
               ->fetchAll();
}

function get_rate_for_tier(string $tier): float {
    $stmt = db()->prepare('SELECT hourly_rate_mwk FROM developer_rates WHERE tier = ?');
    $stmt->execute([$tier]);
    $row = $stmt->fetch();
    return $row ? (float)$row['hourly_rate_mwk'] : 0.0;
}

function get_project_types(): array {
    return db()->query('SELECT * FROM project_types WHERE is_active = 1 ORDER BY name')->fetchAll();
}

function get_modules_with_categories(?int $project_type_id = null): array {
    // Return modules grouped by category
    // Show modules for the selected project type OR global modules (project_type_id IS NULL)
    $sql = '
        SELECT m.*, mc.name AS category_name, mc.project_type_id AS cat_project_type_id
        FROM modules m
        LEFT JOIN module_categories mc ON m.category_id = mc.id
        WHERE m.is_active = 1
        ORDER BY mc.name, m.name
    ';
    $rows = db()->query($sql)->fetchAll();
    $grouped = [];
    foreach ($rows as $row) {
        $cat = $row['category_name'] ?? 'Uncategorized';
        $grouped[$cat][] = $row;
    }
    return $grouped;
}

function get_quote_with_items(int $quote_id, int $user_id, bool $admin = false): ?array {
    $sql = 'SELECT q.*, pt.name AS project_type_name,
                   c.name AS client_name, c.company AS client_company,
                   c.email AS client_email, c.phone AS client_phone,
                   c.address AS client_address,
                   u.name AS creator_name, u.email AS creator_email
            FROM quotes q
            LEFT JOIN project_types pt ON q.project_type_id = pt.id
            LEFT JOIN clients c ON q.client_id = c.id
            LEFT JOIN users u ON q.user_id = u.id
            WHERE q.id = ?';
    $params = [$quote_id];
    if (!$admin) {
        $sql .= ' AND q.user_id = ?';
        $params[] = $user_id;
    }
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    $quote = $stmt->fetch();
    if (!$quote) return null;

    $items = db()->prepare('SELECT * FROM quote_items WHERE quote_id = ? ORDER BY id');
    $items->execute([$quote_id]);
    $quote['items'] = $items->fetchAll();
    return $quote;
}

function get_clients(int $user_id, bool $admin = false): array {
    if ($admin) {
        return db()->query('SELECT c.*, u.name AS owner_name FROM clients c JOIN users u ON c.user_id = u.id ORDER BY c.name')->fetchAll();
    }
    $stmt = db()->prepare('SELECT * FROM clients WHERE user_id = ? ORDER BY name');
    $stmt->execute([$user_id]);
    return $stmt->fetchAll();
}

function badge_status(string $status): string {
    $map = [
        'draft'    => 'secondary',
        'sent'     => 'primary',
        'accepted' => 'success',
        'rejected' => 'danger',
    ];
    $color = $map[$status] ?? 'secondary';
    return '<span class="badge bg-' . $color . '">' . ucfirst($status) . '</span>';
}

function tier_badge(string $tier): string {
    $map = [
        'junior' => 'info',
        'mid'    => 'warning',
        'senior' => 'danger',
    ];
    $color = $map[$tier] ?? 'secondary';
    return '<span class="badge bg-' . $color . '">' . ucfirst($tier) . '</span>';
}

function flash_set(string $key, string $message): void {
    $_SESSION['flash'][$key] = $message;
}

function flash_get(string $key): ?string {
    $msg = $_SESSION['flash'][$key] ?? null;
    unset($_SESSION['flash'][$key]);
    return $msg;
}

function h(string $str): string {
    return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
}
