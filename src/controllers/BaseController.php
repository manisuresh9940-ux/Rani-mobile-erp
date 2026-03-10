<?php

/**
 * src/controllers/BaseController.php
 *
 * Abstract base class for all application controllers.
 *
 * Provides:
 *  - render()   — load a view file and inject variables
 *  - json()     — emit a JSON response
 *  - redirect() — emit an HTTP redirect
 *
 * Usage in a child controller:
 *
 *   class HomeController extends BaseController
 *   {
 *       public function index(): void
 *       {
 *           $this->render('home/index', ['title' => 'Home']);
 *       }
 *   }
 */

declare(strict_types=1);

namespace App\Controllers;

use RuntimeException;

abstract class BaseController
{
    // ── View rendering ────────────────────────────────────────────────────────

    /**
     * Render a view template wrapped in the main layout.
     *
     * @param string               $view      Relative path inside src/views/
     *                                        (without .php extension).
     * @param array<string, mixed> $data      Variables made available inside
     *                                        the view via extract().
     * @param string               $layout    Layout template name (default: 'main').
     */
    protected function render(string $view, array $data = [], string $layout = 'main'): void
    {
        $viewFile   = APP_ROOT . '/src/views/' . $view . '.php';
        $layoutFile = APP_ROOT . '/src/views/layouts/' . $layout . '.php';

        if (!file_exists($viewFile)) {
            throw new RuntimeException("View file not found: {$viewFile}");
        }

        // Make data variables available to the view/layout.
        extract($data, EXTR_SKIP);

        // Capture view content into a variable so the layout can embed it.
        ob_start();
        require $viewFile;
        $content = ob_get_clean();

        if (file_exists($layoutFile)) {
            require $layoutFile;
        } else {
            // No layout found — output content directly.
            echo $content;
        }
    }

    /**
     * Render a view WITHOUT a layout (useful for AJAX partials).
     *
     * @param array<string, mixed> $data
     */
    protected function renderPartial(string $view, array $data = []): void
    {
        $viewFile = APP_ROOT . '/src/views/' . $view . '.php';

        if (!file_exists($viewFile)) {
            throw new RuntimeException("View file not found: {$viewFile}");
        }

        extract($data, EXTR_SKIP);
        require $viewFile;
    }

    // ── JSON response ─────────────────────────────────────────────────────────

    /**
     * Emit a JSON response and halt execution.
     *
     * @param mixed $data
     */
    protected function json(mixed $data, int $statusCode = 200): never
    {
        if (!headers_sent()) {
            http_response_code($statusCode);
            header('Content-Type: application/json; charset=UTF-8');
        }
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        exit;
    }

    // ── Redirect ──────────────────────────────────────────────────────────────

    /**
     * Redirect the browser to another URL and halt execution.
     */
    protected function redirect(string $url, int $statusCode = 302): never
    {
        if (!headers_sent()) {
            http_response_code($statusCode);
            header('Location: ' . $url);
        }
        exit;
    }
}
