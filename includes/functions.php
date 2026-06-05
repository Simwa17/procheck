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

// ── Invoice helpers ────────────────────────────────────────────────────────────

function next_invoice_number(): string {
    $prefix = setting('invoice_prefix', 'INV');
    $year   = date('Y');
    $stmt   = db()->prepare("SELECT COUNT(*) AS cnt FROM invoices WHERE YEAR(issued_at) = ?");
    $stmt->execute([$year]);
    $cnt = (int)$stmt->fetch()['cnt'] + 1;
    return sprintf('%s-%s-%04d', $prefix, $year, $cnt);
}

function get_invoice_for_quote(int $quote_id): ?array {
    $stmt = db()->prepare('SELECT * FROM invoices WHERE quote_id = ? LIMIT 1');
    $stmt->execute([$quote_id]);
    return $stmt->fetch() ?: null;
}

function get_invoice(int $invoice_id, int $user_id, bool $admin = false): ?array {
    $sql = 'SELECT i.*, q.quote_number, q.project_name, q.client_id,
                   c.name AS client_name, c.company AS client_company,
                   c.email AS client_email, c.phone AS client_phone,
                   c.address AS client_address
            FROM invoices i
            JOIN quotes q ON i.quote_id = q.id
            LEFT JOIN clients c ON q.client_id = c.id
            WHERE i.id = ?';
    $params = [$invoice_id];
    if (!$admin) {
        $sql .= ' AND i.user_id = ?';
        $params[] = $user_id;
    }
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    $inv = $stmt->fetch();
    if (!$inv) return null;

    $p = db()->prepare('SELECT * FROM payments WHERE invoice_id = ? ORDER BY payment_date');
    $p->execute([$invoice_id]);
    $inv['payments'] = $p->fetchAll();
    return $inv;
}

function get_invoices(int $user_id, bool $admin = false): array {
    $sql = 'SELECT i.*, q.quote_number, q.project_name,
                   c.name AS client_name, c.company AS client_company
            FROM invoices i
            JOIN quotes q ON i.quote_id = q.id
            LEFT JOIN clients c ON q.client_id = c.id';
    if (!$admin) {
        $sql .= ' WHERE i.user_id = ?';
        $stmt = db()->prepare($sql . ' ORDER BY i.issued_at DESC');
        $stmt->execute([$user_id]);
    } else {
        $stmt = db()->prepare($sql . ' ORDER BY i.issued_at DESC');
        $stmt->execute();
    }
    return $stmt->fetchAll();
}

function badge_invoice_status(string $status): string {
    $map = ['unpaid' => 'danger', 'partial' => 'warning', 'paid' => 'success'];
    return '<span class="badge bg-' . ($map[$status] ?? 'secondary') . '">' . ucfirst($status) . '</span>';
}

// ── Quote financials ───────────────────────────────────────────────────────────

/**
 * Compute discount/tax breakdown from stored quote data.
 * Returns amounts in MWK.
 */
function compute_quote_financials(array $quote): array {
    $subtotal    = (float)$quote['subtotal_mwk'];
    $margin_pct  = (float)$quote['margin_percent'];
    $base        = $subtotal * (1 + $margin_pct / 100);

    $disc_type   = $quote['discount_type'] ?? 'percent';
    $disc_val    = (float)($quote['discount_value'] ?? 0);
    $disc_amount = $disc_type === 'percent'
                    ? $base * $disc_val / 100
                    : min($disc_val, $base);
    $after_disc  = max(0, $base - $disc_amount);

    $tax_rate    = (float)($quote['tax_rate'] ?? 0);
    $tax_amount  = $after_disc * $tax_rate / 100;
    $grand_total = $after_disc + $tax_amount;

    return [
        'subtotal'        => $subtotal,
        'margin_amount'   => $base - $subtotal,
        'base'            => $base,
        'discount_amount' => $disc_amount,
        'after_discount'  => $after_disc,
        'tax_amount'      => $tax_amount,
        'grand_total'     => $grand_total,
    ];
}

/**
 * Recompute quote totals from line items + discount/tax and persist to DB.
 */
function recalc_quote_totals(int $quote_id): void {
    $pdo = db();
    $q = $pdo->prepare('SELECT margin_percent, discount_type, discount_value, tax_rate, usd_rate FROM quotes WHERE id = ?');
    $q->execute([$quote_id]);
    $quote = $q->fetch();
    if (!$quote) return;

    $s = $pdo->prepare('SELECT SUM(hours) AS th, SUM(total_mwk) AS sub FROM quote_items WHERE quote_id = ?');
    $s->execute([$quote_id]);
    $sums = $s->fetch();

    $total_hours = (float)($sums['th']  ?? 0);
    $subtotal    = (float)($sums['sub'] ?? 0);

    $fin = compute_quote_financials(array_merge((array)$quote, ['subtotal_mwk' => $subtotal]));
    $usd = (float)($quote['usd_rate'] ?: 1800);

    $pdo->prepare('UPDATE quotes SET total_hours=?, subtotal_mwk=?, total_mwk=?, total_usd=? WHERE id=?')
        ->execute([$total_hours, $subtotal, $fin['grand_total'], $fin['grand_total'] / $usd, $quote_id]);
}

// ── Quote duplication ──────────────────────────────────────────────────────────

function duplicate_quote(int $quote_id, int $user_id): int {
    $pdo = db();
    $orig = $pdo->prepare('SELECT * FROM quotes WHERE id = ? AND user_id = ?');
    $orig->execute([$quote_id, $user_id]);
    $q = $orig->fetch();
    if (!$q) return 0;

    $new_number = next_quote_number();
    $parent_rev = (int)($q['revision_number'] ?? 1);

    $pdo->prepare('INSERT INTO quotes
        (user_id, client_id, quote_number, project_name, project_type_id,
         developer_tier, total_hours, subtotal_mwk, margin_percent, discount_type,
         discount_value, tax_rate, total_mwk, usd_rate, total_usd, notes,
         status, valid_until, parent_quote_id, revision_number)
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
    ')->execute([
        $user_id, $q['client_id'], $new_number, $q['project_name'] . ' (Copy)',
        $q['project_type_id'], $q['developer_tier'], $q['total_hours'],
        $q['subtotal_mwk'], $q['margin_percent'], $q['discount_type'] ?? 'percent',
        $q['discount_value'] ?? 0, $q['tax_rate'] ?? 0,
        $q['total_mwk'], $q['usd_rate'], $q['total_usd'], $q['notes'],
        'draft', $q['valid_until'], $quote_id, $parent_rev,
    ]);
    $new_id = (int)$pdo->lastInsertId();

    // Copy items
    $items = $pdo->prepare('SELECT * FROM quote_items WHERE quote_id = ?');
    $items->execute([$quote_id]);
    $ins = $pdo->prepare('INSERT INTO quote_items
        (quote_id, item_type, module_id, module_name, description, complexity, hours, rate_mwk, total_mwk)
        VALUES (?,?,?,?,?,?,?,?,?)');
    foreach ($items->fetchAll() as $item) {
        $ins->execute([
            $new_id, $item['item_type'] ?? 'module', $item['module_id'],
            $item['module_name'], $item['description'], $item['complexity'],
            $item['hours'], $item['rate_mwk'], $item['total_mwk'],
        ]);
    }
    return $new_id;
}

// ── Revision history ───────────────────────────────────────────────────────────

function save_quote_revision(int $quote_id, int $user_id, string $note = ''): void {
    $pdo = db();
    $q = $pdo->prepare('SELECT * FROM quotes WHERE id = ?');
    $q->execute([$quote_id]);
    $quote = $q->fetch();
    if (!$quote) return;

    $items = $pdo->prepare('SELECT * FROM quote_items WHERE quote_id = ?');
    $items->execute([$quote_id]);
    $snapshot = ['quote' => $quote, 'items' => $items->fetchAll()];

    $rev_num = (int)($quote['revision_number'] ?? 1);

    $pdo->prepare('INSERT INTO quote_revisions (quote_id, revision_number, changed_by, change_note, snapshot) VALUES (?,?,?,?,?)')
        ->execute([$quote_id, $rev_num, $user_id, $note, json_encode($snapshot)]);

    $pdo->prepare('UPDATE quotes SET revision_number = revision_number + 1 WHERE id = ?')
        ->execute([$quote_id]);
}

function get_quote_revisions(int $quote_id): array {
    $stmt = db()->prepare(
        'SELECT qr.*, u.name AS changed_by_name
         FROM quote_revisions qr
         JOIN users u ON qr.changed_by = u.id
         WHERE qr.quote_id = ?
         ORDER BY qr.changed_at DESC'
    );
    $stmt->execute([$quote_id]);
    return $stmt->fetchAll();
}

// ── Milestones ─────────────────────────────────────────────────────────────────

function get_quote_milestones(int $quote_id): array {
    $stmt = db()->prepare('SELECT * FROM quote_milestones WHERE quote_id = ? ORDER BY sort_order, id');
    $stmt->execute([$quote_id]);
    return $stmt->fetchAll();
}

function badge_milestone_status(string $status): string {
    $map = ['pending' => 'secondary', 'invoiced' => 'warning', 'paid' => 'success'];
    return '<span class="badge bg-' . ($map[$status] ?? 'secondary') . '">' . ucfirst($status) . '</span>';
}
