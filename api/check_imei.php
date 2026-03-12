<?php
/**
 * Rani Mobiles ERP — API: IMEI Duplicate Check
 * GET /api/check_imei.php?imei=XXXXXXXXXXXXXX
 *
 * Returns JSON:
 *   { "exists": false }
 *   { "exists": true, "status": "available", "product": "Samsung Galaxy A54", "branch": "R1" }
 */
require_once __DIR__ . '/../config/auth.php';
require_auth();

header('Content-Type: application/json');

$imei = trim($_GET['imei'] ?? '');

if (strlen($imei) < 14) {
    echo json_encode(['exists' => false, 'error' => 'IMEI must be at least 14 characters.']);
    exit;
}

$pdo = db();

// Check imei_stock (spec-aligned table) first
$stmt = $pdo->prepare(
    'SELECT is.status, p.name AS product, b.code AS branch
     FROM imei_stock is
     JOIN products  p ON p.id=is.product_id
     JOIN branches  b ON b.id=is.branch_id
     WHERE is.imei=?'
);
$stmt->execute([$imei]);
$row = $stmt->fetch();

if ($row) {
    echo json_encode([
        'exists'  => true,
        'status'  => $row['status'],
        'product' => $row['product'],
        'branch'  => $row['branch'],
        'source'  => 'imei_stock',
    ]);
    exit;
}

// Also check imei_numbers (existing tracking table)
$stmt = $pdo->prepare(
    'SELECT im.status, p.name AS product, b.code AS branch
     FROM imei_numbers im
     JOIN products  p ON p.id=im.product_id
     JOIN branches  b ON b.id=im.branch_id
     WHERE im.imei=?'
);
$stmt->execute([$imei]);
$row = $stmt->fetch();

if ($row) {
    echo json_encode([
        'exists'  => true,
        'status'  => $row['status'],
        'product' => $row['product'],
        'branch'  => $row['branch'],
        'source'  => 'imei_numbers',
    ]);
    exit;
}

// Also check second_hand_purchases
$stmt = $pdo->prepare(
    'SELECT sh.condition AS status, sh.model AS product, b.code AS branch
     FROM second_hand_purchases sh
     JOIN branches b ON b.id=sh.branch_id
     WHERE sh.imei=?'
);
$stmt->execute([$imei]);
$row = $stmt->fetch();

if ($row) {
    echo json_encode([
        'exists'  => true,
        'status'  => 'second_hand:' . $row['status'],
        'product' => $row['product'],
        'branch'  => $row['branch'],
        'source'  => 'second_hand',
    ]);
    exit;
}

echo json_encode(['exists' => false]);
