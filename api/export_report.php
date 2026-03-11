<?php
/**
 * Rani Mobiles ERP — Report Export (CSV)
 * GET /api/export_report.php?type=sales&from=2026-01-01&to=2026-01-31
 */
require_once __DIR__ . '/../config/auth.php';
require_auth();

$user      = current_user();
$branch_id = (int)$user['branch_id'];
$isAdmin   = is_admin();
$pdo       = db();

$type    = $_GET['type'] ?? '';
$from    = $_GET['from'] ?? date('Y-m-01');
$to      = $_GET['to']   ?? date('Y-m-d');
$custId  = (int)($_GET['id'] ?? 0);
$date    = $_GET['date']  ?? date('Y-m-d');
$fBranch = (int)($_GET['branch'] ?? 0);

/**
 * Output a CSV file.
 */
function output_csv(string $filename, array $headers, array $rows): void {
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
    echo "\xEF\xBB\xBF"; // UTF-8 BOM for Excel compatibility

    $fh = fopen('php://output', 'w');
    fputcsv($fh, $headers);
    foreach ($rows as $row) {
        fputcsv($fh, array_values($row));
    }
    fclose($fh);
    exit;
}

switch ($type) {

    // ─── Sales Report ─────────────────────────────────────────────
    case 'sales': {
        $where  = ['s.sale_date BETWEEN ? AND ?'];
        $params = [$from, $to];

        if (!$isAdmin) {
            $where[] = 's.branch_id=?'; $params[] = $branch_id;
        } elseif ($fBranch) {
            $where[] = 's.branch_id=?'; $params[] = $fBranch;
        }

        $stmt = $pdo->prepare(
            'SELECT s.invoice_no, s.sale_date, b.code AS branch, s.customer_name,
                    s.customer_phone, s.subtotal, s.gst_amount, s.total,
                    s.paid_cash, s.paid_upi, s.paid_card, s.paid_credit,
                    IF(s.is_gst_invoice,"Yes","No") AS gst_invoice, s.notes
             FROM sales s JOIN branches b ON b.id=s.branch_id
             WHERE ' . implode(' AND ', $where) . '
             ORDER BY s.id DESC'
        );
        $stmt->execute($params);
        $rows = $stmt->fetchAll();

        output_csv(
            'sales_report_' . $from . '_to_' . $to . '.csv',
            ['Invoice No','Date','Branch','Customer Name','Customer Phone','Subtotal','GST Amount','Total',
             'Cash Paid','UPI Paid','Card Paid','Credit','GST Invoice','Notes'],
            $rows
        );
    }

    // ─── Purchase Report ──────────────────────────────────────────
    case 'purchase': {
        $where  = ['p.purchase_date BETWEEN ? AND ?'];
        $params = [$from, $to];

        if (!$isAdmin) {
            $where[] = 'p.branch_id=?'; $params[] = $branch_id;
        } elseif ($fBranch) {
            $where[] = 'p.branch_id=?'; $params[] = $fBranch;
        }

        $stmt = $pdo->prepare(
            'SELECT p.invoice_no, p.purchase_date, b.code AS branch, p.vendor_name,
                    p.vendor_invoice, p.subtotal, p.gst_amount, p.total,
                    p.paid, p.balance, p.payment_mode, p.notes
             FROM purchases p JOIN branches b ON b.id=p.branch_id
             WHERE ' . implode(' AND ', $where) . '
             ORDER BY p.id DESC'
        );
        $stmt->execute($params);
        $rows = $stmt->fetchAll();

        output_csv(
            'purchase_report_' . $from . '_to_' . $to . '.csv',
            ['Invoice No','Date','Branch','Vendor Name','Vendor Invoice',
             'Subtotal','GST Amount','Total','Paid','Balance','Payment Mode','Notes'],
            $rows
        );
    }

    // ─── Stock Report ─────────────────────────────────────────────
    case 'stock': {
        $where  = [];
        $params = [];

        if (!$isAdmin) {
            $where[] = 's.branch_id=?'; $params[] = $branch_id;
        } elseif ($fBranch) {
            $where[] = 's.branch_id=?'; $params[] = $fBranch;
        }

        $whereSQL = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        $stmt = $pdo->prepare(
            'SELECT b.code AS branch, br.name AS brand, p.name AS product, p.model,
                    p.barcode, p.hsn_code, p.purchase_cost, p.sale_price,
                    p.gst_percent, s.qty AS stock_qty, p.min_stock,
                    (s.qty * p.purchase_cost) AS stock_value,
                    IF(s.qty<=0,"Out of Stock", IF(s.qty<=p.min_stock,"Low","OK")) AS status
             FROM stock s
             JOIN branches b  ON b.id=s.branch_id
             JOIN products p  ON p.id=s.product_id
             JOIN brands br   ON br.id=p.brand_id
             ' . $whereSQL . '
             ORDER BY br.name, p.name'
        );
        $stmt->execute($params);
        $rows = $stmt->fetchAll();

        output_csv(
            'stock_report_' . date('Y-m-d') . '.csv',
            ['Branch','Brand','Product','Model','Barcode','HSN Code',
             'Purchase Cost','Sale Price','GST%','Stock Qty','Min Stock','Stock Value','Status'],
            $rows
        );
    }

    // ─── Customer Ledger ──────────────────────────────────────────
    case 'customers': {
        $stmt = $pdo->query(
            'SELECT c.name, c.phone, c.email, c.address, c.gstin,
                    c.total_purchases, c.last_purchase, c.created_at
             FROM customers c ORDER BY c.total_purchases DESC'
        );
        $rows = $stmt->fetchAll();

        output_csv(
            'customers_' . date('Y-m-d') . '.csv',
            ['Name','Phone','Email','Address','GSTIN','Total Purchases','Last Purchase','Created At'],
            $rows
        );
    }

    // ─── Customer Purchase History ────────────────────────────────
    case 'customer_history': {
        if (!$custId) {
            http_response_code(400);
            die('Customer ID required');
        }

        $cust = $pdo->prepare('SELECT name FROM customers WHERE id=?');
        $cust->execute([$custId]);
        $custName = $cust->fetchColumn() ?: 'customer';

        $stmt = $pdo->prepare(
            'SELECT s.invoice_no, s.sale_date, b.code AS branch,
                    s.total, s.paid_cash, s.paid_upi, s.paid_card, s.paid_credit, s.notes
             FROM sales s
             JOIN branches b ON b.id=s.branch_id
             WHERE s.customer_id=?
             ORDER BY s.id DESC'
        );
        $stmt->execute([$custId]);
        $rows = $stmt->fetchAll();

        output_csv(
            'customer_history_' . preg_replace('/\W+/', '_', $custName) . '.csv',
            ['Invoice No','Date','Branch','Total','Cash','UPI','Card','Credit','Notes'],
            $rows
        );
    }

    // ─── Vendor Ledger ────────────────────────────────────────────
    case 'vendors': {
        $stmt = $pdo->query(
            'SELECT v.name, v.phone, v.email, v.address, v.gstin,
                    COALESCE(SUM(pu.total),0) AS total_purchased,
                    COALESCE(SUM(pu.balance),0) AS balance_due
             FROM vendors v
             LEFT JOIN purchases pu ON pu.vendor_id=v.id
             GROUP BY v.id ORDER BY total_purchased DESC'
        );
        $rows = $stmt->fetchAll();

        output_csv(
            'vendors_' . date('Y-m-d') . '.csv',
            ['Name','Phone','Email','Address','GSTIN','Total Purchased','Balance Due'],
            $rows
        );
    }

    // ─── Attendance ───────────────────────────────────────────────
    case 'attendance': {
        $where  = ['sa.date=?'];
        $params = [$date];

        if (!$isAdmin) {
            $where[] = 'sa.branch_id=?'; $params[] = $branch_id;
        }

        $stmt = $pdo->prepare(
            'SELECT u.full_name, u.username, b.code AS branch, sa.date,
                    sa.login_time, sa.logout_time,
                    IF(sa.is_late,"Late","On Time") AS status,
                    sa.latitude, sa.longitude, sa.notes
             FROM staff_attendance sa
             JOIN users u ON u.id=sa.user_id
             JOIN branches b ON b.id=sa.branch_id
             WHERE ' . implode(' AND ', $where) . '
             ORDER BY sa.login_time ASC'
        );
        $stmt->execute($params);
        $rows = $stmt->fetchAll();

        output_csv(
            'attendance_' . $date . '.csv',
            ['Staff Name','Username','Branch','Date','Check-In','Check-Out','Status','Latitude','Longitude','Notes'],
            $rows
        );
    }

    // ─── IMEI List ────────────────────────────────────────────────
    case 'imei': {
        $where  = [];
        $params = [];

        if (!$isAdmin) {
            $where[] = 'i.branch_id=?'; $params[] = $branch_id;
        } elseif ($fBranch) {
            $where[] = 'i.branch_id=?'; $params[] = $fBranch;
        }

        $whereSQL = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        $stmt = $pdo->prepare(
            'SELECT b.code AS branch, br.name AS brand, p.name AS product, p.model,
                    i.imei, i.status, i.created_at
             FROM imei_numbers i
             JOIN branches b  ON b.id=i.branch_id
             JOIN products p  ON p.id=i.product_id
             JOIN brands br   ON br.id=p.brand_id
             ' . $whereSQL . '
             ORDER BY i.created_at DESC'
        );
        $stmt->execute($params);
        $rows = $stmt->fetchAll();

        output_csv(
            'imei_list_' . date('Y-m-d') . '.csv',
            ['Branch','Brand','Product','Model','IMEI','Status','Created At'],
            $rows
        );
    }

    default:
        http_response_code(400);
        die('Invalid export type. Supported: sales, purchase, stock, customers, customer_history, vendors, attendance, imei');
}
