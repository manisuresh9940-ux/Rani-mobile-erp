<?php

/**
 * src/Router.php
 *
 * Minimal front-controller router.
 *
 * Maps incoming URL paths to controller actions using a simple
 * convention:
 *
 *   /                → HomeController::index()
 *   /home            → HomeController::index()
 *   /home/about      → HomeController::about()
 *   /<ctrl>          → <Ctrl>Controller::index()
 *   /<ctrl>/<action> → <Ctrl>Controller::<action>()
 *
 * Route registration supports explicit routes that override the
 * convention.
 */

declare(strict_types=1);

namespace App;

use App\Controllers\ErrorController;

class Router
{
    /** @var array<string, array{controller: string, action: string}> */
    private array $routes = [];

    public function __construct()
    {
        $this->registerDefaultRoutes();
    }

    // ── Route registration ────────────────────────────────────────────────────

    public function get(string $path, string $controller, string $action): void
    {
        $this->routes['GET'][$path] = compact('controller', 'action');
    }

    public function post(string $path, string $controller, string $action): void
    {
        $this->routes['POST'][$path] = compact('controller', 'action');
    }

    // ── Default routes ────────────────────────────────────────────────────────

    private function registerDefaultRoutes(): void
    {
        $this->get('/',      'HomeController', 'index');
        $this->get('/home',  'HomeController', 'index');
        $this->get('/about', 'HomeController', 'about');
        $this->get('/health', 'HomeController', 'health');
    }

    // ── Dispatch ──────────────────────────────────────────────────────────────

    public function dispatch(): void
    {
        $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
        $uri    = $this->parseUri();

        // 1. Check explicit route table first.
        if (isset($this->routes[$method][$uri])) {
            $route      = $this->routes[$method][$uri];
            $controller = $route['controller'];
            $action     = $route['action'];
        } else {
            // 2. Fall back to convention: /segment1/segment2 → Segment1Controller::segment2().
            [$controller, $action] = $this->resolveConventionRoute($uri);
        }

        $fqcn = 'App\\Controllers\\' . $controller;

        if (!class_exists($fqcn)) {
            $this->handleNotFound();
            return;
        }

        $instance = new $fqcn();

        if (!method_exists($instance, $action)) {
            $this->handleNotFound();
            return;
        }

        $instance->$action();
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    /**
     * Return the clean URI path (strips query string and trailing slash).
     */
    private function parseUri(): string
    {
        $uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
        $uri = '/' . trim($uri, '/');

        // Sanitise: allow only alphanumeric characters, hyphens, underscores, slashes.
        $uri = preg_replace('/[^a-zA-Z0-9\-_\/]/', '', $uri);

        return $uri ?: '/';
    }

    /**
     * Derive controller and action names from a URI path segment.
     *
     * /                  → HomeController, index
     * /dashboard         → DashboardController, index
     * /dashboard/reports → DashboardController, reports
     */
    private function resolveConventionRoute(string $uri): array
    {
        $parts      = array_filter(explode('/', ltrim($uri, '/')));
        $parts      = array_values($parts);
        $segment1   = $parts[0] ?? 'home';
        $segment2   = $parts[1] ?? 'index';

        $controller = ucfirst(strtolower($segment1)) . 'Controller';
        $action     = lcfirst(str_replace('-', '', ucwords($segment2, '-')));

        return [$controller, $action];
    }

    private function handleNotFound(): void
    {
        http_response_code(404);
        $ctrl = new ErrorController();
        $ctrl->notFound();
    }
}
