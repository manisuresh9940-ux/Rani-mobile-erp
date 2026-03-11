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

// ── Data Integrity Checks ──────────────────────────────────────

// Negative stock (should not happen)
$q = $isAdmin
   ? $pdo->prepare("SELECT COUNT(*) FROM stock WHERE qty < 0")
   : $pdo->prepare("SELECT COUNT(*) FROM stock WHERE branch_id=? AND qty < 0");
$q->execute($isAdmin ? [] : [$branch_id]);
$negStock = (int)$q->fetchColumn();
if ($negStock > 0) {
    $items[] = ['title' => '🚨 Negative Stock', 'message' => "$negStock product(s) have negative stock. Immediate review required."];
}

// Duplicate IMEI in stock (same IMEI assigned to more than one in-stock record)
$dupImei = (int)$pdo->query(
    "SELECT COUNT(*) FROM (
         SELECT imei, COUNT(*) AS cnt FROM imei_numbers
         WHERE status='in_stock' AND imei IS NOT NULL AND imei != ''
         GROUP BY imei HAVING cnt > 1
     ) AS dup"
)->fetchColumn();
if ($dupImei > 0) {
    $items[] = ['title' => '⚠️ Duplicate IMEI', 'message' => "$dupImei duplicate IMEI number(s) detected in stock."];
}

// Sales payment mismatch (paid < total with no credit recorded)
$q = $isAdmin
   ? $pdo->prepare(
       "SELECT COUNT(*) FROM sales
        WHERE (paid_cash + paid_upi + paid_card + paid_credit) < total - 0.01
          AND paid_credit = 0")
   : $pdo->prepare(
       "SELECT COUNT(*) FROM sales
        WHERE branch_id=?
          AND (paid_cash + paid_upi + paid_card + paid_credit) < total - 0.01
          AND paid_credit = 0");
$q->execute($isAdmin ? [] : [$branch_id]);
$payMismatch = (int)$q->fetchColumn();
if ($payMismatch > 0) {
    $items[] = ['title' => '💰 Payment Mismatch', 'message' => "$payMismatch sale(s) have unpaid balance without credit entry."];
}

// Pending transfer (sent but not received > 7 days)
$q = $isAdmin
   ? $pdo->prepare(
       "SELECT COUNT(*) FROM branch_transfers
        WHERE status='pending' AND DATEDIFF(CURDATE(), transfer_date) > 7")
   : $pdo->prepare(
       "SELECT COUNT(*) FROM branch_transfers
        WHERE (from_branch_id=? OR to_branch_id=?) AND status='pending'
          AND DATEDIFF(CURDATE(), transfer_date) > 7");
$q->execute($isAdmin ? [] : [$branch_id, $branch_id]);
$pendingTrf = (int)$q->fetchColumn();
if ($pendingTrf > 0) {
    $items[] = ['title' => '🔄 Pending Transfer', 'message' => "$pendingTrf branch transfer(s) are pending receipt for over 7 days."];
}

echo json_encode(['items' => $items]);
