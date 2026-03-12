<?php
/**
 * Rani Mobiles ERP — Purchase Report
 */
require_once __DIR__ . '/../../config/auth.php';
require_auth();

$user      = current_user();
$branch_id = (int)$user['branch_id'];
$isAdmin   = is_admin();
$pdo       = db();

$fromDate = $_GET['from'] ?? date('Y-m-01');
$toDate   = $_GET['to']   ?? date('Y-m-d');
$fBranch  = $isAdmin ? (int)($_GET['branch'] ?? 0) : $branch_id;

$where  = ['p.purchase_date BETWEEN ? AND ?'];
$params = [$fromDate, $toDate];
if ($fBranch) { $where[] = 'p.branch_id=?'; $params[] = $fBranch; }
elseif (!$isAdmin) { $where[] = 'p.branch_id=?'; $params[] = $branch_id; }

$summary = $pdo->prepare(
    'SELECT COUNT(*) AS cnt,
            COALESCE(SUM(total),0) AS total,
            COALESCE(SUM(paid),0) AS paid,
            COALESCE(SUM(balance),0) AS balance,
            COALESCE(SUM(gst_amount),0) AS gst
     FROM purchases p WHERE ' . implode(' AND ', $where)
);
$summary->execute($params);
$summary = $summary->fetch();

$topVendors = $pdo->prepare(
    'SELECT vendor_name, COUNT(*) AS orders, SUM(total) AS total, SUM(balance) AS due
     FROM purchases p WHERE ' . implode(' AND ', $where) . '
     GROUP BY vendor_name ORDER BY total DESC LIMIT 10'
);
$topVendors->execute($params);
$topVendors = $topVendors->fetchAll();

$branches = $pdo->query('SELECT id, code FROM branches ORDER BY code')->fetchAll();

// CSV export
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    $exportRows = $pdo->prepare(
        'SELECT p.invoice_no, p.purchase_date, b.code AS branch, p.vendor_name,
                p.total, p.paid, p.balance, p.gst_amount, p.payment_mode, p.notes
         FROM purchases p JOIN branches b ON b.id=p.branch_id
         WHERE ' . implode(' AND ', $where) . ' ORDER BY p.purchase_date DESC'
    );
    $exportRows->execute($params);
    $rows = $exportRows->fetchAll();

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="purchase_report_' . $fromDate . '_to_' . $toDate . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Invoice No','Date','Branch','Vendor','Total','Paid','Balance','GST','Payment Mode','Notes']);
    foreach ($rows as $r) {
        fputcsv($out, [
            $r['invoice_no'], $r['purchase_date'], $r['branch'], $r['vendor_name'],
            $r['total'], $r['paid'], $r['balance'], $r['gst_amount'], $r['payment_mode'], $r['notes']
        ]);
    }
    fclose($out);
    exit;
}

$pageTitle = 'Purchase Report';
?>
<?php include __DIR__ . '/../../includes/header.php'; ?>
<script>window.BASE_URL = '<?= BASE_URL ?>';</script>

<div class="page-header">
  <h1 class="page-title"><i class="bi bi-graph-down me-2"></i>Purchase Report</h1>
  <div class="d-flex gap-2 no-print">
    <button onclick="window.print()" class="btn btn-outline-secondary btn-sm">
      <i class="bi bi-printer"></i> Print / PDF
    </button>
    <a href="?from=<?= urlencode($fromDate) ?>&to=<?= urlencode($toDate) ?>&branch=<?= $fBranch ?>&export=csv"
       class="btn btn-outline-success btn-sm">
      <i class="bi bi-file-earmark-excel me-1"></i>Export CSV
    </a>
  </div>
</div>

<div class="erp-form-card mb-3 no-print">
  <form method="GET" class="row g-2 align-items-end">
    <div class="col-md-2"><label class="form-label">From</label>
      <input type="date" name="from" class="form-control form-control-sm" value="<?= $fromDate ?>"></div>
    <div class="col-md-2"><label class="form-label">To</label>
      <input type="date" name="to" class="form-control form-control-sm" value="<?= $toDate ?>"></div>
    <?php if ($isAdmin): ?>
    <div class="col-md-2"><label class="form-label">Branch</label>
      <select name="branch" class="form-select form-select-sm">
        <option value="">All</option>
        <?php foreach ($branches as $b): ?>
          <option value="<?= $b['id'] ?>" <?= $fBranch==$b['id']?'selected':'' ?>><?= clean($b['code']) ?></option>
        <?php endforeach; ?>
      </select></div>
    <?php endif; ?>
    <div class="col-auto">
      <button type="submit" class="btn btn-primary btn-sm">Generate</button></div>
  </form>
</div>

<div class="row g-3 mb-4">
  <div class="col-6 col-md-3"><div class="kpi-card p-3 kpi-purchase"><div class="kpi-label">Total Purchase</div><div class="kpi-value"><?= money((float)$summary['total']) ?></div></div></div>
  <div class="col-6 col-md-3"><div class="kpi-card p-3"><div class="kpi-label">Invoices</div><div class="kpi-value"><?= (int)$summary['cnt'] ?></div></div></div>
  <div class="col-6 col-md-3"><div class="kpi-card p-3"><div class="kpi-label">Amount Paid</div><div class="kpi-value text-success"><?= money((float)$summary['paid']) ?></div></div></div>
  <div class="col-6 col-md-3"><div class="kpi-card p-3 <?= $summary['balance']>0?'border-danger':'' ?>"><div class="kpi-label">Outstanding</div><div class="kpi-value text-danger"><?= money((float)$summary['balance']) ?></div></div></div>
</div>

<div class="erp-table">
  <div class="p-3 border-bottom fw-bold">Top Vendors by Purchase</div>
  <div class="table-responsive">
    <table class="table table-hover mb-0">
      <thead>
        <tr><th>#</th><th>Vendor</th><th class="text-end">Orders</th>
            <th class="text-end">Total</th><th class="text-end">Due</th></tr>
      </thead>
      <tbody>
        <?php foreach ($topVendors as $i => $v): ?>
          <tr>
            <td><?= $i+1 ?></td>
            <td><?= clean($v['vendor_name'] ?: '-') ?></td>
            <td class="text-end"><?= $v['orders'] ?></td>
            <td class="text-end fw-semibold"><?= money((float)$v['total']) ?></td>
            <td class="text-end <?= $v['due']>0?'text-danger':'' ?>"><?= money((float)$v['due']) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
