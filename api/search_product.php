<?php
/**
 * Rani Mobiles ERP — API: Product Search
 * GET /api/search_product.php?q=query&branch=1
 */
require_once __DIR__ . '/../config/auth.php';
require_auth();

header('Content-Type: application/json');

$q         = trim($_GET['q'] ?? '');
$branch_id = (int)($_GET['branch'] ?? $_SESSION['branch_id'] ?? 1);

if (strlen($q) < 2) {
    echo json_encode(['products' => []]);
    exit;
}

$pdo = db();

// Detect if query is a price search (e.g. "8000")
$isPrice = is_numeric($q);

if ($isPrice) {
    $price = (float)$q;
    $min   = $price * 0.75;
    $max   = $price * 1.25;

    $stmt = $pdo->prepare(
        'SELECT p.id, p.name, p.model, p.barcode, p.sale_price, p.purchase_cost,
                p.gst_percent, p.imei_tracking, p.barcode_tracking,
                br.name AS brand,
                COALESCE(s.qty, 0) AS stock, p.min_stock
         FROM products p
         JOIN brands br ON br.id=p.brand_id
         LEFT JOIN stock s ON s.product_id=p.id AND s.branch_id=?
         WHERE p.is_active=1 AND p.sale_price BETWEEN ? AND ?
         ORDER BY ABS(p.sale_price - ?) ASC
         LIMIT 20'
    );
    $stmt->execute([$branch_id, $min, $max, $price]);
} else {
    $like = "%$q%";
    $stmt = $pdo->prepare(
        'SELECT p.id, p.name, p.model, p.barcode, p.sale_price, p.purchase_cost,
                p.gst_percent, p.imei_tracking, p.barcode_tracking,
                br.name AS brand,
                COALESCE(s.qty, 0) AS stock, p.min_stock
         FROM products p
         JOIN brands br ON br.id=p.brand_id
         LEFT JOIN stock s ON s.product_id=p.id AND s.branch_id=?
         WHERE p.is_active=1
           AND (p.name LIKE ? OR p.model LIKE ? OR br.name LIKE ?
                OR p.barcode = ? OR EXISTS (
                    SELECT 1 FROM imei_numbers i
                    WHERE i.product_id=p.id AND i.imei LIKE ? AND i.status=\'in_stock\'
                ))
         ORDER BY s.qty DESC, p.name ASC
         LIMIT 20'
    );
    $stmt->execute([$branch_id, $like, $like, $like, $q, $like]);
}

$products = $stmt->fetchAll();

// Add full name
foreach ($products as &$p) {
    $p['full_name'] = $p['brand'] . ' ' . $p['name'] . ($p['model'] ? ' ' . $p['model'] : '');
}
unset($p);

echo json_encode(['products' => $products]);
