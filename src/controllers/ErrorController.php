<?php

/**
 * src/controllers/ErrorController.php
 *
 * Renders human-friendly error pages.
 */

declare(strict_types=1);

namespace App\Controllers;

class ErrorController extends BaseController
{
    public function notFound(): void
    {
        http_response_code(404);
        $this->render('errors/404', [
            'title'   => '404 — Page Not Found',
            'appName' => APP_NAME,
        ]);
    }

    public function serverError(): void
    {
        http_response_code(500);
        $this->renderPartial('errors/500', [
            'title'   => '500 — Internal Server Error',
            'appName' => APP_NAME,
        ]);
    }
}
