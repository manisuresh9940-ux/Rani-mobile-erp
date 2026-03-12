<?php
/**
 * Rani Mobiles ERP — Staff Sales & Performance Report
 */
require_once __DIR__ . '/../../config/auth.php';
require_auth();
require_admin();

$pdo = db();

$fromDate = $_GET['from']   ?? date('Y-m-01');
$toDate   = $_GET['to']     ?? date('Y-m-d');
$fBranch  = (int)($_GET['branch'] ?? 0);

$staffWhere  = ['ss.date BETWEEN ? AND ?'];
$staffParams = [$fromDate, $toDate];
if ($fBranch) { $staffWhere[] = 'u.branch_id=?'; $staffParams[] = $fBranch; }

$staffWhereClause = implode(' AND ', $staffWhere);

// Per-staff aggregation from staff_sales
$perfQ = $pdo->prepare(
    'SELECT u.id, u.full_name, r.name AS role_name, b.code AS branch_code,
            COUNT(ss.sale_id)  AS sales_count,
            COALESCE(SUM(ss.amount),0)  AS total_sales,
            COALESCE(SUM(ss.profit),0)  AS total_profit,
            COALESCE(AVG(ss.amount),0)  AS avg_sale,
            MAX(ss.date) AS last_sale_date
     FROM staff_sales ss
     JOIN users u    ON u.id=ss.staff_id
     JOIN roles r    ON r.id=u.role_id
     JOIN branches b ON b.id=u.branch_id
     WHERE ' . $staffWhereClause . '
     GROUP BY ss.staff_id
     ORDER BY total_sales DESC'
);
$perfQ->execute($staffParams);
$staffPerf = $perfQ->fetchAll();

// Summary totals
$totalSales  = array_sum(array_column($staffPerf, 'total_sales'));
$totalProfit = array_sum(array_column($staffPerf, 'total_profit'));
$totalCount  = array_sum(array_column($staffPerf, 'sales_count'));

// Daily breakdown for top performer
$topStaff = $staffPerf[0] ?? null;
$dailyRows = [];
if ($topStaff) {
    $dQ = $pdo->prepare(
        'SELECT ss.date, SUM(ss.amount) AS sales, SUM(ss.profit) AS profit, COUNT(*) AS txns
         FROM staff_sales ss WHERE ss.staff_id=? AND ss.date BETWEEN ? AND ?
         GROUP BY ss.date ORDER BY ss.date DESC LIMIT 31'
    );
    $dQ->execute([$topStaff['id'], $fromDate, $toDate]);
    $dailyRows = $dQ->fetchAll();
}

$branches  = $pdo->query('SELECT id, code FROM branches ORDER BY code')->fetchAll();

// CSV export
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="staff_report_' . $fromDate . '_to_' . $toDate . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Staff','Role','Branch','Sales Count','Total Sales','Total Profit','Avg Sale','Last Sale']);
    foreach ($staffPerf as $s) {
        fputcsv($out, [
            $s['full_name'], $s['role_name'], $s['branch_code'],
            $s['sales_count'], $s['total_sales'], $s['total_profit'],
            round((float)$s['avg_sale'], 2), $s['last_sale_date'] ?? '-'
        ]);
    }
    fclose($out);
    exit;
}

$pageTitle = 'Staff Report';
?>
<?php include __DIR__ . '/../../includes/header.php'; ?>
<script>window.BASE_URL = '<?= BASE_URL ?>';</script>

<div class="page-header">
  <h1 class="page-title"><i class="bi bi-person-badge me-2"></i>Staff Sales & Performance Report</h1>
  <div class="d-flex gap-2 no-print">
    <button onclick="window.print()" class="btn btn-outline-secondary btn-sm">
      <i class="bi bi-printer me-1"></i>Print / PDF
    </button>
    <a href="?from=<?= urlencode($fromDate) ?>&to=<?= urlencode($toDate) ?>&branch=<?= $fBranch ?>&export=csv"
       class="btn btn-outline-success btn-sm">
      <i class="bi bi-file-earmark-excel me-1"></i>Export CSV
    </a>
  </div>
</div>

<!-- Filters -->
<div class="erp-form-card mb-3 no-print">
  <form method="GET" class="row g-2 align-items-end">
    <div class="col-md-2">
      <label class="form-label">From</label>
      <input type="date" name="from" class="form-control form-control-sm" value="<?= $fromDate ?>">
    </div>
    <div class="col-md-2">
      <label class="form-label">To</label>
      <input type="date" name="to" class="form-control form-control-sm" value="<?= $toDate ?>">
    </div>
    <div class="col-md-2">
      <label class="form-label">Branch</label>
      <select name="branch" class="form-select form-select-sm">
        <option value="">All Branches</option>
        <?php foreach ($branches as $b): ?>
          <option value="<?= $b['id'] ?>" <?= $fBranch == $b['id'] ? 'selected' : '' ?>><?= clean($b['code']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-auto">
      <button type="submit" class="btn btn-primary btn-sm">Generate</button>
      <div class="d-inline-flex gap-1 ms-1">
        <a href="?from=<?= date('Y-m-d') ?>&to=<?= date('Y-m-d') ?>" class="btn btn-outline-secondary btn-sm">Today</a>
        <a href="?from=<?= date('Y-m-01') ?>&to=<?= date('Y-m-d') ?>" class="btn btn-outline-secondary btn-sm">Month</a>
        <a href="?from=<?= date('Y-01-01') ?>&to=<?= date('Y-m-d') ?>" class="btn btn-outline-secondary btn-sm">Year</a>
      </div>
    </div>
  </form>
</div>

<!-- Summary KPIs -->
<div class="row g-3 mb-4">
  <div class="col-6 col-md-3">
    <div class="kpi-card kpi-sales p-3">
      <div class="kpi-label">Total Sales</div>
      <div class="kpi-value"><?= money($totalSales) ?></div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="kpi-card kpi-profit p-3">
      <div class="kpi-label">Total Profit</div>
      <div class="kpi-value"><?= money($totalProfit) ?></div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="kpi-card p-3">
      <div class="kpi-label">Transactions</div>
      <div class="kpi-value"><?= number_format($totalCount) ?></div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="kpi-card p-3">
      <div class="kpi-label">Staff on Leaderboard</div>
      <div class="kpi-value"><?= count($staffPerf) ?></div>
    </div>
  </div>
</div>

<?php if (empty($staffPerf)): ?>
  <div class="alert alert-info">
    <i class="bi bi-info-circle me-2"></i>No staff sales data for the selected period.
    <br><small class="text-muted">Sales are tracked in <code>staff_sales</code> table from new transactions. Historical data may not be available.</small>
  </div>
<?php else: ?>

<!-- Staff Leaderboard -->
<div class="erp-table mb-4">
  <div class="p-3 border-bottom fw-bold"><i class="bi bi-trophy me-2"></i>Staff Leaderboard</div>
  <div class="table-responsive">
    <table class="table table-hover mb-0">
      <thead>
        <tr>
          <th>Rank</th>
          <th>Staff Name</th>
          <th>Role</th>
          <th>Branch</th>
          <th class="text-end">Transactions</th>
          <th class="text-end">Total Sales</th>
          <th class="text-end">Total Profit</th>
          <th class="text-end">Avg Sale</th>
          <th>Last Sale</th>
          <th>Performance</th>
        </tr>
      </thead>
      <tbody>
        <?php
          $maxSales = max(1, array_reduce($staffPerf, fn($c, $r) => max($c, (float)$r['total_sales']), 0));
          foreach ($staffPerf as $i => $s):
            $pct  = min(100, round(((float)$s['total_sales'] / $maxSales) * 100));
            $rank = match(true) {
              $i === 0 => '🥇',
              $i === 1 => '🥈',
              $i === 2 => '🥉',
              default  => (string)($i + 1)
            };
        ?>
        <tr>
          <td class="fw-bold"><?= $rank ?></td>
          <td class="fw-semibold"><?= clean($s['full_name']) ?></td>
          <td><span class="badge bg-secondary"><?= clean($s['role_name']) ?></span></td>
          <td><span class="badge bg-primary"><?= clean($s['branch_code']) ?></span></td>
          <td class="text-end"><?= number_format((int)$s['sales_count']) ?></td>
          <td class="text-end fw-semibold"><?= money((float)$s['total_sales']) ?></td>
          <td class="text-end <?= (float)$s['total_profit'] >= 0 ? 'text-success' : 'text-danger' ?>">
            <?= money((float)$s['total_profit']) ?>
          </td>
          <td class="text-end"><?= money((float)$s['avg_sale']) ?></td>
          <td><small class="text-muted"><?= $s['last_sale_date'] ? date('d M Y', strtotime($s['last_sale_date'])) : '-' ?></small></td>
          <td style="min-width:120px">
            <div class="progress" style="height:10px">
              <div class="progress-bar <?= $i === 0 ? 'bg-success' : ($i < 3 ? 'bg-primary' : 'bg-secondary') ?>"
                   style="width:<?= $pct ?>%"></div>
            </div>
            <small class="text-muted"><?= $pct ?>%</small>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<?php if ($topStaff && !empty($dailyRows)): ?>
<!-- Top Performer Daily Chart -->
<div class="row g-3 mb-4">
  <div class="col-lg-8">
    <div class="chart-card">
      <div class="chart-title">
        <i class="bi bi-bar-chart me-2"></i>Top Performer: <?= clean($topStaff['full_name']) ?> — Daily Sales
      </div>
      <canvas id="staffDailyChart" height="100"></canvas>
    </div>
  </div>
  <div class="col-lg-4">
    <div class="erp-table h-100">
      <div class="p-3 border-bottom fw-bold text-success"><i class="bi bi-star me-2"></i>Top Performer</div>
      <div class="p-3">
        <div class="fw-bold fs-5"><?= clean($topStaff['full_name']) ?></div>
        <div class="text-muted mb-2"><?= clean($topStaff['branch_code']) ?> — <?= clean($topStaff['role_name']) ?></div>
        <hr>
        <div class="d-flex justify-content-between mb-1">
          <span>Total Sales:</span>
          <strong class="text-success"><?= money((float)$topStaff['total_sales']) ?></strong>
        </div>
        <div class="d-flex justify-content-between mb-1">
          <span>Total Profit:</span>
          <strong><?= money((float)$topStaff['total_profit']) ?></strong>
        </div>
        <div class="d-flex justify-content-between mb-1">
          <span>Transactions:</span>
          <strong><?= number_format((int)$topStaff['sales_count']) ?></strong>
        </div>
        <div class="d-flex justify-content-between">
          <span>Avg Sale:</span>
          <strong><?= money((float)$topStaff['avg_sale']) ?></strong>
        </div>
      </div>
    </div>
  </div>
</div>
<script>
(function() {
  const labels  = <?= json_encode(array_reverse(array_column($dailyRows, 'date'))) ?>;
  const sales   = <?= json_encode(array_reverse(array_column($dailyRows, 'sales'))) ?>;
  const profits = <?= json_encode(array_reverse(array_column($dailyRows, 'profit'))) ?>;

  const ctx = document.getElementById('staffDailyChart').getContext('2d');
  new Chart(ctx, {
    type: 'bar',
    data: {
      labels: labels.map(d => new Date(d).toLocaleDateString('en-IN', {day:'2-digit', month:'short'})),
      datasets: [
        {
          label: 'Sales',
          data: sales.map(v => parseFloat(v)),
          backgroundColor: 'rgba(15,52,96,0.7)',
          borderRadius: 4
        },
        {
          label: 'Profit',
          data: profits.map(v => parseFloat(v)),
          backgroundColor: 'rgba(40,167,69,0.6)',
          borderRadius: 4
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
</script>
<?php endif; ?>

<?php endif; ?>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
