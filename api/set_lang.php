<?php
/**
 * Rani Mobiles ERP — Set Language API
 * POST: lang=en|ta   (or GET for simplicity)
 * Redirects back to Referer or dashboard.
 */
require_once __DIR__ . '/../config/auth.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$lang = $_REQUEST['lang'] ?? 'en';
set_lang($lang);

$back = $_SERVER['HTTP_REFERER'] ?? (BASE_URL . '/dashboard.php');
header('Location: ' . $back);
exit;
