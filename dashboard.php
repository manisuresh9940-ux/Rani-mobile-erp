<?php
/**
 * Rani Mobiles ERP — Dashboard
 */
require_once __DIR__ . '/config/auth.php';
require_auth();

$user      = current_user();
$branch_id = $user['branch_id'];
$isAdmin   = is_admin();
$pdo       = db();
$today     = date('Y-m-d');

// ── KPI Queries ────────────────────────────────────────────────
// Today Sales
$q = $isAdmin
   ? $pdo->prepare("SELECT COALESCE(SUM(total),0) FROM sales WHERE sale_date=?")
   : $pdo->prepare("SELECT COALESCE(SUM(total),0) FROM sales WHERE sale_date=? AND branch_id=?");
$q->execute($isAdmin ? [$today] : [$today, $branch_id]);
$todaySales = (float)$q->fetchColumn();

// Today Purchase
$q = $isAdmin
   ? $pdo->prepare("SELECT COALESCE(SUM(total),0) FROM purchases WHERE purchase_date=?")
   : $pdo->prepare("SELECT COALESCE(SUM(total),0) FROM purchases WHERE purchase_date=? AND branch_id=?");
$q->execute($isAdmin ? [$today] : [$today, $branch_id]);
$todayPurchase = (float)$q->fetchColumn();

// Approximate profit (sales - purchase cost of items sold today)
$q = $isAdmin
   ? $pdo->prepare("SELECT COALESCE(SUM(si.total - (p.purchase_cost * si.qty)),0)
                    FROM sale_items si JOIN sales s ON s.id=si.sale_id JOIN products p ON p.id=si.product_id
                    WHERE s.sale_date=?")
   : $pdo->prepare("SELECT COALESCE(SUM(si.total - (p.purchase_cost * si.qty)),0)
                    FROM sale_items si JOIN sales s ON s.id=si.sale_id JOIN products p ON p.id=si.product_id
                    WHERE s.sale_date=? AND s.branch_id=?");
$q->execute($isAdmin ? [$today] : [$today, $branch_id]);
$todayProfit = (float)$q->fetchColumn();

// Cash balance (sum of cash sales - sum of expenses today)
$q = $isAdmin
   ? $pdo->prepare("SELECT COALESCE(SUM(paid_cash),0) FROM sales WHERE sale_date=?")
   : $pdo->prepare("SELECT COALESCE(SUM(paid_cash),0) FROM sales WHERE sale_date=? AND branch_id=?");
$q->execute($isAdmin ? [$today] : [$today, $branch_id]);
$cashIn = (float)$q->fetchColumn();

$q = $isAdmin
   ? $pdo->prepare("SELECT COALESCE(SUM(amount),0) FROM expenses WHERE expense_date=?")
   : $pdo->prepare("SELECT COALESCE(SUM(amount),0) FROM expenses WHERE expense_date=? AND branch_id=?");
$q->execute($isAdmin ? [$today] : [$today, $branch_id]);
$cashOut = (float)$q->fetchColumn();
$cashBalance = $cashIn - $cashOut;

// Stock value
$q = $isAdmin
   ? $pdo->prepare("SELECT COALESCE(SUM(s.qty * p.purchase_cost),0) FROM stock s JOIN products p ON p.id=s.product_id")
   : $pdo->prepare("SELECT COALESCE(SUM(s.qty * p.purchase_cost),0) FROM stock s JOIN products p ON p.id=s.product_id WHERE s.branch_id=?");
$q->execute($isAdmin ? [] : [$branch_id]);
$stockValue = (float)$q->fetchColumn();

// Low stock items
$q = $isAdmin
   ? $pdo->prepare("SELECT COUNT(*) FROM stock s JOIN products p ON p.id=s.product_id WHERE s.qty <= p.min_stock AND s.qty > 0")
   : $pdo->prepare("SELECT COUNT(*) FROM stock s JOIN products p ON p.id=s.product_id WHERE s.branch_id=? AND s.qty <= p.min_stock AND s.qty > 0");
$q->execute($isAdmin ? [] : [$branch_id]);
$lowStockCount = (int)$q->fetchColumn();

// Pending payments (credit sales)
$q = $isAdmin
   ? $pdo->prepare("SELECT COALESCE(SUM(paid_credit),0) FROM sales")
   : $pdo->prepare("SELECT COALESCE(SUM(paid_credit),0) FROM sales WHERE branch_id=?");
$q->execute($isAdmin ? [] : [$branch_id]);
$pendingPayments = (float)$q->fetchColumn();

// Internal Branch Profit (from completed transfers where this branch is the sender)
$q = $isAdmin
   ? $pdo->prepare("SELECT COALESCE(SUM(profit),0) FROM branch_transfers WHERE status='received'")
   : $pdo->prepare("SELECT COALESCE(SUM(profit),0) FROM branch_transfers WHERE from_branch_id=? AND status='received'");
$q->execute($isAdmin ? [] : [$branch_id]);
$internalProfit = (float)$q->fetchColumn();

// ── Smart Business Insights ────────────────────────────────────
// Top selling brand this month
$monthStart = date('Y-m-01');
$q = $pdo->prepare(
    "SELECT br.name, COALESCE(SUM(si.qty),0) AS qty_sold
     FROM sale_items si
     JOIN sales s ON s.id=si.sale_id
     JOIN products p ON p.id=si.product_id
     JOIN brands br ON br.id=p.brand_id
     WHERE s.sale_date >= ?
     GROUP BY br.id ORDER BY qty_sold DESC LIMIT 1"
);
$q->execute([$monthStart]);
$topBrand = $q->fetch();

// Best performing branch this month (admin only)
$topBranchInsight = null;
if ($isAdmin) {
    $q = $pdo->prepare(
        "SELECT b.code, COALESCE(SUM(s.total),0) AS total
         FROM branches b
         LEFT JOIN sales s ON s.branch_id=b.id AND s.sale_date >= ?
         GROUP BY b.id ORDER BY total DESC LIMIT 1"
    );
    $q->execute([$monthStart]);
    $topBranchInsight = $q->fetch();
}

// Highest profit product this month
$q = $pdo->prepare(
    "SELECT p.name, br.name AS brand,
            COALESCE(SUM(si.total - p.purchase_cost * si.qty),0) AS profit
     FROM sale_items si
     JOIN sales s ON s.id=si.sale_id
     JOIN products p ON p.id=si.product_id
     JOIN brands br ON br.id=p.brand_id
     WHERE s.sale_date >= ?
     GROUP BY p.id ORDER BY profit DESC LIMIT 1"
);
$q->execute([$monthStart]);
$topProfitProduct = $q->fetch();

// ── Charts data ────────────────────────────────────────────────
// Last 7 days sales
$days = []; $daySales = [];
for ($i = 6; $i >= 0; $i--) {
    $d = date('Y-m-d', strtotime("-$i days"));
    $days[] = date('d M', strtotime($d));
    if ($isAdmin) {
        $q = $pdo->prepare("SELECT COALESCE(SUM(total),0) FROM sales WHERE sale_date=?");
        $q->execute([$d]);
    } else {
        $q = $pdo->prepare("SELECT COALESCE(SUM(total),0) FROM sales WHERE sale_date=? AND branch_id=?");
        $q->execute([$d, $branch_id]);
    }
    $daySales[] = round((float)$q->fetchColumn(), 2);
}

// Branch comparison (admin only)
$branchSales = [];
if ($isAdmin) {
    $branches = $pdo->query("SELECT b.code, COALESCE(SUM(s.total),0) AS total
                              FROM branches b LEFT JOIN sales s ON s.branch_id=b.id AND s.sale_date=CURDATE()
                              GROUP BY b.id")->fetchAll();
    foreach ($branches as $b) {
        $branchSales['labels'][] = $b['code'];
        $branchSales['data'][]   = round((float)$b['total'], 2);
    }
}

// Low stock alerts
if ($isAdmin) {
    $lowItems = $pdo->query(
        "SELECT p.name, p.model, br.name AS brand, s.qty, p.min_stock
         FROM stock s JOIN products p ON p.id=s.product_id JOIN brands br ON br.id=p.brand_id
         WHERE s.qty <= p.min_stock ORDER BY s.qty ASC LIMIT 8"
    )->fetchAll();
} else {
    $q = $pdo->prepare(
        "SELECT p.name, p.model, br.name AS brand, s.qty, p.min_stock
         FROM stock s JOIN products p ON p.id=s.product_id JOIN brands br ON br.id=p.brand_id
         WHERE s.branch_id=? AND s.qty <= p.min_stock ORDER BY s.qty ASC LIMIT 8"
    );
    $q->execute([$branch_id]);
    $lowItems = $q->fetchAll();
}

// Recent sales
if ($isAdmin) {
    $recentSales = $pdo->query(
        "SELECT s.invoice_no, s.customer_name, s.total, s.sale_date, b.code AS branch
         FROM sales s JOIN branches b ON b.id=s.branch_id
         ORDER BY s.id DESC LIMIT 8"
    )->fetchAll();
} else {
    $q = $pdo->prepare(
        "SELECT s.invoice_no, s.customer_name, s.total, s.sale_date, b.code AS branch
         FROM sales s JOIN branches b ON b.id=s.branch_id
         WHERE s.branch_id=? ORDER BY s.id DESC LIMIT 8"
    );
    $q->execute([$branch_id]);
    $recentSales = $q->fetchAll();
}

// Dead stock (no sales in 45 days)
$deadStock = $pdo->query(
    "SELECT p.name, p.model, br.name AS brand,
            DATEDIFF(CURDATE(), MAX(s.sale_date)) AS days_no_sale
     FROM products p
     JOIN brands br ON br.id=p.brand_id
     LEFT JOIN sale_items si ON si.product_id=p.id
     LEFT JOIN sales s ON s.id=si.sale_id
     WHERE p.is_active=1
     GROUP BY p.id
     HAVING days_no_sale > 45 OR days_no_sale IS NULL
     LIMIT 5"
)->fetchAll();

$pageTitle = 'Dashboard';
?>
<?php include __DIR__ . '/includes/header.php'; ?>
<script>window.BASE_URL = '<?= BASE_URL ?>';</script>

<!-- Page heading -->
<div class="page-header">
  <div>
    <h1 class="page-title"><i class="bi bi-speedometer2 me-2"></i>Business Control Center</h1>
    <p class="page-subtitle">
      <?= date('l, d F Y') ?>
      &nbsp;·&nbsp;
      <?= clean($user['branch']) ?> Branch
      <?= $isAdmin ? '&nbsp;·&nbsp;<span class="badge bg-danger">Admin View</span>' : '' ?>
    </p>
  </div>
  <div class="d-flex gap-2">
    <a href="<?= BASE_URL ?>/modules/sales/pos.php" class="btn btn-primary btn-sm">
      <i class="bi bi-plus-circle me-1"></i>New Sale
    </a>
    <a href="<?= BASE_URL ?>/modules/reports/sales.php" class="btn btn-outline-secondary btn-sm">
      <i class="bi bi-bar-chart me-1"></i>Reports
    </a>
  </div>
</div>

<!-- KPI Cards -->
<div class="row g-3 mb-4">
  <div class="col-6 col-md-3 col-xl-2 fade-in-up">
    <div class="kpi-card kpi-sales">
      <div class="kpi-icon"><i class="bi bi-bag-check"></i></div>
      <div class="kpi-value"><?= money($todaySales) ?></div>
      <div class="kpi-label">Today Sales</div>
    </div>
  </div>
  <div class="col-6 col-md-3 col-xl-2 fade-in-up">
    <div class="kpi-card kpi-purchase">
      <div class="kpi-icon"><i class="bi bi-truck"></i></div>
      <div class="kpi-value"><?= money($todayPurchase) ?></div>
      <div class="kpi-label">Today Purchase</div>
    </div>
  </div>
  <div class="col-6 col-md-3 col-xl-2 fade-in-up">
    <div class="kpi-card kpi-profit">
      <div class="kpi-icon"><i class="bi bi-graph-up-arrow"></i></div>
      <div class="kpi-value"><?= money($todayProfit) ?></div>
      <div class="kpi-label">Today Profit</div>
    </div>
  </div>
  <div class="col-6 col-md-3 col-xl-2 fade-in-up">
    <div class="kpi-card kpi-cash">
      <div class="kpi-icon"><i class="bi bi-cash-stack"></i></div>
      <div class="kpi-value"><?= money($cashBalance) ?></div>
      <div class="kpi-label">Cash Balance</div>
    </div>
  </div>
  <div class="col-6 col-md-3 col-xl-2 fade-in-up">
    <div class="kpi-card kpi-stock">
      <div class="kpi-icon"><i class="bi bi-boxes"></i></div>
      <div class="kpi-value"><?= money($stockValue) ?></div>
      <div class="kpi-label">Stock Value</div>
    </div>
  </div>
  <div class="col-6 col-md-3 col-xl-2 fade-in-up">
    <div class="kpi-card kpi-low">
      <div class="kpi-icon"><i class="bi bi-exclamation-triangle"></i></div>
      <div class="kpi-value"><?= $lowStockCount ?></div>
      <div class="kpi-label">Low Stock Items</div>
    </div>
  </div>
  <div class="col-6 col-md-3 col-xl-2 fade-in-up">
    <div class="kpi-card" style="background:linear-gradient(135deg,#e94560,#c0392b)">
      <div class="kpi-icon"><i class="bi bi-clock-history"></i></div>
      <div class="kpi-value" style="color:#fff"><?= money($pendingPayments) ?></div>
      <div class="kpi-label" style="color:rgba(255,255,255,.8)">Pending Payments</div>
    </div>
  </div>
  <div class="col-6 col-md-3 col-xl-2 fade-in-up">
    <div class="kpi-card" style="background:linear-gradient(135deg,#11998e,#38ef7d)">
      <div class="kpi-icon"><i class="bi bi-arrow-left-right"></i></div>
      <div class="kpi-value" style="color:#fff"><?= money($internalProfit) ?></div>
      <div class="kpi-label" style="color:rgba(255,255,255,.8)">Internal Profit</div>
    </div>
  </div>
</div>

<!-- Charts Row -->
<div class="row g-3 mb-4">
  <!-- Daily Sales Chart -->
  <div class="col-lg-8">
    <div class="chart-card">
      <div class="chart-title"><i class="bi bi-bar-chart-line me-2"></i>Sales — Last 7 Days</div>
      <div id="dailySalesChart"></div>
    </div>
  </div>
  <!-- Branch Comparison -->
  <div class="col-lg-4">
    <div class="chart-card">
      <div class="chart-title"><i class="bi bi-shop me-2"></i>Today Branch Comparison</div>
      <div id="branchChart"></div>
    </div>
  </div>
</div>

<!-- Alerts + Recent Sales -->
<div class="row g-3 mb-4">
  <!-- Low Stock Alerts -->
  <div class="col-lg-4">
    <div class="chart-card h-100">
      <div class="chart-title text-danger"><i class="bi bi-exclamation-triangle me-2"></i>Low / Out of Stock</div>
      <?php if (empty($lowItems)): ?>
        <div class="text-center text-muted py-3">
          <i class="bi bi-check-circle text-success fs-2"></i><br>All stock levels OK
        </div>
      <?php else: ?>
        <?php foreach ($lowItems as $item): ?>
          <div class="alert-item <?= $item['qty'] <= 0 ? 'danger' : '' ?>">
            <div>
              <i class="bi bi-phone text-warning"></i>
            </div>
            <div>
              <div class="fw-semibold"><?= clean($item['brand']) ?> <?= clean($item['name']) ?></div>
              <div class="text-muted">Qty: <strong><?= $item['qty'] ?></strong> | Min: <?= $item['min_stock'] ?></div>
            </div>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>

      <?php if (!empty($deadStock)): ?>
        <div class="chart-title text-warning mt-3"><i class="bi bi-hourglass me-2"></i>Dead Stock (45+ days)</div>
        <?php foreach ($deadStock as $ds): ?>
          <div class="alert-item">
            <div><i class="bi bi-phone text-secondary"></i></div>
            <div>
              <div class="fw-semibold"><?= clean($ds['brand']) ?> <?= clean($ds['name']) ?></div>
              <div class="text-muted">No sale for <strong><?= (int)($ds['days_no_sale'] ?? 99) ?></strong> days</div>
            </div>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </div>

  <!-- Recent Sales -->
  <div class="col-lg-8">
    <div class="erp-table">
      <div class="d-flex align-items-center justify-content-between p-3 border-bottom">
        <strong><i class="bi bi-receipt me-2"></i>Recent Sales</strong>
        <a href="<?= BASE_URL ?>/modules/sales/list.php" class="btn btn-outline-primary btn-sm">View All</a>
      </div>
      <table class="table table-hover mb-0">
        <thead>
          <tr>
            <th>Invoice</th>
            <th>Customer</th>
            <th>Branch</th>
            <th>Date</th>
            <th class="text-end">Amount</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($recentSales)): ?>
            <tr><td colspan="5" class="text-center text-muted py-3">No sales today</td></tr>
          <?php else: ?>
            <?php foreach ($recentSales as $s): ?>
              <tr>
                <td><code><?= clean($s['invoice_no']) ?></code></td>
                <td><?= clean($s['customer_name'] ?: 'Walk-in') ?></td>
                <td><span class="badge bg-primary"><?= clean($s['branch']) ?></span></td>
                <td><?= fmt_date($s['sale_date']) ?></td>
                <td class="text-end fw-semibold"><?= money((float)$s['total']) ?></td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- Smart Business Insights -->
<div class="row g-3 mb-4">
  <div class="col-12">
    <div class="chart-card">
      <div class="chart-title"><i class="bi bi-lightbulb me-2 text-warning"></i>Smart Business Insights — This Month</div>
      <div class="row g-3 mt-1">
        <div class="col-md-4">
          <div class="p-3 rounded-3" style="background:#f0f9ff;border-left:4px solid #0f3460">
            <div class="small text-muted mb-1"><i class="bi bi-award me-1"></i>Top Selling Brand</div>
            <?php if ($topBrand): ?>
              <div class="fw-bold fs-5"><?= clean($topBrand['name']) ?></div>
              <div class="text-success small"><?= number_format((int)$topBrand['qty_sold']) ?> units sold this month</div>
            <?php else: ?>
              <div class="text-muted small">No sales data yet</div>
            <?php endif; ?>
          </div>
        </div>
        <div class="col-md-4">
          <div class="p-3 rounded-3" style="background:#f0fff4;border-left:4px solid #28a745">
            <div class="small text-muted mb-1"><i class="bi bi-shop me-1"></i>Best Performing Branch</div>
            <?php if ($topBranchInsight): ?>
              <div class="fw-bold fs-5"><?= clean($topBranchInsight['code']) ?></div>
              <div class="text-success small"><?= money((float)$topBranchInsight['total']) ?> sales this month</div>
            <?php elseif (!$isAdmin): ?>
              <div class="text-muted small">Admin view required</div>
            <?php else: ?>
              <div class="text-muted small">No sales data yet</div>
            <?php endif; ?>
          </div>
        </div>
        <div class="col-md-4">
          <div class="p-3 rounded-3" style="background:#fffbf0;border-left:4px solid #ffc107">
            <div class="small text-muted mb-1"><i class="bi bi-trophy me-1"></i>Highest Profit Product</div>
            <?php if ($topProfitProduct): ?>
              <div class="fw-bold"><?= clean($topProfitProduct['brand']) ?> <?= clean($topProfitProduct['name']) ?></div>
              <div class="text-success small"><?= money((float)$topProfitProduct['profit']) ?> profit this month</div>
            <?php else: ?>
              <div class="text-muted small">No sales data yet</div>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
(function() {
  // ── Daily Sales Chart ──────────────────────────────────────
  const salesOpts = {
    chart: { type: 'area', height: 240, toolbar: { show: false }, animations: { enabled: true } },
    series: [{ name: 'Sales', data: <?= json_encode($daySales) ?> }],
    xaxis: { categories: <?= json_encode($days) ?> },
    yaxis: { labels: { formatter: v => '₹' + v.toLocaleString('en-IN') } },
    dataLabels: { enabled: false },
    stroke:     { curve: 'smooth', width: 3 },
    fill: {
      type: 'gradient',
      gradient: { shadeIntensity: 1, opacityFrom: 0.4, opacityTo: 0.05 }
    },
    colors: ['#0f3460'],
    tooltip: { y: { formatter: v => '₹' + v.toLocaleString('en-IN') } },
    grid: { borderColor: '#f0f0f0' }
  };
  new ApexCharts(document.getElementById('dailySalesChart'), salesOpts).render();

  // ── Branch Comparison Chart ────────────────────────────────
  <?php if ($isAdmin && !empty($branchSales)): ?>
  const branchOpts = {
    chart: { type: 'donut', height: 240, toolbar: { show: false } },
    series: <?= json_encode($branchSales['data'] ?? []) ?>,
    labels: <?= json_encode($branchSales['labels'] ?? []) ?>,
    legend: { position: 'bottom' },
    colors: ['#0f3460','#e94560','#28a745','#ffc107','#17a2b8'],
    tooltip: { y: { formatter: v => '₹' + v.toLocaleString('en-IN') } },
    plotOptions: { pie: { donut: { labels: { show: true, total: { show: true, label: 'Total', formatter: (w) => '₹' + w.globals.seriesTotals.reduce((a,b)=>a+b,0).toLocaleString('en-IN') } } } } }
  };
  new ApexCharts(document.getElementById('branchChart'), branchOpts).render();
  <?php else: ?>
  document.getElementById('branchChart').innerHTML = '<div class="text-center text-muted py-5">Branch comparison available for Admin only</div>';
  <?php endif; ?>
})();
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
