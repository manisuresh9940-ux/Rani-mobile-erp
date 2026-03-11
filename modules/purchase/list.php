<?php
/**
 * Rani Mobiles ERP — Purchase List
 */
require_once __DIR__ . '/../../config/auth.php';
require_auth();

$user      = current_user();
$branch_id = (int)$user['branch_id'];
$isAdmin   = is_admin();
$pdo       = db();

$fromDate = $_GET['from']   ?? date('Y-m-01');
$toDate   = $_GET['to']     ?? date('Y-m-d');
$search   = trim($_GET['s'] ?? '');
$fBranch  = (int)($_GET['branch'] ?? 0);

$where  = ['p.purchase_date BETWEEN ? AND ?'];
$params = [$fromDate, $toDate];

if (!$isAdmin) {
    $where[] = 'p.branch_id=?';
    $params[] = $branch_id;
} elseif ($fBranch) {
    $where[] = 'p.branch_id=?';
    $params[] = $fBranch;
}
if ($search) {
    $where[] = '(p.invoice_no LIKE ? OR p.vendor_name LIKE ?)';
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$purchases = $pdo->prepare(
    'SELECT p.*, b.code AS branch_code
     FROM purchases p JOIN branches b ON b.id=p.branch_id
     WHERE ' . implode(' AND ', $where) . ' ORDER BY p.id DESC LIMIT 200'
);
$purchases->execute($params);
$purchases = $purchases->fetchAll();

$branches = $pdo->query('SELECT id, code FROM branches ORDER BY code')->fetchAll();
$totalAmt  = array_sum(array_column($purchases, 'total'));
$totalPaid = array_sum(array_column($purchases, 'paid'));
$totalBal  = array_sum(array_column($purchases, 'balance'));

$pageTitle = 'Purchase List';
?>
<?php include __DIR__ . '/../../includes/header.php'; ?>
<script>window.BASE_URL = '<?= BASE_URL ?>';</script>

<div class="page-header">
  <h1 class="page-title"><i class="bi bi-truck me-2"></i>Purchase List</h1>
  <a href="<?= BASE_URL ?>/modules/purchase/add.php" class="btn btn-primary btn-sm">
    <i class="bi bi-plus-circle me-1"></i>New Purchase
  </a>
</div>

<div class="erp-form-card mb-3">
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
    <div class="col-md-3"><label class="form-label">Search</label>
      <input type="text" name="s" class="form-control form-control-sm" placeholder="Invoice, vendor…" value="<?= clean($search) ?>"></div>
    <div class="col-md-auto">
      <button type="submit" class="btn btn-primary btn-sm">Filter</button>
      <a href="?" class="btn btn-outline-secondary btn-sm ms-1">Reset</a>
    </div>
  </form>
</div>

<div class="row g-2 mb-3">
  <div class="col-md-4"><div class="kpi-card p-3"><div class="kpi-label">Total Purchases</div><div class="kpi-value fs-5"><?= money($totalAmt) ?></div></div></div>
  <div class="col-md-4"><div class="kpi-card p-3"><div class="kpi-label">Total Paid</div><div class="kpi-value fs-5 text-success"><?= money($totalPaid) ?></div></div></div>
  <div class="col-md-4"><div class="kpi-card p-3 <?= $totalBal>0?'border-danger':'' ?>"><div class="kpi-label">Outstanding</div><div class="kpi-value fs-5 <?= $totalBal>0?'text-danger':'' ?>"><?= money($totalBal) ?></div></div></div>
</div>

<div class="erp-table">
  <div class="table-responsive">
    <table class="table table-hover mb-0">
      <thead>
        <tr><th>#</th><th>Invoice</th><th>Vendor</th><th>Branch</th><th>Date</th>
            <th class="text-end">Total</th><th class="text-end">Paid</th>
            <th class="text-end">Balance</th><th>Payment</th></tr>
      </thead>
      <tbody>
        <?php if (empty($purchases)): ?>
          <tr><td colspan="9" class="text-center text-muted py-4">No purchases found</td></tr>
        <?php else: ?>
          <?php foreach ($purchases as $i => $p): ?>
            <tr>
              <td><?= $i+1 ?></td>
              <td><code><?= clean($p['invoice_no']) ?></code></td>
              <td><?= clean($p['vendor_name'] ?: '-') ?></td>
              <td><span class="badge bg-primary"><?= clean($p['branch_code']) ?></span></td>
              <td><?= fmt_date($p['purchase_date']) ?></td>
              <td class="text-end fw-semibold"><?= money((float)$p['total']) ?></td>
              <td class="text-end text-success"><?= money((float)$p['paid']) ?></td>
              <td class="text-end <?= $p['balance']>0?'text-danger':'' ?>"><?= money((float)$p['balance']) ?></td>
              <td><span class="badge bg-secondary"><?= clean($p['payment_mode']) ?></span></td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
