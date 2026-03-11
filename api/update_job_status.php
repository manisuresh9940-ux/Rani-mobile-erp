<?php
/**
 * Rani Mobiles ERP — API: Update Job Card Status
 * POST /api/update_job_status.php
 */
require_once __DIR__ . '/../config/auth.php';
require_auth();

verify_csrf();

$job_id  = (int)($_POST['job_id'] ?? 0);
$status  = clean($_POST['status'] ?? '');
$notes   = clean($_POST['notes'] ?? '');
$rating  = isset($_POST['rating']) ? (int)$_POST['rating'] : null;
$allowed = ['received','diagnosed','in_repair','ready','delivered'];

if (!$job_id || !in_array($status, $allowed)) {
    header('Location: ' . BASE_URL . '/modules/service/list.php?error=invalid');
    exit;
}

/* Rating is only stored (1–5) when delivering; clear it for any other status */
if ($status === 'delivered') {
    $rating = ($rating >= 1 && $rating <= 5) ? $rating : null;
} else {
    $rating = null;
}

$pdo = db();

$delivered_at = $status === 'delivered' ? date('Y-m-d H:i:s') : null;

$pdo->prepare(
    'UPDATE service_jobs SET status=?, delivered_at=?, rating=? WHERE id=?'
)->execute([$status, $delivered_at, $rating, $job_id]);

log_activity('UPDATE_JOB_STATUS', "Job ID: $job_id → $status" . ($rating ? " (rating: $rating)" : ''));

header('Location: ' . BASE_URL . '/modules/service/list.php?msg=updated');
exit;
