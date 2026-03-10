<?php

/**
 * Rani Mobiles ERP — Front Controller
 *
 * All HTTP requests are routed through this single entry point.
 * The public/ directory is the document root; nothing outside it
 * is directly accessible over the web.
 */

declare(strict_types=1);

// ── 1. Define the application root (one level above public/) ──────────────────
define('APP_ROOT', dirname(__DIR__));

// ── 2. Bootstrap the application (autoloader, config, error handling) ─────────
require APP_ROOT . '/src/bootstrap.php';

// ── 3. Dispatch the request through the router ───────────────────────────────
use App\Router;

$router = new Router();
$router->dispatch();
