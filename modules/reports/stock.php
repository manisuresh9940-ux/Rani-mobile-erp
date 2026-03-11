<?php
/**
 * Rani Mobiles ERP — Stock Report
 */
require_once __DIR__ . '/../../config/auth.php';
require_auth();

$user      = current_user();
$branch_id = (int)$user['branch_id'];
$isAdmin   = is_admin();
$pdo       = db();

$fBranch = $isAdmin ? (int)($_GET['branch'] ?? 0) : $branch_id;

$where  = ['p.is_active=1'];
$params = [];
if ($fBranch) { $where[] = 's.branch_id=?'; $params[] = $fBranch; }
elseif (!$isAdmin) { $where[] = 's.branch_id=?'; $params[] = $branch_id; }

$stocks = $pdo->prepare(
    'SELECT p.name, p.model, p.purchase_cost, p.sale_price, p.min_stock,
            br.name AS brand, c.name AS category,
            COALESCE(SUM(s.qty),0) AS total_qty,
            COALESCE(SUM(s.qty * p.purchase_cost),0) AS cost_value,
            COALESCE(SUM(s.qty * p.sale_price),0) AS sell_value
     FROM products p
     JOIN brands br ON br.id=p.brand_id
     LEFT JOIN categories c ON c.id=p.category_id
     LEFT JOIN stock s ON s.product_id=p.id
     WHERE ' . implode(' AND ', $where) . '
     GROUP BY p.id ORDER BY br.name, p.name LIMIT 500'
);
$stocks->execute($params);
$stocks = $stocks->fetchAll();

$totalCostVal = array_sum(array_column($stocks, 'cost_value'));
$totalSellVal = array_sum(array_column($stocks, 'sell_value'));
$totalItems   = count($stocks);

$branches = $pdo->query('SELECT id, code FROM branches ORDER BY code')->fetchAll();
$pageTitle = 'Stock Report';
?>
<?php include __DIR__ . '/../../includes/header.php'; ?>
<script>window.BASE_URL = '<?= BASE_URL ?>';</script>

<div class="page-header">
  <h1 class="page-title"><i class="bi bi-boxes me-2"></i>Stock Report</h1>
  <div class="d-flex gap-2 no-print">
    <a href="<?= BASE_URL ?>/api/export_report.php?type=stock<?= $fBranch?"&branch=$fBranch":'' ?>"
       class="btn btn-outline-success btn-sm">
      <i class="bi bi-download me-1"></i>Export CSV
    </a>
    <button onclick="window.print()" class="btn btn-outline-secondary btn-sm">
      <i class="bi bi-printer"></i> Print
    </button>
  </div>
</div>

<div class="erp-form-card mb-3 no-print">
  <form method="GET" class="row g-2 align-items-end">
    <?php if ($isAdmin): ?>
    <div class="col-md-3">
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
  </form>
</div>

<div class="row g-3 mb-3">
  <div class="col-md-4"><div class="kpi-card p-3"><div class="kpi-label">Total Products</div><div class="kpi-value"><?= $totalItems ?></div></div></div>
  <div class="col-md-4"><div class="kpi-card p-3"><div class="kpi-label">Cost Value</div><div class="kpi-value"><?= money($totalCostVal) ?></div></div></div>
  <div class="col-md-4"><div class="kpi-card p-3"><div class="kpi-label">Retail Value</div><div class="kpi-value"><?= money($totalSellVal) ?></div></div></div>
</div>

<div class="erp-table">
  <div class="table-responsive">
    <table class="table table-hover mb-0">
      <thead>
        <tr><th>#</th><th>Product</th><th>Brand</th><th>Category</th>
            <th class="text-end">Cost</th><th class="text-end">Price</th>
            <th class="text-center">Qty</th><th class="text-center">Min</th>
            <th class="text-end">Cost Val.</th><th class="text-end">Retail Val.</th>
            <th>Status</th></tr>
      </thead>
      <tbody>
        <?php if (empty($stocks)): ?>
          <tr><td colspan="11" class="text-center text-muted py-4">No data</td></tr>
        <?php else: ?>
          <?php foreach ($stocks as $i => $s):
            $status = $s['total_qty'] <= 0
                    ? ['label'=>'Out', 'class'=>'stock-out']
                    : ($s['total_qty'] <= $s['min_stock']
                      ? ['label'=>'Low', 'class'=>'stock-low']
                      : ['label'=>'OK', 'class'=>'stock-ok']);
          ?>
            <tr>
              <td><?= $i+1 ?></td>
              <td>
                <div class="fw-semibold"><?= clean($s['name']) ?></div>
                <small class="text-muted"><?= clean($s['model'] ?? '') ?></small>
              </td>
              <td><?= clean($s['brand']) ?></td>
              <td><small><?= clean($s['category'] ?? '-') ?></small></td>
              <td class="text-end"><?= money((float)$s['purchase_cost']) ?></td>
              <td class="text-end"><?= money((float)$s['sale_price']) ?></td>
              <td class="text-center fw-bold <?= $status['class'] ?>"><?= $s['total_qty'] ?></td>
              <td class="text-center text-muted"><?= $s['min_stock'] ?></td>
              <td class="text-end"><?= money(max(0, (float)$s['cost_value'])) ?></td>
              <td class="text-end"><?= money(max(0, (float)$s['sell_value'])) ?></td>
              <td class="<?= $status['class'] ?>"><?= $status['label'] ?></td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
      <tfoot class="table-secondary fw-bold">
        <tr>
          <td colspan="8" class="text-end">Totals:</td>
          <td class="text-end"><?= money($totalCostVal) ?></td>
          <td class="text-end"><?= money($totalSellVal) ?></td>
          <td></td>
        </tr>
      </tfoot>
    </table>
  </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
