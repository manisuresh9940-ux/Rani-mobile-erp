<?php

/**
 * src/models/Database.php
 *
 * Thin alias / facade so application code can write:
 *
 *   use App\Models\Database;
 *   $pdo = Database::getConnection();
 *
 * The actual connection logic lives in App\Config\Database to keep
 * the config/ directory self-contained.
 */

declare(strict_types=1);

namespace App\Models;

use App\Config\Database as DbConfig;
use PDO;

class Database
{
    public static function getConnection(): PDO
    {
        return DbConfig::getConnection();
    }

    public static function closeConnection(): void
    {
        DbConfig::closeConnection();
    }
}
