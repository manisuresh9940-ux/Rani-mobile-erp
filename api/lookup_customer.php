<?php
/**
 * Rani Mobiles ERP — API: Customer Lookup
 * GET /api/lookup_customer.php?phone=9876543210
 */
require_once __DIR__ . '/../config/auth.php';
require_auth();

header('Content-Type: application/json');

$phone = trim($_GET['phone'] ?? '');
if (!$phone) {
    echo json_encode(['customer' => null]);
    exit;
}

$pdo  = db();
$stmt = $pdo->prepare(
    'SELECT id, name, phone, total_purchases, last_purchase
     FROM customers WHERE phone=? LIMIT 1'
);
$stmt->execute([$phone]);
$customer = $stmt->fetch();

echo json_encode(['customer' => $customer ?: null]);
