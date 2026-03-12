<?php
/**
 * Rani Mobiles ERP — Profit Report
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

$where  = ['s.sale_date BETWEEN ? AND ?'];
$params = [$fromDate, $toDate];
if ($fBranch) { $where[] = 's.branch_id=?'; $params[] = $fBranch; }
elseif (!$isAdmin) { $where[] = 's.branch_id=?'; $params[] = $branch_id; }

$whereClause = implode(' AND ', $where);

// Daily profit
$dailyProfit = $pdo->prepare(
    'SELECT s.sale_date AS date,
            COALESCE(SUM(si.total),0) AS revenue,
            COALESCE(SUM(p.purchase_cost * si.qty),0) AS cost,
            COALESCE(SUM(si.total - p.purchase_cost * si.qty),0) AS profit
     FROM sale_items si
     JOIN sales s ON s.id=si.sale_id
     JOIN products p ON p.id=si.product_id
     WHERE ' . $whereClause . '
     GROUP BY s.sale_date ORDER BY s.sale_date'
);
$dailyProfit->execute($params);
$dailyProfit = $dailyProfit->fetchAll();

// Total metrics
$totals = $pdo->prepare(
    'SELECT COALESCE(SUM(si.total),0) AS revenue,
            COALESCE(SUM(p.purchase_cost * si.qty),0) AS cost,
            COALESCE(SUM(si.total - p.purchase_cost * si.qty),0) AS gross_profit
     FROM sale_items si
     JOIN sales s ON s.id=si.sale_id
     JOIN products p ON p.id=si.product_id
     WHERE ' . $whereClause
);
$totals->execute($params);
$totals = $totals->fetch();

// Expenses
$expQ = $pdo->prepare(
    'SELECT COALESCE(SUM(amount),0) FROM expenses WHERE expense_date BETWEEN ? AND ?'
    . ($fBranch ? ' AND branch_id=?' : (!$isAdmin ? ' AND branch_id=?' : ''))
);
$expParams = [$fromDate, $toDate];
if ($fBranch || !$isAdmin) $expParams[] = $fBranch ?: $branch_id;
$expQ->execute($expParams);
$totalExpenses = (float)$expQ->fetchColumn();

$netProfit = (float)$totals['gross_profit'] - $totalExpenses;

$branches = $pdo->query('SELECT id, code FROM branches ORDER BY code')->fetchAll();

// CSV export
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="profit_report_' . $fromDate . '_to_' . $toDate . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Date','Revenue','Cost','Profit','Margin %']);
    foreach ($dailyProfit as $d) {
        $dm = $d['revenue'] > 0 ? round(($d['profit'] / $d['revenue']) * 100, 2) : 0;
        fputcsv($out, [$d['date'], $d['revenue'], $d['cost'], $d['profit'], $dm]);
    }
    fclose($out);
    exit;
}

$pageTitle = 'Profit Report';
?>
<?php include __DIR__ . '/../../includes/header.php'; ?>
<script>window.BASE_URL = '<?= BASE_URL ?>';</script>

<div class="page-header">
  <h1 class="page-title"><i class="bi bi-currency-rupee me-2"></i>Profit Report</h1>
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
    <div class="col-md-2">
      <label class="form-label">Branch</label>
      <select name="branch" class="form-select form-select-sm">
        <option value="">All</option>
        <?php foreach ($branches as $b): ?>
          <option value="<?= $b['id'] ?>" <?= $fBranch==$b['id']?'selected':'' ?>><?= clean($b['code']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <?php endif; ?>
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

<!-- Summary cards -->
<div class="row g-3 mb-4">
  <div class="col-6 col-md-3">
    <div class="kpi-card kpi-sales p-3">
      <div class="kpi-label">Revenue</div>
      <div class="kpi-value"><?= money((float)$totals['revenue']) ?></div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="kpi-card kpi-purchase p-3">
      <div class="kpi-label">Purchase Cost</div>
      <div class="kpi-value"><?= money((float)$totals['cost']) ?></div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="kpi-card p-3">
      <div class="kpi-label">Gross Profit</div>
      <div class="kpi-value <?= $totals['gross_profit']>=0?'text-success':'text-danger' ?>">
        <?= money((float)$totals['gross_profit']) ?>
      </div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="kpi-card p-3">
      <div class="kpi-label">Net Profit (after expenses)</div>
      <div class="kpi-value <?= $netProfit>=0?'text-success':'text-danger' ?>">
        <?= money($netProfit) ?>
      </div>
    </div>
  </div>
</div>

<!-- Profit margin -->
<?php
  $margin = $totals['revenue'] > 0 ? round(($totals['gross_profit'] / $totals['revenue']) * 100, 1) : 0;
?>
<div class="erp-form-card mb-4">
  <div class="d-flex align-items-center justify-content-between mb-2">
    <span>Gross Profit Margin: <strong><?= $margin ?>%</strong></span>
    <span class="text-muted small">Expenses: <?= money($totalExpenses) ?></span>
  </div>
  <div class="progress" style="height:20px">
    <div class="progress-bar <?= $margin>=20?'bg-success':($margin>=10?'bg-warning':'bg-danger') ?>"
         style="width:<?= min(100, $margin) ?>%"><?= $margin ?>%</div>
  </div>
</div>

<!-- Chart -->
<?php if (!empty($dailyProfit)): ?>
<div class="chart-card mb-4">
  <div class="chart-title">Daily Profit Trend</div>
  <canvas id="profitChart" height="80"></canvas>
</div>
<?php endif; ?>

<!-- Daily table -->
<div class="erp-table">
  <div class="p-3 border-bottom fw-bold">Daily Breakdown</div>
  <div class="table-responsive">
    <table class="table table-hover mb-0">
      <thead>
        <tr><th>Date</th><th class="text-end">Revenue</th>
            <th class="text-end">Cost</th><th class="text-end">Profit</th><th>Margin</th></tr>
      </thead>
      <tbody>
        <?php if (empty($dailyProfit)): ?>
          <tr><td colspan="5" class="text-center text-muted py-3">No data</td></tr>
        <?php else: ?>
          <?php foreach ($dailyProfit as $d):
            $dm = $d['revenue'] > 0 ? round(($d['profit']/$d['revenue'])*100,1) : 0;
          ?>
            <tr>
              <td><?= fmt_date($d['date']) ?></td>
              <td class="text-end"><?= money((float)$d['revenue']) ?></td>
              <td class="text-end"><?= money((float)$d['cost']) ?></td>
              <td class="text-end fw-semibold <?= $d['profit']>=0?'text-success':'text-danger' ?>">
                <?= money((float)$d['profit']) ?>
              </td>
              <td>
                <div class="progress" style="height:12px;min-width:80px">
                  <div class="progress-bar <?= $dm>=20?'bg-success':($dm>=10?'bg-warning':'bg-danger') ?>"
                       style="width:<?= min(100,$dm) ?>%"></div>
                </div>
                <small><?= $dm ?>%</small>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<script>
<?php if (!empty($dailyProfit)): ?>
(function() {
  const labels = <?= json_encode(array_map(fn($d) => date('d M', strtotime($d['date'])), $dailyProfit)) ?>;
  const revenues = <?= json_encode(array_column($dailyProfit, 'revenue')) ?>;
  const profits  = <?= json_encode(array_column($dailyProfit, 'profit')) ?>;

  const ctx = document.getElementById('profitChart').getContext('2d');
  new Chart(ctx, {
    type: 'line',
    data: {
      labels: labels,
      datasets: [
        {
          label: 'Revenue',
          data: revenues.map(v => parseFloat(v)),
          borderColor: '#0f3460',
          backgroundColor: 'rgba(15,52,96,0.1)',
          fill: true,
          tension: 0.4,
          pointRadius: 3
        },
        {
          label: 'Profit',
          data: profits.map(v => parseFloat(v)),
          borderColor: '#28a745',
          backgroundColor: 'rgba(40,167,69,0.1)',
          fill: true,
          tension: 0.4,
          pointRadius: 3
        }
      ]
    },
    options: {
      responsive: true,
      plugins: {
        tooltip: { callbacks: { label: ctx => ctx.dataset.label + ': ₹' + ctx.parsed.y.toLocaleString('en-IN') } }
      },
      scales: {
        y: { ticks: { callback: v => '₹' + v.toLocaleString('en-IN') } },
        x: { grid: { display: false } }
      }
    }
  });
})();
<?php endif; ?>
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
