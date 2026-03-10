<?php

/**
 * src/config/config.php
 *
 * Central application configuration.
 *
 * Values are read from environment variables (set via .env loaded in
 * bootstrap.php or via the server / hosting panel's environment).
 * Hard-coded defaults are supplied only for values that have safe
 * non-secret defaults; secrets (DB passwords, etc.) must come from the
 * environment and will throw a clear exception if missing in production.
 *
 * PHP 8.0+ required.
 */

declare(strict_types=1);

// ── Helper: read an env variable with an optional default ────────────────────
if (!function_exists('env')) {
    function env(string $key, mixed $default = null): mixed
    {
        $value = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);

        if ($value === false) {
            return $default;
        }

        // Cast common string booleans so callers can use strict comparison.
        return match (strtolower((string) $value)) {
            'true',  '1', 'yes', 'on'  => true,
            'false', '0', 'no',  'off' => false,
            default                     => $value,
        };
    }
}

// ── Application environment ───────────────────────────────────────────────────
$appEnv   = env('APP_ENV', 'production');
$appDebug = env('APP_DEBUG', false);

define('APP_ENV',   $appEnv);
define('APP_DEBUG', $appDebug);
define('APP_NAME',  env('APP_NAME', 'Rani Mobiles ERP'));
define('APP_URL',   env('APP_URL',  'https://ranimobile.com'));

// ── PHP error reporting ───────────────────────────────────────────────────────
// In development : display all errors so problems are immediately visible.
// In production  : log everything but never display errors to the browser
//                  (exposing stack traces is a security risk).
if (APP_DEBUG === true) {
    ini_set('display_errors', '1');
    ini_set('display_startup_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
    ini_set('display_startup_errors', '0');
    error_reporting(E_ALL);          // still log everything
}

// ── Log file ──────────────────────────────────────────────────────────────────
$logPath = env('LOG_PATH', APP_ROOT . '/logs/app.log');
ini_set('log_errors', '1');
ini_set('error_log', $logPath);

// ── Database ──────────────────────────────────────────────────────────────────
define('DB_CONFIG', [
    'host'    => env('DB_HOST',    'localhost'),
    'port'    => (int) env('DB_PORT', 3306),
    'name'    => env('DB_NAME',    'rani_erp'),
    'user'    => env('DB_USER',    'root'),
    'pass'    => env('DB_PASS',    ''),
    'charset' => env('DB_CHARSET', 'utf8mb4'),
]);
