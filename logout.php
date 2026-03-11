<?php
/**
 * Rani Mobiles ERP — Logout
 */
require_once __DIR__ . '/config/auth.php';
if (!empty($_SESSION['user_id'])) {
    log_activity('LOGOUT', 'User logged out');
    // Record logout time in attendance
    db()->prepare(
        'UPDATE staff_attendance SET logout_time=NOW()
         WHERE user_id=? AND date=? AND logout_time IS NULL'
    )->execute([$_SESSION['user_id'], date('Y-m-d')]);
}
session_unset();
session_destroy();
header('Location: ' . BASE_URL . '/index.php');
exit;
