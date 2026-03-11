<?php
/**
 * Rani Mobiles ERP — Application Configuration
 */

define('APP_NAME',    'Rani Mobiles ERP');
define('APP_VERSION', '1.0.0');

// ── Database ──────────────────────────────────────────────────
define('DB_HOST', 'localhost');
define('DB_NAME', 'rani_erp');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

// ── Paths ─────────────────────────────────────────────────────
define('BASE_URL',    '/Rani-mobile-erp');
define('BASE_PATH',   __DIR__ . '/..');
define('UPLOAD_PATH', BASE_PATH . '/uploads');
define('UPLOAD_URL',  BASE_URL . '/uploads');

// ── Session ───────────────────────────────────────────────────
define('SESSION_LIFETIME', 3600 * 8);   // 8 hours

// ── Currency ──────────────────────────────────────────────────
define('CURRENCY_SYMBOL', '₹');

// ── Timezone ──────────────────────────────────────────────────
date_default_timezone_set('Asia/Kolkata');

// ── Error reporting ───────────────────────────────────────────
// Set to 0 on production
error_reporting(E_ALL);
ini_set('display_errors', 1);
