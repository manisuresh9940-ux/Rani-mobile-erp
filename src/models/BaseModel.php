<?php

/**
 * src/models/BaseModel.php
 *
 * Abstract base class for all application models.
 *
 * Provides:
 *  - A shared PDO connection (lazy-initialised).
 *  - Convenience methods for common query patterns.
 *  - Safe parameter binding to prevent SQL injection.
 *
 * Child model example:
 *
 *   class ProductModel extends BaseModel
 *   {
 *       protected string $table = 'products';
 *
 *       public function findAll(): array
 *       {
 *           return $this->all();
 *       }
 *   }
 */

declare(strict_types=1);

namespace App\Models;

use PDO;
use PDOStatement;
use RuntimeException;

abstract class BaseModel
{
    /** Override in child classes to set the default table name. */
    protected string $table = '';

    private ?PDO $pdo = null;

    // ── Connection ────────────────────────────────────────────────────────────

    protected function db(): PDO
    {
        if ($this->pdo === null) {
            $this->pdo = Database::getConnection();
        }
        return $this->pdo;
    }

    // ── Generic query helpers ─────────────────────────────────────────────────

    /**
     * Execute a raw query and return the prepared statement.
     *
     * @param array<string, mixed> $params
     */
    protected function query(string $sql, array $params = []): PDOStatement
    {
        $stmt = $this->db()->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    /**
     * Fetch a single row by primary key.
     *
     * @return array<string, mixed>|null
     */
    public function findById(int $id): ?array
    {
        $this->requireTable();
        $stmt = $this->query(
            "SELECT * FROM `{$this->table}` WHERE id = :id LIMIT 1",
            [':id' => $id]
        );
        $row = $stmt->fetch();
        return $row !== false ? $row : null;
    }

    /**
     * Fetch all rows from the model's table.
     *
     * @return array<int, array<string, mixed>>
     */
    public function all(): array
    {
        $this->requireTable();
        return $this->query("SELECT * FROM `{$this->table}`")->fetchAll();
    }

    /**
     * Count all rows in the model's table.
     */
    public function count(): int
    {
        $this->requireTable();
        $result = $this->query("SELECT COUNT(*) AS cnt FROM `{$this->table}`")->fetch();
        return (int) ($result['cnt'] ?? 0);
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    private function requireTable(): void
    {
        if ($this->table === '') {
            throw new RuntimeException(
                static::class . ' must define a $table property.'
            );
        }
    }
}
