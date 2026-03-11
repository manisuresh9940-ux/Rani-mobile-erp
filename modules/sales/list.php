<?php
/**
 * Rani Mobiles ERP — Sales List
 */
require_once __DIR__ . '/../../config/auth.php';
require_auth();

$user      = current_user();
$branch_id = (int)$user['branch_id'];
$isAdmin   = is_admin();
$pdo       = db();

// Filters
$fromDate  = $_GET['from']   ?? date('Y-m-01');
$toDate    = $_GET['to']     ?? date('Y-m-d');
$search    = trim($_GET['s'] ?? '');
$fBranch   = (int)($_GET['branch'] ?? 0);

$params = [];
$where  = ['s.sale_date BETWEEN ? AND ?'];
$params = [$fromDate, $toDate];

if (!$isAdmin) {
    $where[] = 's.branch_id=?';
    $params[] = $branch_id;
} elseif ($fBranch) {
    $where[] = 's.branch_id=?';
    $params[] = $fBranch;
}

if ($search) {
    $where[] = '(s.invoice_no LIKE ? OR s.customer_name LIKE ? OR s.customer_phone LIKE ?)';
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$whereClause = implode(' AND ', $where);
$sales = $pdo->prepare(
    "SELECT s.*, b.code AS branch_code
     FROM sales s JOIN branches b ON b.id=s.branch_id
     WHERE $whereClause ORDER BY s.id DESC LIMIT 200"
);
$sales->execute($params);
$sales = $sales->fetchAll();

// ── CSV Export ────────────────────────────────────────────────
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="sales_' . $fromDate . '_to_' . $toDate . '.csv"');
    $out = fopen('php://output', 'w');
    fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF)); // UTF-8 BOM for Excel
    fputcsv($out, ['Invoice No', 'Branch', 'Date', 'Customer', 'Phone', 'Subtotal', 'GST', 'Total', 'Cash', 'UPI', 'Card', 'Credit', 'Type']);
    foreach ($sales as $s) {
        fputcsv($out, [
            $s['invoice_no'], $s['branch_code'], $s['sale_date'],
            $s['customer_name'] ?: 'Walk-in', $s['customer_phone'] ?? '',
            $s['subtotal'], $s['gst_amount'], $s['total'],
            $s['paid_cash'], $s['paid_upi'], $s['paid_card'], $s['paid_credit'],
            $s['is_gst_invoice'] ? 'GST' : 'Non-GST',
        ]);
    }
    fclose($out);
    exit;
}

// Totals
$totalSale   = array_sum(array_column($sales, 'total'));
$totalCash   = array_sum(array_column($sales, 'paid_cash'));
$totalUpi    = array_sum(array_column($sales, 'paid_upi'));
$totalCard   = array_sum(array_column($sales, 'paid_card'));
$totalCredit = array_sum(array_column($sales, 'paid_credit'));

$branches = $pdo->query('SELECT id, code, name FROM branches ORDER BY code')->fetchAll();

$pageTitle = 'Sales List';
?>
<?php include __DIR__ . '/../../includes/header.php'; ?>
<script>window.BASE_URL = '<?= BASE_URL ?>';</script>

<div class="page-header">
  <div>
    <h1 class="page-title"><i class="bi bi-receipt me-2"></i>Sales List</h1>
    <p class="page-subtitle"><?= count($sales) ?> records found</p>
  </div>
  <div class="d-flex gap-2">
    <a href="?<?= http_build_query(array_merge($_GET, ['export'=>'csv'])) ?>" class="btn btn-outline-success btn-sm">
      <i class="bi bi-file-earmark-spreadsheet me-1"></i>Export CSV
    </a>
    <a href="<?= BASE_URL ?>/modules/sales/pos.php" class="btn btn-primary btn-sm">
      <i class="bi bi-plus-circle me-1"></i>New Sale
    </a>
  </div>
</div>

<!-- Filters -->
<div class="erp-form-card mb-3">
  <form method="GET" class="row g-2 align-items-end">
    <div class="col-md-2">
      <label class="form-label">From Date</label>
      <input type="date" name="from" class="form-control form-control-sm" value="<?= $fromDate ?>">
    </div>
    <div class="col-md-2">
      <label class="form-label">To Date</label>
      <input type="date" name="to" class="form-control form-control-sm" value="<?= $toDate ?>">
    </div>
    <?php if ($isAdmin): ?>
    <div class="col-md-2">
      <label class="form-label">Branch</label>
      <select name="branch" class="form-select form-select-sm">
        <option value="">All Branches</option>
        <?php foreach ($branches as $b): ?>
          <option value="<?= $b['id'] ?>" <?= $fBranch==$b['id']?'selected':'' ?>><?= clean($b['code']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <?php endif; ?>
    <div class="col-md-3">
      <label class="form-label">Search</label>
      <input type="text" name="s" class="form-control form-control-sm"
             placeholder="Invoice, customer, phone…" value="<?= clean($search) ?>">
    </div>
    <div class="col-md-auto">
      <button type="submit" class="btn btn-primary btn-sm">
        <i class="bi bi-funnel me-1"></i>Filter
      </button>
      <a href="<?= BASE_URL ?>/modules/sales/list.php" class="btn btn-outline-secondary btn-sm ms-1">Reset</a>
    </div>
  </form>
</div>

<!-- Summary cards -->
<div class="row g-2 mb-3">
  <div class="col-6 col-md-3">
    <div class="kpi-card p-3">
      <div class="kpi-label">Total Sales</div>
      <div class="kpi-value fs-5"><?= money($totalSale) ?></div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="kpi-card p-3">
      <div class="kpi-label">Cash</div>
      <div class="kpi-value fs-5"><?= money($totalCash) ?></div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="kpi-card p-3">
      <div class="kpi-label">UPI + Card</div>
      <div class="kpi-value fs-5"><?= money($totalUpi + $totalCard) ?></div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="kpi-card p-3 <?= $totalCredit>0?'border-danger':'' ?>">
      <div class="kpi-label">Credit</div>
      <div class="kpi-value fs-5 <?= $totalCredit>0?'text-danger':'' ?>"><?= money($totalCredit) ?></div>
    </div>
  </div>
</div>

<!-- Table -->
<div class="erp-table">
  <div class="table-responsive">
    <table class="table table-hover mb-0" id="salesTable">
      <thead>
        <tr>
          <th>#</th>
          <th>Invoice</th>
          <th>Customer</th>
          <th>Branch</th>
          <th>Date</th>
          <th class="text-end">Total</th>
          <th>Payment</th>
          <th>GST</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($sales)): ?>
          <tr><td colspan="9" class="text-center text-muted py-4">No sales found for selected period</td></tr>
        <?php else: ?>
          <?php foreach ($sales as $i => $s): ?>
            <tr>
              <td><?= $i+1 ?></td>
              <td><code><?= clean($s['invoice_no']) ?></code></td>
              <td>
                <div><?= clean($s['customer_name'] ?: 'Walk-in') ?></div>
                <?php if ($s['customer_phone']): ?>
                  <small class="text-muted"><?= clean($s['customer_phone']) ?></small>
                <?php endif; ?>
              </td>
              <td><span class="badge bg-primary"><?= clean($s['branch_code']) ?></span></td>
              <td><?= fmt_date($s['sale_date']) ?></td>
              <td class="text-end fw-semibold"><?= money((float)$s['total']) ?></td>
              <td>
                <?php
                  $modes = [];
                  if ($s['paid_cash']   > 0) $modes[] = '<span class="badge bg-success">Cash</span>';
                  if ($s['paid_upi']    > 0) $modes[] = '<span class="badge bg-info text-dark">UPI</span>';
                  if ($s['paid_card']   > 0) $modes[] = '<span class="badge bg-warning text-dark">Card</span>';
                  if ($s['paid_credit'] > 0) $modes[] = '<span class="badge bg-danger">Credit</span>';
                  echo implode(' ', $modes);
                ?>
              </td>
              <td><?= $s['is_gst_invoice'] ? '<span class="badge bg-primary">GST</span>' : '<span class="badge bg-secondary">Non-GST</span>' ?></td>
              <td>
                <a href="<?= BASE_URL ?>/modules/sales/invoice.php?id=<?= $s['id'] ?>"
                   class="btn btn-sm btn-outline-primary" target="_blank" title="View Invoice">
                  <i class="bi bi-eye"></i>
                </a>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
