<?php
/**
 * Rani Mobiles ERP - Dashboard
 */
require_once __DIR__ . '/config/auth.php';
require_auth();

$user = current_user();
$branchId = (int)($user['branch_id'] ?? 0);
$isAdmin = is_admin();
$isSuperAdmin = is_super_admin();
$pdo = db();
$today = date('Y-m-d');

$singleValue = static function (string $sql, array $params = []) use ($pdo): float {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return (float)$stmt->fetchColumn();
};

$todaySales = $singleValue(
    $isAdmin
        ? "SELECT COALESCE(SUM(total), 0) FROM sales WHERE sale_date = ?"
        : "SELECT COALESCE(SUM(total), 0) FROM sales WHERE sale_date = ? AND branch_id = ?",
    $isAdmin ? [$today] : [$today, $branchId]
);

$todayPurchase = $singleValue(
    $isAdmin
        ? "SELECT COALESCE(SUM(total), 0) FROM purchases WHERE purchase_date = ?"
        : "SELECT COALESCE(SUM(total), 0) FROM purchases WHERE purchase_date = ? AND branch_id = ?",
    $isAdmin ? [$today] : [$today, $branchId]
);

$todayProfit = $singleValue(
    $isAdmin
        ? "SELECT COALESCE(SUM(si.total - (p.purchase_cost * si.qty)), 0)
           FROM sale_items si
           JOIN sales s ON s.id = si.sale_id
           JOIN products p ON p.id = si.product_id
           WHERE s.sale_date = ?"
        : "SELECT COALESCE(SUM(si.total - (p.purchase_cost * si.qty)), 0)
           FROM sale_items si
           JOIN sales s ON s.id = si.sale_id
           JOIN products p ON p.id = si.product_id
           WHERE s.sale_date = ? AND s.branch_id = ?",
    $isAdmin ? [$today] : [$today, $branchId]
);

$cashIn = $singleValue(
    $isAdmin
        ? "SELECT COALESCE(SUM(paid_cash), 0) FROM sales WHERE sale_date = ?"
        : "SELECT COALESCE(SUM(paid_cash), 0) FROM sales WHERE sale_date = ? AND branch_id = ?",
    $isAdmin ? [$today] : [$today, $branchId]
);

$cashOut = $singleValue(
    $isAdmin
        ? "SELECT COALESCE(SUM(amount), 0) FROM expenses WHERE expense_date = ?"
        : "SELECT COALESCE(SUM(amount), 0) FROM expenses WHERE expense_date = ? AND branch_id = ?",
    $isAdmin ? [$today] : [$today, $branchId]
);
$cashBalance = $cashIn - $cashOut;

$stockValue = $singleValue(
    $isAdmin
        ? "SELECT COALESCE(SUM(s.qty * p.purchase_cost), 0)
           FROM stock s
           JOIN products p ON p.id = s.product_id"
        : "SELECT COALESCE(SUM(s.qty * p.purchase_cost), 0)
           FROM stock s
           JOIN products p ON p.id = s.product_id
           WHERE s.branch_id = ?",
    $isAdmin ? [] : [$branchId]
);

$lowStockCount = (int)$singleValue(
    $isAdmin
        ? "SELECT COUNT(*)
           FROM stock s
           JOIN products p ON p.id = s.product_id
           WHERE s.qty <= p.min_stock"
        : "SELECT COUNT(*)
           FROM stock s
           JOIN products p ON p.id = s.product_id
           WHERE s.branch_id = ? AND s.qty <= p.min_stock",
    $isAdmin ? [] : [$branchId]
);

$pendingPayments = $singleValue(
    $isAdmin
        ? "SELECT COALESCE(SUM(paid_credit), 0) FROM sales"
        : "SELECT COALESCE(SUM(paid_credit), 0) FROM sales WHERE branch_id = ?",
    $isAdmin ? [] : [$branchId]
);

$days = [];
$daySales = [];
$dayIndexMap = [];
for ($i = 6; $i >= 0; $i--) {
    $dayKey = date('Y-m-d', strtotime('-' . $i . ' days'));
    $days[] = date('d M', strtotime($dayKey));
    $daySales[] = 0.0;
    $dayIndexMap[$dayKey] = count($daySales) - 1;
}

$fromDate = date('Y-m-d', strtotime('-6 days'));
if ($isAdmin) {
    $stmt = $pdo->prepare(
        "SELECT sale_date, COALESCE(SUM(total), 0) AS total
         FROM sales
         WHERE sale_date BETWEEN ? AND ?
         GROUP BY sale_date"
    );
    $stmt->execute([$fromDate, $today]);
} else {
    $stmt = $pdo->prepare(
        "SELECT sale_date, COALESCE(SUM(total), 0) AS total
         FROM sales
         WHERE sale_date BETWEEN ? AND ? AND branch_id = ?
         GROUP BY sale_date"
    );
    $stmt->execute([$fromDate, $today, $branchId]);
}

foreach ($stmt->fetchAll() as $row) {
    $key = (string)$row['sale_date'];
    if (array_key_exists($key, $dayIndexMap)) {
        $daySales[$dayIndexMap[$key]] = round((float)$row['total'], 2);
    }
}

$branchSales = ['labels' => [], 'data' => []];
if ($isAdmin) {
    $branchStmt = $pdo->query(
        "SELECT b.code, COALESCE(SUM(s.total), 0) AS total
         FROM branches b
         LEFT JOIN sales s ON s.branch_id = b.id AND s.sale_date = CURDATE()
         WHERE b.is_active = 1
         GROUP BY b.id, b.code
         ORDER BY b.code"
    );
    foreach ($branchStmt->fetchAll() as $row) {
        $branchSales['labels'][] = (string)$row['code'];
        $branchSales['data'][] = round((float)$row['total'], 2);
    }
}

if ($isAdmin) {
    $lowItemsStmt = $pdo->query(
        "SELECT p.name, p.model, COALESCE(br.name, '-') AS brand, s.qty, p.min_stock, b.code AS branch_code
         FROM stock s
         JOIN branches b ON b.id = s.branch_id
         JOIN products p ON p.id = s.product_id
         LEFT JOIN brands br ON br.id = p.brand_id
         WHERE s.qty <= p.min_stock
         ORDER BY (s.qty <= 0) DESC, s.qty ASC
         LIMIT 10"
    );
    $lowItems = $lowItemsStmt->fetchAll();
} else {
    $lowItemsStmt = $pdo->prepare(
        "SELECT p.name, p.model, COALESCE(br.name, '-') AS brand, s.qty, p.min_stock
         FROM stock s
         JOIN products p ON p.id = s.product_id
         LEFT JOIN brands br ON br.id = p.brand_id
         WHERE s.branch_id = ? AND s.qty <= p.min_stock
         ORDER BY (s.qty <= 0) DESC, s.qty ASC
         LIMIT 10"
    );
    $lowItemsStmt->execute([$branchId]);
    $lowItems = $lowItemsStmt->fetchAll();
}

if ($isAdmin) {
    $recentSalesStmt = $pdo->query(
        "SELECT s.invoice_no, s.customer_name, s.total, s.sale_date, b.code AS branch
         FROM sales s
         JOIN branches b ON b.id = s.branch_id
         ORDER BY s.id DESC
         LIMIT 8"
    );
    $recentSales = $recentSalesStmt->fetchAll();
} else {
    $recentSalesStmt = $pdo->prepare(
        "SELECT s.invoice_no, s.customer_name, s.total, s.sale_date, b.code AS branch
         FROM sales s
         JOIN branches b ON b.id = s.branch_id
         WHERE s.branch_id = ?
         ORDER BY s.id DESC
         LIMIT 8"
    );
    $recentSalesStmt->execute([$branchId]);
    $recentSales = $recentSalesStmt->fetchAll();
}

if ($isAdmin) {
    $deadStockStmt = $pdo->query(
        "SELECT p.name, p.model, COALESCE(br.name, '-') AS brand,
                COALESCE(DATEDIFF(CURDATE(), MAX(s.sale_date)), 999) AS days_no_sale
         FROM products p
         JOIN (
             SELECT product_id, SUM(qty) AS qty_total
             FROM stock
             GROUP BY product_id
         ) st ON st.product_id = p.id AND st.qty_total > 0
         LEFT JOIN brands br ON br.id = p.brand_id
         LEFT JOIN sale_items si ON si.product_id = p.id
         LEFT JOIN sales s ON s.id = si.sale_id
         WHERE p.is_active = 1
         GROUP BY p.id, p.name, p.model, br.name
         HAVING days_no_sale > 45
         ORDER BY days_no_sale DESC
         LIMIT 5"
    );
    $deadStock = $deadStockStmt->fetchAll();
} else {
    $deadStockStmt = $pdo->prepare(
        "SELECT p.name, p.model, COALESCE(br.name, '-') AS brand,
                COALESCE(DATEDIFF(CURDATE(), MAX(s.sale_date)), 999) AS days_no_sale
         FROM products p
         JOIN stock st ON st.product_id = p.id AND st.branch_id = ? AND st.qty > 0
         LEFT JOIN brands br ON br.id = p.brand_id
         LEFT JOIN sale_items si ON si.product_id = p.id
         LEFT JOIN sales s ON s.id = si.sale_id AND s.branch_id = ?
         WHERE p.is_active = 1
         GROUP BY p.id, p.name, p.model, br.name
         HAVING days_no_sale > 45
         ORDER BY days_no_sale DESC
         LIMIT 5"
    );
    $deadStockStmt->execute([$branchId, $branchId]);
    $deadStock = $deadStockStmt->fetchAll();
}

if ($isAdmin) {
    $topProductsStmt = $pdo->prepare(
        "SELECT p.name, p.model, COALESCE(br.name, '-') AS brand,
                SUM(si.qty) AS qty_sold,
                SUM(si.total) AS total_sold
         FROM sale_items si
         JOIN sales s ON s.id = si.sale_id
         JOIN products p ON p.id = si.product_id
         LEFT JOIN brands br ON br.id = p.brand_id
         WHERE s.sale_date = ?
         GROUP BY si.product_id, p.name, p.model, br.name
         ORDER BY qty_sold DESC, total_sold DESC
         LIMIT 6"
    );
    $topProductsStmt->execute([$today]);
} else {
    $topProductsStmt = $pdo->prepare(
        "SELECT p.name, p.model, COALESCE(br.name, '-') AS brand,
                SUM(si.qty) AS qty_sold,
                SUM(si.total) AS total_sold
         FROM sale_items si
         JOIN sales s ON s.id = si.sale_id
         JOIN products p ON p.id = si.product_id
         LEFT JOIN brands br ON br.id = p.brand_id
         WHERE s.sale_date = ? AND s.branch_id = ?
         GROUP BY si.product_id, p.name, p.model, br.name
         ORDER BY qty_sold DESC, total_sold DESC
         LIMIT 6"
    );
    $topProductsStmt->execute([$today, $branchId]);
}
$topProducts = $topProductsStmt->fetchAll();

$paymentStmt = $pdo->prepare(
    $isAdmin
        ? "SELECT COALESCE(SUM(paid_cash), 0) AS cash,
                  COALESCE(SUM(paid_upi), 0) AS upi,
                  COALESCE(SUM(paid_card), 0) AS card,
                  COALESCE(SUM(paid_credit), 0) AS credit
           FROM sales
           WHERE sale_date = ?"
        : "SELECT COALESCE(SUM(paid_cash), 0) AS cash,
                  COALESCE(SUM(paid_upi), 0) AS upi,
                  COALESCE(SUM(paid_card), 0) AS card,
                  COALESCE(SUM(paid_credit), 0) AS credit
           FROM sales
           WHERE sale_date = ? AND branch_id = ?"
);
$paymentStmt->execute($isAdmin ? [$today] : [$today, $branchId]);
$paymentRow = $paymentStmt->fetch() ?: ['cash' => 0, 'upi' => 0, 'card' => 0, 'credit' => 0];
$paymentSeries = [
    round((float)$paymentRow['cash'], 2),
    round((float)$paymentRow['upi'], 2),
    round((float)$paymentRow['card'], 2),
    round((float)$paymentRow['credit'], 2),
];

$pageTitle = 'Dashboard';
?>
<?php include __DIR__ . '/includes/header.php'; ?>
<script>window.BASE_URL = '<?= BASE_URL ?>';</script>

<div class="page-header">
  <div>
    <h1 class="page-title"><i class="bi bi-speedometer2 me-2"></i>Business Control Center</h1>
    <p class="page-subtitle">
      <?= date('l, d F Y') ?>
      &nbsp;·&nbsp;
      <?= clean($user['branch']) ?> Branch
      <?= $isSuperAdmin ? '&nbsp;·&nbsp;<span class="badge bg-danger">Admin View</span>' : '' ?>
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

<div class="row g-3 mb-4">
  <div class="col-12 col-lg-8">
    <div class="chart-card mb-0">
      <div class="chart-title"><i class="bi bi-bar-chart-line me-2"></i>Sales - Last 7 Days</div>
      <div id="dailySalesChart"></div>
    </div>
  </div>
  <div class="col-12 col-lg-4">
    <div class="chart-card mb-0">
      <div class="chart-title"><i class="bi bi-pie-chart me-2"></i><?= $isAdmin ? 'Today Branch Comparison' : 'Today Payment Mix' ?></div>
      <div id="sideChart"></div>
    </div>
  </div>
</div>

<div class="row g-3 mb-4">
  <div class="col-lg-4">
    <div class="chart-card h-100 mb-0">
      <div class="chart-title text-danger"><i class="bi bi-exclamation-triangle me-2"></i>Low / Out of Stock</div>
      <?php if (empty($lowItems)): ?>
        <div class="text-center text-muted py-3">
          <i class="bi bi-check-circle text-success fs-2"></i><br>All stock levels OK
        </div>
      <?php else: ?>
        <?php foreach ($lowItems as $item): ?>
          <div class="alert-item <?= ((int)$item['qty'] <= 0) ? 'danger' : '' ?>">
            <div><i class="bi bi-phone text-warning"></i></div>
            <div>
              <div class="fw-semibold">
                <?= clean($item['brand']) ?> <?= clean($item['name']) ?>
                <?php if (!empty($item['model'])): ?>
                  <span class="text-muted">(<?= clean($item['model']) ?>)</span>
                <?php endif; ?>
              </div>
              <div class="text-muted">
                Qty: <strong><?= (int)$item['qty'] ?></strong> | Min: <?= (int)$item['min_stock'] ?>
                <?php if ($isAdmin && !empty($item['branch_code'])): ?>
                  | <span class="badge bg-secondary"><?= clean($item['branch_code']) ?></span>
                <?php endif; ?>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>

      <?php if (!empty($deadStock)): ?>
        <div class="chart-title text-warning mt-3"><i class="bi bi-hourglass me-2"></i>Dead Stock (45+ days)</div>
        <?php foreach ($deadStock as $item): ?>
          <div class="alert-item">
            <div><i class="bi bi-phone text-secondary"></i></div>
            <div>
              <div class="fw-semibold">
                <?= clean($item['brand']) ?> <?= clean($item['name']) ?>
                <?php if (!empty($item['model'])): ?>
                  <span class="text-muted">(<?= clean($item['model']) ?>)</span>
                <?php endif; ?>
              </div>
              <div class="text-muted">No sale for <strong><?= (int)$item['days_no_sale'] ?></strong> days</div>
            </div>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </div>

  <div class="col-lg-8">
    <div class="erp-table mb-0">
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
            <tr><td colspan="5" class="text-center text-muted py-3">No sales found</td></tr>
          <?php else: ?>
            <?php foreach ($recentSales as $sale): ?>
              <tr>
                <td><code><?= clean($sale['invoice_no']) ?></code></td>
                <td><?= clean($sale['customer_name'] ?: 'Walk-in') ?></td>
                <td><span class="badge bg-primary"><?= clean($sale['branch']) ?></span></td>
                <td><?= fmt_date((string)$sale['sale_date']) ?></td>
                <td class="text-end fw-semibold"><?= money((float)$sale['total']) ?></td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<div class="row g-3">
  <div class="col-12">
    <div class="erp-table">
      <div class="d-flex align-items-center justify-content-between p-3 border-bottom">
        <strong><i class="bi bi-trophy me-2"></i>Top Selling Products Today</strong>
        <span class="badge bg-light text-dark border">Pending Credit: <?= money($pendingPayments) ?></span>
      </div>
      <table class="table table-hover mb-0">
        <thead>
          <tr>
            <th>Product</th>
            <th>Model</th>
            <th>Brand</th>
            <th class="text-end">Qty Sold</th>
            <th class="text-end">Sales Value</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($topProducts)): ?>
            <tr><td colspan="5" class="text-center text-muted py-3">No product sales recorded today</td></tr>
          <?php else: ?>
            <?php foreach ($topProducts as $item): ?>
              <tr>
                <td><?= clean($item['name']) ?></td>
                <td><?= clean($item['model'] ?: '-') ?></td>
                <td><?= clean($item['brand']) ?></td>
                <td class="text-end fw-semibold"><?= (int)$item['qty_sold'] ?></td>
                <td class="text-end"><?= money((float)$item['total_sold']) ?></td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<script>
(function () {
  const salesOptions = {
    chart: {
      type: 'area',
      height: 250,
      toolbar: { show: false },
      animations: { enabled: true }
    },
    series: [{ name: 'Sales', data: <?= json_encode($daySales) ?> }],
    xaxis: { categories: <?= json_encode($days) ?> },
    yaxis: {
      labels: {
        formatter: function (value) {
          return '₹' + Number(value).toLocaleString('en-IN');
        }
      }
    },
    dataLabels: { enabled: false },
    stroke: { curve: 'smooth', width: 3 },
    fill: {
      type: 'gradient',
      gradient: {
        shadeIntensity: 1,
        opacityFrom: 0.4,
        opacityTo: 0.06
      }
    },
    colors: ['#0f3460'],
    tooltip: {
      y: {
        formatter: function (value) {
          return '₹' + Number(value).toLocaleString('en-IN');
        }
      }
    },
    grid: { borderColor: '#f0f0f0' }
  };
  new ApexCharts(document.getElementById('dailySalesChart'), salesOptions).render();

  const sideChartContainer = document.getElementById('sideChart');

  <?php if ($isAdmin): ?>
    const branchData = <?= json_encode($branchSales['data']) ?>;
    const branchLabels = <?= json_encode($branchSales['labels']) ?>;
    const hasBranchData = branchData.some(function (value) { return Number(value) > 0; });

    if (hasBranchData) {
      const branchOptions = {
        chart: { type: 'donut', height: 250, toolbar: { show: false } },
        series: branchData,
        labels: branchLabels,
        legend: { position: 'bottom' },
        colors: ['#0f3460', '#e94560', '#28a745', '#ffc107', '#17a2b8', '#6f42c1'],
        tooltip: {
          y: {
            formatter: function (value) {
              return '₹' + Number(value).toLocaleString('en-IN');
            }
          }
        }
      };
      new ApexCharts(sideChartContainer, branchOptions).render();
    } else {
      sideChartContainer.innerHTML = '<div class="text-center text-muted py-5">No branch sales data for today</div>';
    }
  <?php else: ?>
    const paymentSeries = <?= json_encode($paymentSeries) ?>;
    const hasPaymentData = paymentSeries.some(function (value) { return Number(value) > 0; });

    if (hasPaymentData) {
      const paymentOptions = {
        chart: { type: 'donut', height: 250, toolbar: { show: false } },
        series: paymentSeries,
        labels: ['Cash', 'UPI', 'Card', 'Credit'],
        legend: { position: 'bottom' },
        colors: ['#28a745', '#17a2b8', '#0f3460', '#e94560'],
        tooltip: {
          y: {
            formatter: function (value) {
              return '₹' + Number(value).toLocaleString('en-IN');
            }
          }
        }
      };
      new ApexCharts(sideChartContainer, paymentOptions).render();
    } else {
      sideChartContainer.innerHTML = '<div class="text-center text-muted py-5">No payment data for today</div>';
    }
  <?php endif; ?>
})();
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
