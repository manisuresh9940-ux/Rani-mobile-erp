<?php
/**
 * Rani Mobiles ERP — Database Connection (PDO Singleton)
 */

require_once __DIR__ . '/config.php';

class DB {
    private static ?PDO $instance = null;

    public static function get(): PDO {
        if (self::$instance === null) {
            $dsn = sprintf('mysql:host=%s;dbname=%s;charset=%s', DB_HOST, DB_NAME, DB_CHARSET);
            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ];
            try {
                self::$instance = new PDO($dsn, DB_USER, DB_PASS, $options);
            } catch (PDOException $e) {
                die(json_encode(['error' => 'Database connection failed: ' . $e->getMessage()]));
            }
        }
        return self::$instance;
    }
}

/**
 * Shorthand helper — returns PDO instance.
 */
function db(): PDO {
    return DB::get();
}

/**
 * Generate next serial number for a branch + document type.
 * Format: R1-SAL-2026-0001
 */
function next_serial(int $branch_id, string $doc_type): string {
    $pdo  = db();
    $year = (int) date('Y');

    // Lock row and increment
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare(
            'INSERT INTO serial_numbers (branch_id, doc_type, year, last_serial)
             VALUES (?, ?, ?, 1)
             ON DUPLICATE KEY UPDATE last_serial = last_serial + 1'
        );
        $stmt->execute([$branch_id, $doc_type, $year]);

        $row = $pdo->prepare(
            'SELECT last_serial FROM serial_numbers WHERE branch_id=? AND doc_type=? AND year=?'
        );
        $row->execute([$branch_id, $doc_type, $year]);
        $seq = (int) $row->fetchColumn();

        // Get branch code
        $bStmt = $pdo->prepare('SELECT code FROM branches WHERE id=?');
        $bStmt->execute([$branch_id]);
        $code = $bStmt->fetchColumn() ?: 'XX';

        $pdo->commit();
        return sprintf('%s-%s-%d-%04d', $code, strtoupper($doc_type), $year, $seq);
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

/**
 * Log user activity.
 */
function log_activity(string $action, string $desc = ''): void {
    if (!isset($_SESSION['user_id'])) return;
    try {
        $pdo = db();
        $stmt = $pdo->prepare(
            'INSERT INTO activity_log (user_id, branch_id, action, description, ip_address)
             VALUES (?,?,?,?,?)'
        );
        $stmt->execute([
            $_SESSION['user_id'],
            $_SESSION['branch_id'] ?? null,
            $action,
            $desc,
            $_SERVER['REMOTE_ADDR'] ?? null,
        ]);
    } catch (Throwable) {}
}

/**
 * Update stock quantity for a branch/product (delta can be negative).
 */
function update_stock(int $branch_id, int $product_id, int $delta): void {
    $pdo = db();
    $pdo->prepare(
        'INSERT INTO stock (branch_id, product_id, qty)
         VALUES (?,?,?)
         ON DUPLICATE KEY UPDATE qty = qty + ?'
    )->execute([$branch_id, $product_id, $delta, $delta]);
}
