<?php
/**
 * Rani Mobiles ERP — Transfer List
 */
require_once __DIR__ . '/../../config/auth.php';
require_auth();

$user      = current_user();
$branch_id = (int)$user['branch_id'];
$isAdmin   = is_admin();
$pdo       = db();

$fromDate = $_GET['from'] ?? date('Y-m-01');
$toDate   = $_GET['to']   ?? date('Y-m-d');

$where  = ['t.transfer_date BETWEEN ? AND ?'];
$params = [$fromDate, $toDate];

if (!$isAdmin) {
    $where[] = '(t.from_branch_id=? OR t.to_branch_id=?)';
    $params[] = $branch_id;
    $params[] = $branch_id;
}

$transfers = $pdo->prepare(
    'SELECT t.*, fb.code AS from_code, tb.code AS to_code
     FROM branch_transfers t
     JOIN branches fb ON fb.id=t.from_branch_id
     JOIN branches tb ON tb.id=t.to_branch_id
     WHERE ' . implode(' AND ', $where) . ' ORDER BY t.id DESC LIMIT 200'
);
$transfers->execute($params);
$transfers = $transfers->fetchAll();

$pageTitle = 'Transfer List';
?>
<?php include __DIR__ . '/../../includes/header.php'; ?>
<script>window.BASE_URL = '<?= BASE_URL ?>';</script>

<div class="page-header">
  <h1 class="page-title"><i class="bi bi-arrow-left-right me-2"></i>Transfer List</h1>
  <a href="<?= BASE_URL ?>/modules/transfer/add.php" class="btn btn-primary btn-sm">
    <i class="bi bi-plus-circle me-1"></i>New Transfer
  </a>
</div>

<div class="erp-form-card mb-3">
  <form method="GET" class="row g-2 align-items-end">
    <div class="col-md-2"><label class="form-label">From</label>
      <input type="date" name="from" class="form-control form-control-sm" value="<?= $fromDate ?>"></div>
    <div class="col-md-2"><label class="form-label">To</label>
      <input type="date" name="to" class="form-control form-control-sm" value="<?= $toDate ?>"></div>
    <div class="col-auto">
      <button type="submit" class="btn btn-primary btn-sm">Filter</button>
      <a href="?" class="btn btn-outline-secondary btn-sm ms-1">Reset</a>
    </div>
  </form>
</div>

<div class="erp-table">
  <div class="table-responsive">
    <table class="table table-hover mb-0">
      <thead>
        <tr><th>#</th><th>Ref No</th><th>From</th><th>To</th><th>Date</th>
            <th class="text-end">Cost</th><th class="text-end">Transfer Price</th>
            <th class="text-end">Profit</th><th class="text-end">Balance</th><th>Status</th></tr>
      </thead>
      <tbody>
        <?php if (empty($transfers)): ?>
          <tr><td colspan="10" class="text-center text-muted py-4">No transfers found</td></tr>
        <?php else: ?>
          <?php foreach ($transfers as $i => $t): ?>
            <tr>
              <td><?= $i+1 ?></td>
              <td><code><?= clean($t['transfer_no']) ?></code></td>
              <td><span class="badge bg-secondary"><?= clean($t['from_code']) ?></span></td>
              <td><span class="badge bg-primary"><?= clean($t['to_code']) ?></span></td>
              <td><?= fmt_date($t['transfer_date']) ?></td>
              <td class="text-end"><?= money((float)$t['total_cost']) ?></td>
              <td class="text-end"><?= money((float)$t['transfer_price']) ?></td>
              <td class="text-end <?= $t['profit']>=0?'text-success':'text-danger' ?>">
                <?= money((float)$t['profit']) ?>
              </td>
              <td class="text-end <?= $t['balance']>0?'text-danger':'' ?>"><?= money((float)$t['balance']) ?></td>
              <td>
                <?php
                  $stColors = ['pending'=>'warning','received'=>'success','rejected'=>'danger'];
                  $sc = $stColors[$t['status']] ?? 'secondary';
                ?>
                <span class="badge bg-<?= $sc ?>"><?= ucfirst($t['status']) ?></span>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
