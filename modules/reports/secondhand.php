<?php
/**
 * Rani Mobiles ERP — Second-Hand Phones Report
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

$buyWhere  = ['shp.created_at BETWEEN ? AND ?'];
$buyParams = [$fromDate . ' 00:00:00', $toDate . ' 23:59:59'];
if ($fBranch) { $buyWhere[] = 'shp.branch_id=?'; $buyParams[] = $fBranch; }
elseif (!$isAdmin) { $buyWhere[] = 'shp.branch_id=?'; $buyParams[] = $branch_id; }

$buyWhereClause = implode(' AND ', $buyWhere);

// Summary
$summaryQ = $pdo->prepare(
    'SELECT COUNT(*) AS total_bought,
            SUM(CASE WHEN shp.sold=1 THEN 1 ELSE 0 END) AS total_sold,
            SUM(CASE WHEN shp.sold=0 THEN 1 ELSE 0 END) AS in_stock,
            COALESCE(SUM(shp.buy_price),0) AS total_investment
     FROM second_hand_purchases shp WHERE ' . $buyWhereClause
);
$summaryQ->execute($buyParams);
$summary = $summaryQ->fetch();

// Total profit from sales in this period
$profitQ = $pdo->prepare(
    'SELECT COALESCE(SUM(shs.profit),0) AS total_profit,
            COALESCE(SUM(shs.sale_price),0) AS total_revenue
     FROM second_hand_sales shs
     JOIN second_hand_purchases shp ON shp.id=shs.purchase_id
     WHERE ' . $buyWhereClause
);
$profitQ->execute($buyParams);
$profitData = $profitQ->fetch();

// Device list
$devicesQ = $pdo->prepare(
    'SELECT shp.ref_no, shp.created_at, b.code AS branch,
            shp.seller_name, shp.seller_phone,
            br.name AS brand, shp.model, shp.imei,
            shp.condition, shp.buy_price, shp.sold,
            shs.sale_price, shs.profit, shs.buyer_name, shs.sale_date
     FROM second_hand_purchases shp
     JOIN branches b ON b.id=shp.branch_id
     LEFT JOIN brands br ON br.id=shp.brand_id
     LEFT JOIN second_hand_sales shs ON shs.purchase_id=shp.id
     WHERE ' . $buyWhereClause . '
     ORDER BY shp.created_at DESC'
);
$devicesQ->execute($buyParams);
$devices = $devicesQ->fetchAll();

$branches = $pdo->query('SELECT id, code FROM branches ORDER BY code')->fetchAll();

// CSV export
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="secondhand_report_' . $fromDate . '_to_' . $toDate . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Ref No','Date','Branch','Seller','Phone','Brand','Model','IMEI','Condition','Buy Price','Sold','Sale Price','Profit','Buyer','Sale Date']);
    foreach ($devices as $d) {
        fputcsv($out, [
            $d['ref_no'], date('d-m-Y', strtotime($d['created_at'])),
            $d['branch'], $d['seller_name'], $d['seller_phone'],
            $d['brand'] ?? '-', $d['model'], $d['imei'] ?? '-',
            $d['condition'], $d['buy_price'],
            $d['sold'] ? 'Yes' : 'No',
            $d['sale_price'] ?? '-', $d['profit'] ?? '-',
            $d['buyer_name'] ?? '-',
            $d['sale_date'] ? date('d-m-Y', strtotime($d['sale_date'])) : '-'
        ]);
    }
    fclose($out);
    exit;
}

$pageTitle = 'Second-Hand Report';
?>
<?php include __DIR__ . '/../../includes/header.php'; ?>
<script>window.BASE_URL = '<?= BASE_URL ?>';</script>

<div class="page-header">
  <h1 class="page-title"><i class="bi bi-recycle me-2"></i>Second-Hand Phones Report</h1>
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
          <option value="<?= $b['id'] ?>" <?= $fBranch == $b['id'] ? 'selected' : '' ?>><?= clean($b['code']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <?php endif; ?>
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

<!-- KPI Summary -->
<div class="row g-3 mb-4">
  <div class="col-6 col-md-3">
    <div class="kpi-card p-3">
      <div class="kpi-label">Total Bought</div>
      <div class="kpi-value"><?= (int)$summary['total_bought'] ?></div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="kpi-card kpi-sales p-3">
      <div class="kpi-label">Sold</div>
      <div class="kpi-value"><?= (int)$summary['total_sold'] ?></div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="kpi-card kpi-purchase p-3">
      <div class="kpi-label">In Stock</div>
      <div class="kpi-value"><?= (int)$summary['in_stock'] ?></div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="kpi-card kpi-profit p-3">
      <div class="kpi-label">Total Profit</div>
      <div class="kpi-value"><?= money((float)$profitData['total_profit']) ?></div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="kpi-card kpi-cash p-3">
      <div class="kpi-label">Total Investment</div>
      <div class="kpi-value"><?= money((float)$summary['total_investment']) ?></div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="kpi-card p-3">
      <div class="kpi-label">Total Revenue</div>
      <div class="kpi-value"><?= money((float)$profitData['total_revenue']) ?></div>
    </div>
  </div>
</div>

<!-- Devices Table -->
<div class="erp-table">
  <div class="p-3 border-bottom fw-bold"><i class="bi bi-phone me-2"></i>Device Records (<?= count($devices) ?>)</div>
  <div class="table-responsive">
    <table class="table table-hover mb-0">
      <thead>
        <tr>
          <th>Ref No</th>
          <th>Date</th>
          <th>Branch</th>
          <th>Device</th>
          <th>IMEI</th>
          <th>Condition</th>
          <th>Seller</th>
          <th class="text-end">Buy Price</th>
          <th>Status</th>
          <th class="text-end">Sale Price</th>
          <th class="text-end">Profit</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($devices)): ?>
          <tr><td colspan="11" class="text-center text-muted py-3">No records found</td></tr>
        <?php else: ?>
          <?php foreach ($devices as $d): ?>
            <tr>
              <td><code><?= clean($d['ref_no']) ?></code></td>
              <td><?= date('d-m-Y', strtotime($d['created_at'])) ?></td>
              <td><span class="badge bg-primary"><?= clean($d['branch']) ?></span></td>
              <td>
                <?= clean(($d['brand'] ?? '') . ' ' . ($d['model'] ?? '')) ?>
              </td>
              <td><small class="text-muted"><?= clean($d['imei'] ?? '-') ?></small></td>
              <td>
                <?php
                  $condColors = ['excellent' => 'success', 'good' => 'info', 'fair' => 'warning', 'poor' => 'danger'];
                  $cc = $condColors[$d['condition']] ?? 'secondary';
                ?>
                <span class="badge bg-<?= $cc ?>"><?= ucfirst($d['condition']) ?></span>
              </td>
              <td>
                <?= clean($d['seller_name']) ?>
                <?php if ($d['seller_phone']): ?>
                  <br><small class="text-muted"><?= clean($d['seller_phone']) ?></small>
                <?php endif; ?>
              </td>
              <td class="text-end"><?= money((float)$d['buy_price']) ?></td>
              <td>
                <?php if ($d['sold']): ?>
                  <span class="badge bg-success">Sold</span>
                  <?php if ($d['sale_date']): ?>
                    <br><small class="text-muted"><?= date('d-m-Y', strtotime($d['sale_date'])) ?></small>
                  <?php endif; ?>
                <?php else: ?>
                  <span class="badge bg-warning text-dark">In Stock</span>
                <?php endif; ?>
              </td>
              <td class="text-end"><?= $d['sale_price'] !== null ? money((float)$d['sale_price']) : '-' ?></td>
              <td class="text-end fw-semibold <?= ($d['profit'] ?? 0) >= 0 ? 'text-success' : 'text-danger' ?>">
                <?= $d['profit'] !== null ? money((float)$d['profit']) : '-' ?>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
