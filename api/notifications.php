<?php
/**
 * Rani Mobiles ERP — API: Dashboard Notifications / Alerts
 * GET /api/notifications.php
 */
require_once __DIR__ . '/../config/auth.php';
require_auth();

header('Content-Type: application/json');

$user      = current_user();
$branch_id = (int)$user['branch_id'];
$isAdmin   = is_admin();
$pdo       = db();

$items = [];

// Low stock
$q = $isAdmin
   ? $pdo->prepare("SELECT COUNT(*) FROM stock s JOIN products p ON p.id=s.product_id WHERE s.qty<=p.min_stock AND s.qty>0")
   : $pdo->prepare("SELECT COUNT(*) FROM stock s JOIN products p ON p.id=s.product_id WHERE s.branch_id=? AND s.qty<=p.min_stock AND s.qty>0");
$q->execute($isAdmin ? [] : [$branch_id]);
$lowCount = (int)$q->fetchColumn();
if ($lowCount > 0) {
    $items[] = ['title' => '⚠️ Low Stock', 'message' => "$lowCount product(s) are below minimum stock level."];
}

// Out of stock
$q = $isAdmin
   ? $pdo->prepare("SELECT COUNT(*) FROM stock s WHERE s.qty<=0")
   : $pdo->prepare("SELECT COUNT(*) FROM stock s WHERE s.branch_id=? AND s.qty<=0");
$q->execute($isAdmin ? [] : [$branch_id]);
$outCount = (int)$q->fetchColumn();
if ($outCount > 0) {
    $items[] = ['title' => '❌ Out of Stock', 'message' => "$outCount product(s) are out of stock."];
}

// Pending credit sales
$q = $isAdmin
   ? $pdo->prepare("SELECT COALESCE(SUM(paid_credit),0) FROM sales")
   : $pdo->prepare("SELECT COALESCE(SUM(paid_credit),0) FROM sales WHERE branch_id=?");
$q->execute($isAdmin ? [] : [$branch_id]);
$credit = (float)$q->fetchColumn();
if ($credit > 0) {
    $items[] = ['title' => '💳 Credit Pending', 'message' => '₹' . number_format($credit, 2) . ' in credit sales pending.'];
}

// Pending vendor payments
$q = $isAdmin
   ? $pdo->prepare("SELECT COALESCE(SUM(balance),0) FROM purchases WHERE balance>0")
   : $pdo->prepare("SELECT COALESCE(SUM(balance),0) FROM purchases WHERE branch_id=? AND balance>0");
$q->execute($isAdmin ? [] : [$branch_id]);
$vendorDue = (float)$q->fetchColumn();
if ($vendorDue > 0) {
    $items[] = ['title' => '🏪 Vendor Payment Due', 'message' => '₹' . number_format($vendorDue, 2) . ' pending to vendors.'];
}

// Job cards ready
$q = $isAdmin
   ? $pdo->prepare("SELECT COUNT(*) FROM service_jobs WHERE status='ready'")
   : $pdo->prepare("SELECT COUNT(*) FROM service_jobs WHERE branch_id=? AND status='ready'");
$q->execute($isAdmin ? [] : [$branch_id]);
$readyJobs = (int)$q->fetchColumn();
if ($readyJobs > 0) {
    $items[] = ['title' => '🔧 Jobs Ready', 'message' => "$readyJobs job card(s) are ready for pickup."];
}

// Dead stock
$deadCount = (int)$pdo->query(
    "SELECT COUNT(DISTINCT p.id) FROM products p
     LEFT JOIN sale_items si ON si.product_id=p.id
     LEFT JOIN sales s ON s.id=si.sale_id
     WHERE p.is_active=1
     GROUP BY p.id
     HAVING MAX(s.sale_date) < DATE_SUB(CURDATE(), INTERVAL 45 DAY) OR MAX(s.sale_date) IS NULL
     LIMIT 10"
)->rowCount();
if ($deadCount > 0) {
    $items[] = ['title' => '📦 Dead Stock', 'message' => "Some products have no sales in 45+ days."];
}

echo json_encode(['items' => $items]);
