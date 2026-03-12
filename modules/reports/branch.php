<?php
/**
 * Rani Mobiles ERP — Branch Performance Report
 */
require_once __DIR__ . '/../../config/auth.php';
require_auth();

$user    = current_user();
$isAdmin = is_admin();
$pdo     = db();

if (!$isAdmin) {
    header('Location: ' . BASE_URL . '/dashboard.php?err=access_denied');
    exit;
}

$fromDate = $_GET['from'] ?? date('Y-m-01');
$toDate   = $_GET['to']   ?? date('Y-m-d');

$branches = $pdo->query('SELECT id, code, name FROM branches ORDER BY code')->fetchAll();

$branchData = [];
foreach ($branches as $b) {
    $bid = $b['id'];

    $q = $pdo->prepare('SELECT COALESCE(SUM(total),0) FROM sales WHERE branch_id=? AND sale_date BETWEEN ? AND ?');
    $q->execute([$bid, $fromDate, $toDate]);
    $sales = (float)$q->fetchColumn();

    $q = $pdo->prepare('SELECT COALESCE(SUM(total),0) FROM purchases WHERE branch_id=? AND purchase_date BETWEEN ? AND ?');
    $q->execute([$bid, $fromDate, $toDate]);
    $purchase = (float)$q->fetchColumn();

    $q = $pdo->prepare('SELECT COALESCE(SUM(amount),0) FROM expenses WHERE branch_id=? AND expense_date BETWEEN ? AND ?');
    $q->execute([$bid, $fromDate, $toDate]);
    $expenses = (float)$q->fetchColumn();

    $q = $pdo->prepare(
        'SELECT COALESCE(SUM(si.total - p.purchase_cost * si.qty),0)
         FROM sale_items si JOIN sales s ON s.id=si.sale_id JOIN products p ON p.id=si.product_id
         WHERE s.branch_id=? AND s.sale_date BETWEEN ? AND ?'
    );
    $q->execute([$bid, $fromDate, $toDate]);
    $profit = (float)$q->fetchColumn();

    $q = $pdo->prepare('SELECT COALESCE(SUM(qty * purchase_cost),0) FROM stock s JOIN products p ON p.id=s.product_id WHERE s.branch_id=?');
    $q->execute([$bid]);
    $stockValue = (float)$q->fetchColumn();

    $branchData[] = [
        'code'        => $b['code'],
        'name'        => $b['name'],
        'sales'       => $sales,
        'purchase'    => $purchase,
        'expenses'    => $expenses,
        'profit'      => $profit,
        'stock_value' => $stockValue,
    ];
}

// CSV export
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="branch_report_' . $fromDate . '_to_' . $toDate . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Branch','Sales','Purchase','Expenses','Gross Profit','Stock Value']);
    foreach ($branchData as $d) {
        fputcsv($out, [
            $d['code'] . ' - ' . $d['name'],
            $d['sales'], $d['purchase'], $d['expenses'], $d['profit'], $d['stock_value']
        ]);
    }
    fclose($out);
    exit;
}

$pageTitle = 'Branch Report';
?>
<?php include __DIR__ . '/../../includes/header.php'; ?>
<script>window.BASE_URL = '<?= BASE_URL ?>';</script>

<div class="page-header">
  <h1 class="page-title"><i class="bi bi-shop me-2"></i>Branch Performance Report</h1>
  <div class="d-flex gap-2 no-print">
    <button onclick="window.print()" class="btn btn-outline-secondary btn-sm">
      <i class="bi bi-printer"></i> Print / PDF
    </button>
    <a href="?from=<?= urlencode($fromDate) ?>&to=<?= urlencode($toDate) ?>&export=csv"
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
    <div class="col-auto">
      <button type="submit" class="btn btn-primary btn-sm">Generate</button>
      <div class="d-inline-flex gap-1 ms-2">
        <a href="?from=<?= date('Y-m-d') ?>&to=<?= date('Y-m-d') ?>" class="btn btn-outline-secondary btn-sm">Today</a>
        <a href="?from=<?= date('Y-m-01') ?>&to=<?= date('Y-m-d') ?>" class="btn btn-outline-secondary btn-sm">Month</a>
        <a href="?from=<?= date('Y-01-01') ?>&to=<?= date('Y-m-d') ?>" class="btn btn-outline-secondary btn-sm">Year</a>
      </div>
    </div>
  </form>
</div>

<!-- Charts -->
<div class="row g-3 mb-4">
  <div class="col-lg-6">
    <div class="chart-card">
      <div class="chart-title">Branch Sales Comparison</div>
      <canvas id="branchSalesChart" height="120"></canvas>
    </div>
  </div>
  <div class="col-lg-6">
    <div class="chart-card">
      <div class="chart-title">Branch Profit Comparison</div>
      <canvas id="branchProfitChart" height="120"></canvas>
    </div>
  </div>
</div>

<!-- Table -->
<div class="erp-table">
  <div class="table-responsive">
    <table class="table table-hover mb-0">
      <thead>
        <tr><th>Branch</th><th class="text-end">Sales</th><th class="text-end">Purchase</th>
            <th class="text-end">Expenses</th><th class="text-end">Profit</th>
            <th class="text-end">Stock Value</th><th>Rank</th></tr>
      </thead>
      <tbody>
        <?php
          $sorted = $branchData;
          usort($sorted, fn($a,$b) => $b['sales'] <=> $a['sales']);
        ?>
        <?php foreach ($sorted as $i => $b): ?>
          <tr>
            <td>
              <div class="fw-bold"><?= clean($b['code']) ?></div>
              <small class="text-muted"><?= clean($b['name']) ?></small>
            </td>
            <td class="text-end fw-semibold"><?= money($b['sales']) ?></td>
            <td class="text-end"><?= money($b['purchase']) ?></td>
            <td class="text-end"><?= money($b['expenses']) ?></td>
            <td class="text-end <?= $b['profit']>=0?'text-success':'text-danger' ?> fw-semibold">
              <?= money($b['profit']) ?>
            </td>
            <td class="text-end"><?= money($b['stock_value']) ?></td>
            <td><?= $i===0?'🥇':($i===1?'🥈':($i===2?'🥉':'#'.($i+1))) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
      <tfoot class="table-secondary fw-bold">
        <tr>
          <td>TOTAL</td>
          <?php
            $tSales  = array_sum(array_column($branchData, 'sales'));
            $tPurch  = array_sum(array_column($branchData, 'purchase'));
            $tExp    = array_sum(array_column($branchData, 'expenses'));
            $tProfit = array_sum(array_column($branchData, 'profit'));
            $tStock  = array_sum(array_column($branchData, 'stock_value'));
          ?>
          <td class="text-end"><?= money($tSales) ?></td>
          <td class="text-end"><?= money($tPurch) ?></td>
          <td class="text-end"><?= money($tExp) ?></td>
          <td class="text-end <?= $tProfit>=0?'text-success':'text-danger' ?>"><?= money($tProfit) ?></td>
          <td class="text-end"><?= money($tStock) ?></td>
          <td></td>
        </tr>
      </tfoot>
    </table>
  </div>
</div>

<script>
(function() {
  const codes   = <?= json_encode(array_column($branchData, 'code')) ?>;
  const sales   = <?= json_encode(array_column($branchData, 'sales')) ?>;
  const profits = <?= json_encode(array_column($branchData, 'profit')) ?>;

  const salesCtx  = document.getElementById('branchSalesChart').getContext('2d');
  const profitCtx = document.getElementById('branchProfitChart').getContext('2d');

  const chartDefaults = {
    type: 'bar',
    options: {
      responsive: true,
      plugins: {
        legend: { display: false },
        tooltip: { callbacks: { label: ctx => '₹' + ctx.parsed.y.toLocaleString('en-IN') } }
      },
      scales: {
        y: { ticks: { callback: v => '₹' + v.toLocaleString('en-IN') } },
        x: { grid: { display: false } }
      }
    }
  };

  new Chart(salesCtx, {
    ...chartDefaults,
    data: {
      labels: codes,
      datasets: [{ label: 'Sales', data: sales.map(v => parseFloat(v)), backgroundColor: '#0f3460', borderRadius: 4 }]
    }
  });

  new Chart(profitCtx, {
    ...chartDefaults,
    data: {
      labels: codes,
      datasets: [{
        label: 'Profit',
        data: profits.map(v => parseFloat(v)),
        backgroundColor: profits.map(v => parseFloat(v) >= 0 ? '#28a745' : '#dc3545'),
        borderRadius: 4
      }]
    }
  });
})();
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
