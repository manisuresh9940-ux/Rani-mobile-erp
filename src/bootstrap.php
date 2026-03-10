<?php

/**
 * src/bootstrap.php
 *
 * Application bootstrap — the first file loaded by public/index.php.
 *
 * Responsibilities
 * ────────────────
 * 1. Guard: ensure APP_ROOT is defined (set in public/index.php).
 * 2. Load a .env file when present (development convenience).
 * 3. Load the main configuration (error reporting, constants, etc.).
 * 4. Register the PSR-4 class autoloader.
 * 5. Register a global exception handler so every uncaught exception
 *    results in a clean HTTP 500 page instead of a raw PHP stack trace.
 *
 * PHP 8.0+ required.
 */

declare(strict_types=1);

// ── 1. Sanity check ───────────────────────────────────────────────────────────
if (!defined('APP_ROOT')) {
    // This should never happen in normal operation; protects against
    // someone including bootstrap.php directly.
    http_response_code(500);
    exit('Application root is not defined. Please load the application through public/index.php.');
}

// ── 2. Load .env file (if present) ───────────────────────────────────────────
// A simple key=value parser — no external dependency required.
// Lines starting with # are treated as comments.
// Quoted values (single or double) are unquoted automatically.
$envFile = APP_ROOT . '/.env';
if (is_file($envFile) && is_readable($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }
        [$key, $value] = array_pad(explode('=', $line, 2), 2, '');
        $key   = trim($key);
        $value = trim($value, " \t\"'");   // strip surrounding quotes
        if ($key !== '' && !isset($_ENV[$key])) {
            $_ENV[$key]    = $value;
            $_SERVER[$key] = $value;
            putenv("{$key}={$value}");
        }
    }
}

// ── 3. Load configuration (error reporting, DB constants, …) ─────────────────
require_once APP_ROOT . '/src/config/config.php';

// ── 4. PSR-4 autoloader ──────────────────────────────────────────────────────
// Maps the `App\` namespace root to src/.
// Example: App\Controllers\HomeController → src/controllers/HomeController.php
spl_autoload_register(function (string $class): void {
    // Only handle classes under the App\ namespace.
    $prefix = 'App\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }

    // Strip the namespace prefix and convert backslashes to forward slashes.
    // e.g. App\Controllers\HomeController → Controllers/HomeController
    $relative = str_replace('\\', '/', substr($class, strlen($prefix)));

    // 1st attempt: exact case (e.g. src/Controllers/HomeController.php)
    $file = APP_ROOT . '/src/' . $relative . '.php';

    // 2nd attempt: lowercase first directory segment (e.g. src/controllers/HomeController.php)
    // This accommodates the convention of keeping directory names lowercase
    // while class files remain PascalCase.
    if (!file_exists($file)) {
        $parts    = explode('/', $relative, 2);
        $parts[0] = strtolower($parts[0]);
        $file     = APP_ROOT . '/src/' . implode('/', $parts) . '.php';
    }

    if (file_exists($file)) {
        require_once $file;
    }
    // If the file is still not found, PHP will emit a clear "Class not found"
    // error — we do not swallow it silently.
});

// ── 5. Global exception / error handler ──────────────────────────────────────
// Converts uncaught exceptions and fatal errors into a clean HTTP 500 response
// so the user never sees a raw PHP stack trace or blank white page.

set_exception_handler(function (Throwable $e): void {
    $statusCode = 500;

    // Log the full detail for the developer.
    error_log(sprintf(
        '[UNCAUGHT] %s: %s in %s on line %d%sStack trace:%s%s',
        get_class($e),
        $e->getMessage(),
        $e->getFile(),
        $e->getLine(),
        PHP_EOL,
        PHP_EOL,
        $e->getTraceAsString()
    ));

    if (!headers_sent()) {
        http_response_code($statusCode);
        header('Content-Type: text/html; charset=UTF-8');
    }

    // In development mode show a helpful error page;
    // in production show only a generic message.
    if (APP_DEBUG === true) {
        echo _renderDebugErrorPage($e);
    } else {
        $view = APP_ROOT . '/src/views/errors/500.php';
        if (file_exists($view)) {
            require $view;
        } else {
            echo '<h1>500 — Internal Server Error</h1><p>Something went wrong. Please try again later.</p>';
        }
    }
    exit(1);
});

set_error_handler(function (int $severity, string $message, string $file, int $line): bool {
    // Convert every PHP error (notice, warning, …) into an ErrorException
    // so it is caught by the exception handler above.
    if (!(error_reporting() & $severity)) {
        return false; // respect @ suppression operator
    }
    throw new \ErrorException($message, 0, $severity, $file, $line);
});

register_shutdown_function(function (): void {
    $error = error_get_last();
    if ($error !== null && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        if (!headers_sent()) {
            http_response_code(500);
        }
        error_log(sprintf(
            '[FATAL] %s in %s on line %d',
            $error['message'],
            $error['file'],
            $error['line']
        ));
        if (APP_DEBUG === true) {
            echo '<pre><b>Fatal Error:</b> ' . htmlspecialchars($error['message'], ENT_QUOTES, 'UTF-8') . "\n"
                . 'File: ' . htmlspecialchars($error['file'], ENT_QUOTES, 'UTF-8') . "\n"
                . 'Line: ' . $error['line'] . '</pre>';
        } else {
            $view = APP_ROOT . '/src/views/errors/500.php';
            if (file_exists($view)) {
                require $view;
            } else {
                echo '<h1>500 — Internal Server Error</h1><p>Something went wrong.</p>';
            }
        }
    }
});

// ── Helper: debug error page (only shown when APP_DEBUG=true) ────────────────
function _renderDebugErrorPage(Throwable $e): string
{
    $class   = htmlspecialchars(get_class($e),    ENT_QUOTES, 'UTF-8');
    $message = htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
    $file    = htmlspecialchars($e->getFile(),    ENT_QUOTES, 'UTF-8');
    $line    = $e->getLine();
    $trace   = htmlspecialchars($e->getTraceAsString(), ENT_QUOTES, 'UTF-8');

    return <<<HTML
    <!DOCTYPE html>
    <html lang="en">
    <head>
      <meta charset="UTF-8">
      <title>500 — Application Error [DEBUG]</title>
      <style>
        body { font-family: monospace; background: #1e1e1e; color: #d4d4d4; padding: 2rem; }
        h1   { color: #f44747; }
        .box { background: #252526; border: 1px solid #444; border-radius: 4px; padding: 1rem; margin: 1rem 0; }
        .label { color: #9cdcfe; font-weight: bold; }
        pre  { white-space: pre-wrap; word-break: break-word; }
      </style>
    </head>
    <body>
      <h1>&#9888; Application Error (Debug Mode)</h1>
      <div class="box">
        <span class="label">Exception:</span> {$class}<br>
        <span class="label">Message:</span>   {$message}<br>
        <span class="label">File:</span>      {$file}<br>
        <span class="label">Line:</span>      {$line}
      </div>
      <div class="box">
        <span class="label">Stack Trace:</span>
        <pre>{$trace}</pre>
      </div>
      <p><em>This page is only shown when APP_DEBUG=true. Set APP_DEBUG=false in production.</em></p>
    </body>
    </html>
    HTML;
}
