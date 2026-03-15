<?php
/**
 * Rani Mobiles ERP — Inventory View
 */
require_once __DIR__ . '/../../config/auth.php';
require_auth();

$user      = current_user();
$branch_id = (int)$user['branch_id'];
$isAdmin   = is_admin();
$canSeeAll = can_see_all_branches();
$pdo       = db();

$fBranch  = $canSeeAll ? (int)($_GET['branch'] ?? 0) : $branch_id;
$fBrandId = (int)($_GET['brand'] ?? 0);
$search   = trim($_GET['s'] ?? '');
$filter   = $_GET['filter'] ?? ''; // 'low', 'out', 'ok'

$whereP  = ['1=1'];
$paramsP = [];

// Always filter to a specific branch unless SUP/Admin viewing all
if (!$canSeeAll) {
    // Branch users always see their own branch in primary view
    $whereP[] = 's.branch_id=?';
    $paramsP[] = $branch_id;
} elseif ($fBranch) {
    $whereP[] = 's.branch_id=?';
    $paramsP[] = $fBranch;
}

if ($fBrandId) {
    $whereP[] = 'p.brand_id=?';
    $paramsP[] = $fBrandId;
}

if ($search) {
    $whereP[] = '(p.name LIKE ? OR p.model LIKE ? OR br.name LIKE ?)';
    $paramsP[] = "%$search%"; $paramsP[] = "%$search%"; $paramsP[] = "%$search%";
}

if ($filter === 'low')  $whereP[] = 's.qty > 0 AND s.qty <= p.min_stock';
if ($filter === 'out')  $whereP[] = 's.qty <= 0';
if ($filter === 'ok')   $whereP[] = 's.qty > p.min_stock';

$stocks = $pdo->prepare(
    'SELECT s.qty, s.branch_id, p.id AS product_id, p.name, p.model, p.min_stock,
            p.purchase_cost, p.sale_price, p.imei_tracking,
            br.name AS brand, b.code AS branch_code
     FROM stock s
     JOIN products p  ON p.id=s.product_id
     JOIN brands br   ON br.id=p.brand_id
     JOIN branches b  ON b.id=s.branch_id
     WHERE ' . implode(' AND ', $whereP) . '
     AND p.is_active=1
     ORDER BY s.qty ASC, br.name, p.name LIMIT 500'
);
$stocks->execute($paramsP);
$stocks = $stocks->fetchAll();

$brands   = $pdo->query('SELECT id, name FROM brands ORDER BY name')->fetchAll();
$branches = $pdo->query('SELECT id, code FROM branches ORDER BY code')->fetchAll();

// Summary
$totalItems = count($stocks);
$lowCount   = count(array_filter($stocks, fn($s) => $s['qty'] > 0 && $s['qty'] <= $s['min_stock']));
$outCount   = count(array_filter($stocks, fn($s) => $s['qty'] <= 0));
$totalValue = array_sum(array_map(fn($s) => $s['qty'] * $s['purchase_cost'], $stocks));

$pageTitle = 'Inventory';
?>
<?php include __DIR__ . '/../../includes/header.php'; ?>
<script>window.BASE_URL = '<?= BASE_URL ?>';</script>

<div class="page-header">
  <h1 class="page-title"><i class="bi bi-boxes me-2"></i>Inventory</h1>
  <a href="<?= BASE_URL ?>/modules/stock/products.php" class="btn btn-outline-secondary btn-sm">
    <i class="bi bi-phone me-1"></i>Products
  </a>
</div>

<!-- Summary cards -->
<div class="row g-2 mb-3">
  <div class="col-6 col-md-3">
    <div class="kpi-card p-3">
      <div class="kpi-label">Total Items</div>
      <div class="kpi-value fs-5"><?= $totalItems ?></div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="kpi-card p-3">
      <div class="kpi-label">Stock Value</div>
      <div class="kpi-value fs-5"><?= money($totalValue) ?></div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <a href="?filter=low<?= $fBranch?"&branch=$fBranch":'' ?>" class="text-decoration-none">
      <div class="kpi-card p-3 border-warning">
        <div class="kpi-label text-warning">Low Stock</div>
        <div class="kpi-value fs-5 text-warning"><?= $lowCount ?></div>
      </div>
    </a>
  </div>
  <div class="col-6 col-md-3">
    <a href="?filter=out<?= $fBranch?"&branch=$fBranch":'' ?>" class="text-decoration-none">
      <div class="kpi-card p-3 border-danger">
        <div class="kpi-label text-danger">Out of Stock</div>
        <div class="kpi-value fs-5 text-danger"><?= $outCount ?></div>
      </div>
    </a>
  </div>
</div>

<!-- Filters -->
<div class="erp-form-card mb-3">
  <form method="GET" class="row g-2 align-items-end">
    <?php if ($canSeeAll): ?>
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
    <div class="col-md-2">
      <label class="form-label">Brand</label>
      <select name="brand" class="form-select form-select-sm">
        <option value="">All Brands</option>
        <?php foreach ($brands as $b): ?>
          <option value="<?= $b['id'] ?>" <?= $fBrandId==$b['id']?'selected':'' ?>><?= clean($b['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-md-2">
      <label class="form-label">Stock Filter</label>
      <select name="filter" class="form-select form-select-sm">
        <option value="">All</option>
        <option value="ok" <?= $filter==='ok'?'selected':'' ?>>✅ OK</option>
        <option value="low" <?= $filter==='low'?'selected':'' ?>>⚠️ Low</option>
        <option value="out" <?= $filter==='out'?'selected':'' ?>>❌ Out</option>
      </select>
    </div>
    <div class="col-md-3">
      <label class="form-label">Search</label>
      <input type="text" name="s" class="form-control form-control-sm"
             placeholder="Name, model, brand…" value="<?= clean($search) ?>">
    </div>
    <div class="col-auto">
      <button type="submit" class="btn btn-primary btn-sm">Filter</button>
      <a href="?" class="btn btn-outline-secondary btn-sm ms-1">Reset</a>
    </div>
  </form>
</div>

<!-- Inventory table -->
<div class="erp-table">
  <div class="table-responsive">
    <table class="table table-hover mb-0">
      <thead>
        <tr>
          <th>#</th><th>Product</th><th>Brand</th>
          <?php if ($canSeeAll && !$fBranch): ?><th>Branch</th><?php endif; ?>
          <th class="text-center">Qty</th>
          <th class="text-center">Min Stock</th>
          <th class="text-end">Cost</th>
          <th class="text-end">Value</th>
          <th class="text-center">Status</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($stocks)): ?>
          <tr><td colspan="9" class="text-center text-muted py-4">No inventory records found</td></tr>
        <?php else: ?>
          <?php foreach ($stocks as $i => $s):
            $status = $s['qty'] <= 0
                    ? ['label'=>'Out of Stock', 'class'=>'stock-out', 'icon'=>'❌']
                    : ($s['qty'] <= $s['min_stock']
                      ? ['label'=>'Low Stock', 'class'=>'stock-low', 'icon'=>'⚠️']
                      : ['label'=>'OK', 'class'=>'stock-ok', 'icon'=>'✅']);
          ?>
            <tr>
              <td><?= $i+1 ?></td>
              <td>
                <div class="fw-semibold"><?= clean($s['name']) ?></div>
                <small class="text-muted"><?= clean($s['model'] ?? '') ?></small>
              </td>
              <td><?= clean($s['brand']) ?></td>
              <?php if ($canSeeAll && !$fBranch): ?>
                <td><span class="badge bg-primary"><?= clean($s['branch_code']) ?></span></td>
              <?php endif; ?>
              <?php
                // Branch staff see full detail only for their own branch;
                // other-branch rows show availability only (no qty, cost, value).
                $isOwnBranch = $canSeeAll || (int)$s['branch_id'] === $branch_id;
              ?>
              <td class="text-center fw-bold <?= $status['class'] ?>">
                <?php if ($isOwnBranch): ?>
                  <?= $s['qty'] ?>
                <?php else: ?>
                  <span class="text-muted"><?= $s['qty'] > 0 ? __('available') : __('not_available') ?></span>
                <?php endif; ?>
              </td>
              <td class="text-center text-muted"><?= $isOwnBranch ? $s['min_stock'] : '-' ?></td>
              <td class="text-end"><?= $isOwnBranch ? money((float)$s['purchase_cost']) : '-' ?></td>
              <td class="text-end"><?= $isOwnBranch ? money(max(0, (float)$s['qty'] * (float)$s['purchase_cost'])) : '-' ?></td>
              <td class="text-center <?= $status['class'] ?>"><?= $status['icon'] ?> <?= $status['label'] ?></td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
