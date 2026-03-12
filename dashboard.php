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

// Last 6 months sales
$months = []; $monthSales = [];
for ($i = 5; $i >= 0; $i--) {
    $m = date('Y-m', strtotime("-$i months"));
    $months[] = date('M Y', strtotime("$m-01"));
    if ($isAdmin) {
        $q = $pdo->prepare("SELECT COALESCE(SUM(total),0) FROM sales WHERE DATE_FORMAT(sale_date,'%Y-%m')=?");
        $q->execute([$m]);
    } else {
        $q = $pdo->prepare("SELECT COALESCE(SUM(total),0) FROM sales WHERE DATE_FORMAT(sale_date,'%Y-%m')=? AND branch_id=?");
        $q->execute([$m, $branch_id]);
    }
    $monthSales[] = round((float)$q->fetchColumn(), 2);
}

// Top selling models (this month)
$topModelsQ = $isAdmin
    ? $pdo->prepare("SELECT p.name, br.name AS brand, SUM(si.qty) AS qty_sold
                     FROM sale_items si JOIN sales s ON s.id=si.sale_id
                     JOIN products p ON p.id=si.product_id JOIN brands br ON br.id=p.brand_id
                     WHERE DATE_FORMAT(s.sale_date,'%Y-%m')=DATE_FORMAT(CURDATE(),'%Y-%m')
                     GROUP BY si.product_id ORDER BY qty_sold DESC LIMIT 5")
    : $pdo->prepare("SELECT p.name, br.name AS brand, SUM(si.qty) AS qty_sold
                     FROM sale_items si JOIN sales s ON s.id=si.sale_id
                     JOIN products p ON p.id=si.product_id JOIN brands br ON br.id=p.brand_id
                     WHERE DATE_FORMAT(s.sale_date,'%Y-%m')=DATE_FORMAT(CURDATE(),'%Y-%m')
                       AND s.branch_id=?
                     GROUP BY si.product_id ORDER BY qty_sold DESC LIMIT 5");
$topModelsQ->execute($isAdmin ? [] : [$branch_id]);
$topModels = $topModelsQ->fetchAll();

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
  <div class="col-6 col-md-4 col-xl-2 fade-in-up">
    <div class="kpi-card kpi-sales">
      <div class="kpi-icon"><i class="bi bi-bag-check"></i></div>
      <div class="kpi-value"><?= money($todaySales) ?></div>
      <div class="kpi-label">Today Sales</div>
    </div>
  </div>
  <div class="col-6 col-md-4 col-xl-2 fade-in-up">
    <div class="kpi-card kpi-purchase">
      <div class="kpi-icon"><i class="bi bi-truck"></i></div>
      <div class="kpi-value"><?= money($todayPurchase) ?></div>
      <div class="kpi-label">Today Purchase</div>
    </div>
  </div>
  <div class="col-6 col-md-4 col-xl-2 fade-in-up">
    <div class="kpi-card kpi-profit">
      <div class="kpi-icon"><i class="bi bi-graph-up-arrow"></i></div>
      <div class="kpi-value"><?= money($todayProfit) ?></div>
      <div class="kpi-label">Today Profit</div>
    </div>
  </div>
  <div class="col-6 col-md-4 col-xl-2 fade-in-up">
    <div class="kpi-card kpi-cash">
      <div class="kpi-icon"><i class="bi bi-cash-stack"></i></div>
      <div class="kpi-value"><?= money($cashBalance) ?></div>
      <div class="kpi-label">Cash Balance</div>
    </div>
  </div>
  <div class="col-6 col-md-4 col-xl-2 fade-in-up">
    <div class="kpi-card kpi-stock">
      <div class="kpi-icon"><i class="bi bi-boxes"></i></div>
      <div class="kpi-value"><?= money($stockValue) ?></div>
      <div class="kpi-label">Stock Value</div>
    </div>
  </div>
  <div class="col-6 col-md-4 col-xl-2 fade-in-up">
    <div class="kpi-card kpi-low">
      <div class="kpi-icon"><i class="bi bi-exclamation-triangle"></i></div>
      <div class="kpi-value"><?= $lowStockCount ?></div>
      <div class="kpi-label">Low Stock Items</div>
    </div>
  </div>
</div>

<!-- Charts Row -->
<div class="row g-3 mb-4">
  <!-- Daily Sales Chart -->
  <div class="col-lg-8">
    <div class="chart-card">
      <div class="chart-title"><i class="bi bi-bar-chart-line me-2"></i>Sales — Last 7 Days</div>
      <canvas id="dailySalesChart" height="100"></canvas>
    </div>
  </div>
  <!-- Branch Comparison -->
  <div class="col-lg-4">
    <div class="chart-card">
      <div class="chart-title"><i class="bi bi-shop me-2"></i>Today Branch Comparison</div>
      <canvas id="branchChart" height="140"></canvas>
    </div>
  </div>
</div>

<!-- Monthly Sales + Top Models Row -->
<div class="row g-3 mb-4">
  <!-- Monthly Sales Chart -->
  <div class="col-lg-7">
    <div class="chart-card">
      <div class="chart-title"><i class="bi bi-graph-up me-2"></i>Monthly Sales — Last 6 Months</div>
      <canvas id="monthlySalesChart" height="110"></canvas>
    </div>
  </div>
  <!-- Top Selling Models -->
  <div class="col-lg-5">
    <div class="erp-table h-100">
      <div class="p-3 border-bottom fw-bold text-success"><i class="bi bi-trophy me-2"></i>Top Selling Models (This Month)</div>
      <?php if (empty($topModels)): ?>
        <div class="text-center text-muted py-3">No sales this month</div>
      <?php else: ?>
        <table class="table table-sm mb-0">
          <thead><tr><th>#</th><th>Model</th><th class="text-end">Qty</th></tr></thead>
          <tbody>
            <?php foreach ($topModels as $i => $m): ?>
              <tr>
                <td><?= $i === 0 ? '🥇' : ($i === 1 ? '🥈' : ($i === 2 ? '🥉' : $i + 1)) ?></td>
                <td><?= clean($m['brand']) ?> <?= clean($m['name']) ?></td>
                <td class="text-end fw-semibold"><?= (int)$m['qty_sold'] ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
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

<script>
(function() {
  // ── Daily Sales Chart ──────────────────────────────────────
  const salesCtx = document.getElementById('dailySalesChart').getContext('2d');
  new Chart(salesCtx, {
    type: 'bar',
    data: {
      labels: <?= json_encode($days) ?>,
      datasets: [{
        label: 'Sales (₹)',
        data: <?= json_encode($daySales) ?>,
        backgroundColor: 'rgba(15,52,96,0.7)',
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
        y: { ticks: { callback: v => '₹' + v.toLocaleString('en-IN') }, grid: { color: '#f0f0f0' } },
        x: { grid: { display: false } }
      }
    }
  });

  // ── Branch Comparison Chart ────────────────────────────────
  <?php if ($isAdmin && !empty($branchSales)): ?>
  const branchCtx = document.getElementById('branchChart').getContext('2d');
  new Chart(branchCtx, {
    type: 'doughnut',
    data: {
      labels: <?= json_encode($branchSales['labels'] ?? []) ?>,
      datasets: [{
        data: <?= json_encode($branchSales['data'] ?? []) ?>,
        backgroundColor: ['#0f3460','#e94560','#28a745','#ffc107','#17a2b8'],
        borderWidth: 2
      }]
    },
    options: {
      responsive: true,
      plugins: {
        legend: { position: 'bottom' },
        tooltip: { callbacks: { label: ctx => ctx.label + ': ₹' + ctx.parsed.toLocaleString('en-IN') } }
      }
    }
  });
  <?php else: ?>
  const branchCanvas = document.getElementById('branchChart');
  const parentDiv = branchCanvas.parentNode;
  branchCanvas.remove();
  parentDiv.insertAdjacentHTML('beforeend', '<div class="text-center text-muted py-5">Branch comparison available for Admin only</div>');
  <?php endif; ?>

  // ── Monthly Sales Chart ────────────────────────────────────
  const monthCtx = document.getElementById('monthlySalesChart').getContext('2d');
  new Chart(monthCtx, {
    type: 'line',
    data: {
      labels: <?= json_encode($months) ?>,
      datasets: [{
        label: 'Monthly Sales (₹)',
        data: <?= json_encode($monthSales) ?>,
        borderColor: '#0f3460',
        backgroundColor: 'rgba(15,52,96,0.1)',
        fill: true,
        tension: 0.4,
        pointRadius: 5,
        pointBackgroundColor: '#0f3460'
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
})();
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
