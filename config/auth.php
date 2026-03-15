<?php
/**
 * Rani Mobiles ERP — Authentication Helpers
 */

require_once __DIR__ . '/database.php';

// Start session only once
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => SESSION_LIFETIME,
        'path'     => '/',
        'httponly' => true,
        'samesite' => 'Strict',
    ]);
    session_start();
}

/**
 * Redirect to login if not authenticated.
 */
function require_auth(): void {
    if (empty($_SESSION['user_id'])) {
        header('Location: ' . BASE_URL . '/index.php');
        exit;
    }
    // Regenerate session periodically
    if (!isset($_SESSION['last_regenerated']) || time() - $_SESSION['last_regenerated'] > 600) {
        session_regenerate_id(true);
        $_SESSION['last_regenerated'] = time();
    }
}

/**
 * Require Admin role.
 */
function require_admin(): void {
    require_auth();
    if (($_SESSION['role'] ?? '') !== 'Admin') {
        header('Location: ' . BASE_URL . '/dashboard.php?err=access_denied');
        exit;
    }
}

/**
 * Return current user info from session.
 */
function current_user(): array {
    return [
        'id'        => $_SESSION['user_id']   ?? 0,
        'name'      => $_SESSION['user_name'] ?? 'Guest',
        'role'      => $_SESSION['role']      ?? '',
        'branch_id' => $_SESSION['branch_id'] ?? 1,
        'branch'    => $_SESSION['branch']    ?? '',
    ];
}

/**
 * Check if current user is Admin.
 */
function is_admin(): bool {
    return ($_SESSION['role'] ?? '') === 'Admin';
}

/**
 * Sanitize and validate CSRF token.
 */
function csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verify_csrf(): void {
    $token = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
        http_response_code(403);
        die('CSRF validation failed.');
    }
}

/**
 * Sanitize user input.
 */
function clean(mixed $val): string {
    return htmlspecialchars(trim((string)$val), ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

/**
 * Format currency.
 */
function money(float $val): string {
    return CURRENCY_SYMBOL . number_format($val, 2);
}

/**
 * Format date for display.
 */
function fmt_date(string $date): string {
    return $date ? date('d-m-Y', strtotime($date)) : '-';
}

// ── Role helpers ────────────────────────────────────────────────────────────

function is_sup(): bool  { return in_array($_SESSION['role'] ?? '', ['Admin','SUP']); }
function is_bm(): bool   { return ($_SESSION['role'] ?? '') === 'BM'; }
function is_cash(): bool { return ($_SESSION['role'] ?? '') === 'CASH'; }
function is_sk(): bool   { return ($_SESSION['role'] ?? '') === 'SK'; }
function is_acc(): bool  { return ($_SESSION['role'] ?? '') === 'ACC'; }

function can_see_all_branches(): bool { return is_sup() || is_admin(); }
function user_role(): string          { return $_SESSION['role'] ?? ''; }

/**
 * Redirect unless Supervisor/Owner.
 */
function require_sup(): void {
    require_auth();
    if (!is_sup()) {
        header('Location: ' . BASE_URL . '/dashboard.php?err=access_denied');
        exit;
    }
}

/**
 * Heartbeat: update user presence table.
 */
function update_presence(): void {
    if (empty($_SESSION['user_id'])) return;
    try {
        $pdo = db();
        $pdo->prepare(
            "INSERT INTO user_presence (user_id, branch_id, last_seen)
             VALUES (?, ?, NOW())
             ON DUPLICATE KEY UPDATE last_seen = NOW(), branch_id = ?"
        )->execute([
            $_SESSION['user_id'],
            $_SESSION['branch_id'] ?? 1,
            $_SESSION['branch_id'] ?? 1,
        ]);
    } catch (\Throwable $e) { /* silently fail if table not yet created */ }
}
