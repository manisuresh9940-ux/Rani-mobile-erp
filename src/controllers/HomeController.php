<?php

/**
 * src/controllers/HomeController.php
 *
 * Handles the public-facing pages of the Rani Mobiles ERP.
 */

declare(strict_types=1);

namespace App\Controllers;

use App\Config\Database;
use RuntimeException;

class HomeController extends BaseController
{
    // ── Pages ─────────────────────────────────────────────────────────────────

    public function index(): void
    {
        $this->render('home/index', [
            'title'   => APP_NAME . ' — Dashboard',
            'appName' => APP_NAME,
        ]);
    }

    public function about(): void
    {
        $this->render('home/about', [
            'title'   => APP_NAME . ' — About',
            'appName' => APP_NAME,
        ]);
    }

    // ── Health-check endpoint ─────────────────────────────────────────────────
    //
    // GET /health
    //
    // Returns a JSON payload that monitoring tools / deployment pipelines
    // can ping to verify:
    //   • the PHP application boots correctly, AND
    //   • the database is reachable.
    //
    // HTTP 200 = everything OK
    // HTTP 503 = database unreachable (application code is still running)

    public function health(): void
    {
        $status = [
            'app'      => 'ok',
            'php'      => PHP_VERSION,
            'env'      => APP_ENV,
            'database' => 'unknown',
        ];
        $httpStatus = 200;

        try {
            $pdo = Database::getConnection();
            $pdo->query('SELECT 1');
            $status['database'] = 'ok';
        } catch (RuntimeException $e) {
            $status['database'] = 'error';
            $status['db_error'] = APP_DEBUG ? $e->getMessage() : 'Database unreachable';
            $httpStatus = 503;
        }

        $this->json($status, $httpStatus);
    }
}
