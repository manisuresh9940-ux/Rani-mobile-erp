<?php
/**
 * Rani Mobiles ERP — Presence Heartbeat API
 * POST or GET to update last_seen for the logged-in user.
 */
require_once __DIR__ . '/../config/auth.php';
header('Content-Type: application/json');

if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

update_presence();
echo json_encode(['ok' => true, 'ts' => date('Y-m-d H:i:s')]);
