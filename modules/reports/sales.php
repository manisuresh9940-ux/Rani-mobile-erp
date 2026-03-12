<?php
/**
 * Rani Mobiles ERP — Sales Report
 */
require_once __DIR__ . '/../../config/auth.php';
require_auth();

$user      = current_user();
$branch_id = (int)$user['branch_id'];
$isAdmin   = is_admin();
$pdo       = db();

$fromDate = $_GET['from']   ?? date('Y-m-01');
$toDate   = $_GET['to']     ?? date('Y-m-d');
$fBranch  = $isAdmin ? (int)($_GET['branch'] ?? 0) : $branch_id;
$groupBy  = $_GET['group']  ?? 'day';  // day | week | month | brand

$where  = ['s.sale_date BETWEEN ? AND ?'];
$params = [$fromDate, $toDate];
if ($fBranch) { $where[] = 's.branch_id=?'; $params[] = $fBranch; }
elseif (!$isAdmin) { $where[] = 's.branch_id=?'; $params[] = $branch_id; }

// Summary
$summary = $pdo->prepare(
    'SELECT COUNT(*) AS cnt,
            COALESCE(SUM(s.total),0) AS total_sales,
            COALESCE(SUM(s.subtotal),0) AS subtotal,
            COALESCE(SUM(s.gst_amount),0) AS gst_total,
            COALESCE(SUM(s.paid_cash),0) AS cash,
            COALESCE(SUM(s.paid_upi),0) AS upi,
            COALESCE(SUM(s.paid_card),0) AS card,
            COALESCE(SUM(s.paid_credit),0) AS credit
     FROM sales s WHERE ' . implode(' AND ', $where)
);
$summary->execute($params);
$summary = $summary->fetch();

// Profit calculation
$profitQ = $pdo->prepare(
    'SELECT COALESCE(SUM(si.total - (p.purchase_cost * si.qty)),0) AS profit
     FROM sale_items si
     JOIN sales s ON s.id=si.sale_id
     JOIN products p ON p.id=si.product_id
     WHERE ' . implode(' AND ', $where)
);
$profitQ->execute($params);
$totalProfit = (float)$profitQ->fetchColumn();

// Chart data: daily sales
$chartData = $pdo->prepare(
    'SELECT s.sale_date AS label,
            COALESCE(SUM(s.total),0) AS value
     FROM sales s WHERE ' . implode(' AND ', $where) . ' GROUP BY s.sale_date ORDER BY s.sale_date'
);
$chartData->execute($params);
$chartData = $chartData->fetchAll();

// Top products
$topProducts = $pdo->prepare(
    'SELECT p.name, br.name AS brand, SUM(si.qty) AS qty_sold,
            SUM(si.total) AS revenue,
            SUM(si.total - p.purchase_cost * si.qty) AS profit
     FROM sale_items si
     JOIN sales s ON s.id=si.sale_id
     JOIN products p ON p.id=si.product_id
     JOIN brands br ON br.id=p.brand_id
     WHERE ' . implode(' AND ', $where) . '
     GROUP BY si.product_id ORDER BY revenue DESC LIMIT 10'
);
$topProducts->execute($params);
$topProducts = $topProducts->fetchAll();

// Top brands
$topBrands = $pdo->prepare(
    'SELECT br.name, SUM(si.qty) AS qty_sold, SUM(si.total) AS revenue
     FROM sale_items si
     JOIN sales s ON s.id=si.sale_id
     JOIN products p ON p.id=si.product_id
     JOIN brands br ON br.id=p.brand_id
     WHERE ' . implode(' AND ', $where) . '
     GROUP BY br.id ORDER BY revenue DESC LIMIT 8'
);
$topBrands->execute($params);
$topBrands = $topBrands->fetchAll();

$branches = $pdo->query('SELECT id, code FROM branches ORDER BY code')->fetchAll();

// CSV export
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    // Full sales rows for CSV
    $expWhere  = $where;
    $expParams = $params;
    $exportRows = $pdo->prepare(
        'SELECT s.invoice_no, s.sale_date, b.code AS branch,
                s.customer_name, s.total, s.paid_cash, s.paid_upi, s.paid_card, s.paid_credit,
                s.gst_amount, s.notes
         FROM sales s JOIN branches b ON b.id=s.branch_id
         WHERE ' . implode(' AND ', $expWhere) . ' ORDER BY s.sale_date DESC'
    );
    $exportRows->execute($expParams);
    $rows = $exportRows->fetchAll();

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="sales_report_' . $fromDate . '_to_' . $toDate . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Invoice No','Date','Branch','Customer','Total','Cash','UPI','Card','Credit','GST','Notes']);
    foreach ($rows as $r) {
        fputcsv($out, [
            $r['invoice_no'], $r['sale_date'], $r['branch'], $r['customer_name'] ?: 'Walk-in',
            $r['total'], $r['paid_cash'], $r['paid_upi'], $r['paid_card'], $r['paid_credit'],
            $r['gst_amount'], $r['notes']
        ]);
    }
    fclose($out);
    exit;
}

$pageTitle = 'Sales Report';
?>
<?php include __DIR__ . '/../../includes/header.php'; ?>
<script>window.BASE_URL = '<?= BASE_URL ?>';</script>

<div class="page-header">
  <h1 class="page-title"><i class="bi bi-graph-up me-2"></i>Sales Report</h1>
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
    <div class="col-auto">
      <button type="submit" class="btn btn-primary btn-sm">Generate</button>
    </div>
    <!-- Quick date ranges -->
    <div class="col-auto d-flex gap-1 flex-wrap">
      <a href="?from=<?= date('Y-m-d') ?>&to=<?= date('Y-m-d') ?>" class="btn btn-outline-secondary btn-sm">Today</a>
      <a href="?from=<?= date('Y-m-01') ?>&to=<?= date('Y-m-d') ?>" class="btn btn-outline-secondary btn-sm">This Month</a>
      <a href="?from=<?= date('Y-m-d', strtotime('-7 days')) ?>&to=<?= date('Y-m-d') ?>" class="btn btn-outline-secondary btn-sm">7 Days</a>
      <a href="?from=<?= date('Y-01-01') ?>&to=<?= date('Y-m-d') ?>" class="btn btn-outline-secondary btn-sm">This Year</a>
    </div>
  </form>
</div>

<!-- Summary KPIs -->
<div class="row g-3 mb-4">
  <div class="col-6 col-md-2">
    <div class="kpi-card kpi-sales p-3">
      <div class="kpi-label">Total Sales</div>
      <div class="kpi-value"><?= money((float)$summary['total_sales']) ?></div>
    </div>
  </div>
  <div class="col-6 col-md-2">
    <div class="kpi-card kpi-profit p-3">
      <div class="kpi-label">Profit</div>
      <div class="kpi-value"><?= money($totalProfit) ?></div>
    </div>
  </div>
  <div class="col-6 col-md-2">
    <div class="kpi-card p-3">
      <div class="kpi-label">Invoices</div>
      <div class="kpi-value"><?= number_format((int)$summary['cnt']) ?></div>
    </div>
  </div>
  <div class="col-6 col-md-2">
    <div class="kpi-card p-3">
      <div class="kpi-label">GST Collected</div>
      <div class="kpi-value"><?= money((float)$summary['gst_total']) ?></div>
    </div>
  </div>
  <div class="col-6 col-md-2">
    <div class="kpi-card kpi-cash p-3">
      <div class="kpi-label">Cash</div>
      <div class="kpi-value"><?= money((float)$summary['cash']) ?></div>
    </div>
  </div>
  <div class="col-6 col-md-2">
    <div class="kpi-card p-3 border-danger">
      <div class="kpi-label">Credit</div>
      <div class="kpi-value text-danger"><?= money((float)$summary['credit']) ?></div>
    </div>
  </div>
</div>

<div class="row g-3 mb-4">
  <!-- Sales trend chart -->
  <div class="col-lg-8">
    <div class="chart-card">
      <div class="chart-title">Daily Sales Trend</div>
      <canvas id="salesTrendChart" height="100"></canvas>
    </div>
  </div>
  <!-- Top brands pie -->
  <div class="col-lg-4">
    <div class="chart-card">
      <div class="chart-title">Top Brands (Revenue)</div>
      <canvas id="brandChart" height="140"></canvas>
    </div>
  </div>
</div>

<!-- Top products -->
<div class="erp-table">
  <div class="p-3 border-bottom fw-bold"><i class="bi bi-trophy me-2"></i>Top Selling Products</div>
  <div class="table-responsive">
    <table class="table table-hover mb-0">
      <thead>
        <tr><th>#</th><th>Product</th><th>Brand</th>
            <th class="text-end">Qty Sold</th><th class="text-end">Revenue</th><th class="text-end">Profit</th></tr>
      </thead>
      <tbody>
        <?php if (empty($topProducts)): ?>
          <tr><td colspan="6" class="text-center text-muted py-3">No data</td></tr>
        <?php else: ?>
          <?php foreach ($topProducts as $i => $p): ?>
            <tr>
              <td><?= $i===0?'🥇':($i===1?'🥈':($i===2?'🥉':$i+1)) ?></td>
              <td><?= clean($p['name']) ?></td>
              <td><?= clean($p['brand']) ?></td>
              <td class="text-end"><?= number_format((int)$p['qty_sold']) ?></td>
              <td class="text-end fw-semibold"><?= money((float)$p['revenue']) ?></td>
              <td class="text-end <?= $p['profit']>=0?'text-success':'text-danger' ?>">
                <?= money((float)$p['profit']) ?>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<script>
(function() {
  const labels = <?= json_encode(array_column($chartData, 'label')) ?>;
  const values = <?= json_encode(array_column($chartData, 'value')) ?>;

  const salesCtx = document.getElementById('salesTrendChart').getContext('2d');
  new Chart(salesCtx, {
    type: 'bar',
    data: {
      labels: labels.map(d => new Date(d).toLocaleDateString('en-IN', {day:'2-digit', month:'short'})),
      datasets: [{
        label: 'Sales (₹)',
        data: values.map(v => parseFloat(v)),
        backgroundColor: 'rgba(15,52,96,0.75)',
        borderColor: '#0f3460',
        borderWidth: 1,
        borderRadius: 4
      }]
    },
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
  });

  <?php if (!empty($topBrands)): ?>
  const brandCtx = document.getElementById('brandChart').getContext('2d');
  new Chart(brandCtx, {
    type: 'pie',
    data: {
      labels: <?= json_encode(array_column($topBrands, 'name')) ?>,
      datasets: [{
        data: <?= json_encode(array_column($topBrands, 'revenue')) ?>,
        backgroundColor: ['#0f3460','#e94560','#28a745','#ffc107','#17a2b8','#6f42c1','#fd7e14','#20c997','#dc3545','#6c757d']
      }]
    },
    options: {
      responsive: true,
      plugins: {
        legend: { position: 'bottom', labels: { font: { size: 11 } } },
        tooltip: { callbacks: { label: ctx => ctx.label + ': ₹' + parseFloat(ctx.parsed).toLocaleString('en-IN') } }
      }
    }
  });
  <?php endif; ?>
})();
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
