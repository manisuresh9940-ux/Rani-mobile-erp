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
$allowed = ['received','diagnosed','in_repair','ready','delivered'];

if (!$job_id || !in_array($status, $allowed)) {
    header('Location: ' . BASE_URL . '/modules/service/list.php?error=invalid');
    exit;
}

$pdo = db();

$delivered_at = $status === 'delivered' ? date('Y-m-d H:i:s') : null;

$pdo->prepare(
    'UPDATE service_jobs SET status=?, delivered_at=? WHERE id=?'
)->execute([$status, $delivered_at, $job_id]);

log_activity('UPDATE_JOB_STATUS', "Job ID: $job_id → $status");

header('Location: ' . BASE_URL . '/modules/service/list.php?msg=updated');
exit;
