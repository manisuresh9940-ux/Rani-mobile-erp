<?php

/**
 * src/config/Database.php
 *
 * PDO database connection factory.
 *
 * Usage (anywhere inside the application after bootstrap):
 *
 *   $pdo = \App\Config\Database::getConnection();
 *
 * Design decisions
 * ────────────────
 * • Uses PDO (not deprecated mysql_* functions) for PHP 8 compatibility.
 * • PDO::ERRMODE_EXCEPTION ensures every SQL error becomes a catchable
 *   exception rather than a silent failure that causes mysterious HTTP 500s.
 * • PDO::FETCH_ASSOC is the default fetch mode (predictable, avoids
 *   duplicate columns from FETCH_BOTH).
 * • utf8mb4 charset prevents "Incorrect string value" errors for emoji /
 *   multi-byte characters.
 * • The singleton pattern keeps one connection alive for the request
 *   lifetime and avoids repeated TCP handshakes.
 */

declare(strict_types=1);

namespace App\Config;

use PDO;
use PDOException;
use RuntimeException;

class Database
{
    private static ?PDO $instance = null;

    // Prevent direct instantiation.
    private function __construct() {}

    /**
     * Return (creating if necessary) the shared PDO connection.
     *
     * @throws RuntimeException when the connection cannot be established,
     *         with a user-friendly message that does NOT expose credentials.
     */
    public static function getConnection(): PDO
    {
        if (self::$instance !== null) {
            return self::$instance;
        }

        $cfg = DB_CONFIG;   // defined in src/config/config.php

        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=%s',
            $cfg['host'],
            $cfg['port'],
            $cfg['name'],
            $cfg['charset']
        );

        $options = [
            // Throw exceptions on every error — no silent failures.
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            // Return associative arrays by default.
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            // Disable emulated prepares for real prepared statements.
            PDO::ATTR_EMULATE_PREPARES   => false,
            // Keep the connection alive during long requests.
            PDO::ATTR_PERSISTENT         => false,
        ];

        try {
            self::$instance = new PDO($dsn, $cfg['user'], $cfg['pass'], $options);
        } catch (PDOException $e) {
            // Log the full technical detail.
            error_log('[DB] Connection failed: ' . $e->getMessage());

            // Surface a safe, generic message to the caller.
            throw new RuntimeException(
                'Database connection failed. Please check your configuration and ensure ' .
                'the MySQL server is running. See the application log for details.',
                (int) $e->getCode(),
                $e
            );
        }

        return self::$instance;
    }

    /**
     * Close the connection (useful in long-running CLI scripts).
     */
    public static function closeConnection(): void
    {
        self::$instance = null;
    }
}
