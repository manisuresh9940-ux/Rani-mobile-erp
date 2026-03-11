<?php
/**
 * Rani Mobiles ERP — Second-Hand Device List + Sell
 */
require_once __DIR__ . '/../../config/auth.php';
require_auth();

$user      = current_user();
$branch_id = (int)$user['branch_id'];
$isAdmin   = is_admin();
$pdo       = db();

$message = $error = '';

// Handle sell
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['sell_device'])) {
    verify_csrf();

    $sh_id      = (int)$_POST['sh_id'];
    $buyer_name = clean($_POST['buyer_name'] ?? '');
    $buyer_phone= clean($_POST['buyer_phone'] ?? '');
    $sale_price = (float)$_POST['sale_price'];

    $shRow = $pdo->prepare('SELECT * FROM second_hand_purchases WHERE id=? AND sold=0');
    $shRow->execute([$sh_id]);
    $shRow = $shRow->fetch();

    if (!$shRow) {
        $error = 'Device not found or already sold.';
    } elseif ($sale_price <= 0) {
        $error = 'Invalid sale price.';
    } else {
        $profit  = $sale_price - $shRow['buy_price'];
        $sale_ref = next_serial($branch_id, 'SHS');

        $pdo->prepare(
            'INSERT INTO second_hand_sales (purchase_id,sale_ref,branch_id,buyer_name,buyer_phone,
             sale_price,profit,sale_date,created_by)
             VALUES (?,?,?,?,?,?,?,CURDATE(),?)'
        )->execute([$sh_id, $sale_ref, $branch_id, $buyer_name, $buyer_phone,
                    $sale_price, $profit, $user['id']]);

        $pdo->prepare('UPDATE second_hand_purchases SET sold=1 WHERE id=?')->execute([$sh_id]);

        log_activity('SH_SELL', "Ref: $sale_ref, Profit: $profit");
        $message = "Device sold! Ref: $sale_ref | Profit: " . money($profit);
    }
}

$where  = ['1=1'];
$params = [];
if (!$isAdmin) { $where[]='sh.branch_id=?'; $params[]=$branch_id; }
$fSold  = $_GET['sold'] ?? '';
if ($fSold === '0') { $where[]='sh.sold=0'; }
elseif ($fSold === '1') { $where[]='sh.sold=1'; }

$devices = $pdo->prepare(
    'SELECT sh.*, br.name AS brand_name, b.code AS branch_code
     FROM second_hand_purchases sh
     LEFT JOIN brands br ON br.id=sh.brand_id
     JOIN branches b ON b.id=sh.branch_id
     WHERE ' . implode(' AND ', $where) . ' ORDER BY sh.id DESC LIMIT 200'
);
$devices->execute($params);
$devices = $devices->fetchAll();

$pageTitle = 'Second-Hand Devices';
?>
<?php include __DIR__ . '/../../includes/header.php'; ?>
<script>window.BASE_URL = '<?= BASE_URL ?>';</script>

<?php if ($message): ?>
  <div class="alert alert-success"><i class="bi bi-check-circle me-2"></i><?= clean($message) ?></div>
<?php endif; ?>
<?php if ($error): ?>
  <div class="alert alert-danger"><?= clean($error) ?></div>
<?php endif; ?>

<div class="page-header">
  <h1 class="page-title"><i class="bi bi-recycle me-2"></i>Second-Hand Devices</h1>
  <a href="<?= BASE_URL ?>/modules/secondhand/buy.php" class="btn btn-primary btn-sm">
    <i class="bi bi-plus-circle me-1"></i>Buy Device
  </a>
</div>

<!-- Filter tabs -->
<div class="mb-3 d-flex gap-2">
  <a href="?" class="btn btn-sm <?= $fSold===''?'btn-primary':'btn-outline-secondary' ?>">All</a>
  <a href="?sold=0" class="btn btn-sm <?= $fSold==='0'?'btn-success':'btn-outline-success' ?>">Available</a>
  <a href="?sold=1" class="btn btn-sm <?= $fSold==='1'?'btn-secondary':'btn-outline-secondary' ?>">Sold</a>
</div>

<div class="erp-table">
  <div class="table-responsive">
    <table class="table table-hover mb-0">
      <thead>
        <tr><th>#</th><th>Ref</th><th>Seller</th><th>Device</th><th>IMEI</th>
            <th>Condition</th><th class="text-end">Buy Price</th>
            <th class="text-end">Sale Price</th><th class="text-end">Profit</th>
            <th>Status</th><th>Actions</th></tr>
      </thead>
      <tbody>
        <?php if (empty($devices)): ?>
          <tr><td colspan="11" class="text-center text-muted py-4">No devices found</td></tr>
        <?php else: ?>
          <?php foreach ($devices as $i => $d): ?>
            <?php
              $saleRow = null;
              if ($d['sold']) {
                  $sr = $pdo->prepare('SELECT * FROM second_hand_sales WHERE purchase_id=? LIMIT 1');
                  $sr->execute([$d['id']]);
                  $saleRow = $sr->fetch();
              }
            ?>
            <tr>
              <td><?= $i+1 ?></td>
              <td><code><?= clean($d['ref_no']) ?></code></td>
              <td>
                <div><?= clean($d['seller_name']) ?></div>
                <small class="text-muted"><?= clean($d['seller_phone']) ?></small>
              </td>
              <td><?= clean($d['brand_name'] ?? '') ?> <?= clean($d['model'] ?? '') ?></td>
              <td><?= clean($d['imei'] ?? '-') ?></td>
              <td>
                <?php
                  $condColors = ['excellent'=>'success','good'=>'primary','fair'=>'warning','poor'=>'danger'];
                  $cc = $condColors[$d['condition']] ?? 'secondary';
                ?>
                <span class="badge bg-<?= $cc ?>"><?= ucfirst($d['condition']) ?></span>
              </td>
              <td class="text-end"><?= money((float)$d['buy_price']) ?></td>
              <td class="text-end"><?= $saleRow ? money((float)$saleRow['sale_price']) : '-' ?></td>
              <td class="text-end <?= $saleRow ? ($saleRow['profit']>=0?'text-success':'text-danger') : '' ?>">
                <?= $saleRow ? money((float)$saleRow['profit']) : '-' ?>
              </td>
              <td>
                <?= $d['sold']
                  ? '<span class="badge bg-secondary">Sold</span>'
                  : '<span class="badge bg-success">Available</span>' ?>
              </td>
              <td>
                <?php if (!$d['sold']): ?>
                  <button class="btn btn-sm btn-success"
                          onclick="openSellModal(<?= $d['id'] ?>, '<?= clean($d['brand_name']??'') ?> <?= clean($d['model']??'') ?>', <?= $d['buy_price'] ?>)">
                    <i class="bi bi-tag"></i> Sell
                  </button>
                <?php endif; ?>
                <?php if ($d['seller_photo']): ?>
                  <a href="<?= UPLOAD_URL ?>/<?= clean($d['seller_photo']) ?>" target="_blank" class="btn btn-sm btn-outline-secondary">
                    <i class="bi bi-person-badge"></i>
                  </a>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Sell Modal -->
<div class="modal fade" id="sellModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="POST">
        <input type="hidden" name="sell_device" value="1">
        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
        <input type="hidden" name="sh_id" id="sellShId">
        <div class="modal-header bg-success text-white">
          <h5 class="modal-title" id="sellModalTitle">Sell Device</h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label">Buyer Name</label>
            <input type="text" name="buyer_name" class="form-control">
          </div>
          <div class="mb-3">
            <label class="form-label">Buyer Phone</label>
            <input type="text" name="buyer_phone" class="form-control" maxlength="15">
          </div>
          <div class="mb-3">
            <label class="form-label">Sale Price (₹)</label>
            <input type="number" name="sale_price" id="sellPrice" class="form-control" min="0" step="0.01" required>
          </div>
          <div id="profitPreview" class="alert alert-info py-2 small"></div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-success">Confirm Sale</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
let sellBuyPrice = 0;
function openSellModal(id, device, buyPrice) {
  document.getElementById('sellShId').value = id;
  document.getElementById('sellModalTitle').textContent = 'Sell: ' + device;
  document.getElementById('sellPrice').value = '';
  sellBuyPrice = buyPrice;
  updateProfitPreview();
  new bootstrap.Modal(document.getElementById('sellModal')).show();
}

document.getElementById('sellPrice')?.addEventListener('input', updateProfitPreview);

function updateProfitPreview() {
  const price  = parseFloat(document.getElementById('sellPrice')?.value) || 0;
  const profit = price - sellBuyPrice;
  const el     = document.getElementById('profitPreview');
  if (el) {
    el.className = 'alert py-2 small ' + (profit >= 0 ? 'alert-success' : 'alert-danger');
    el.textContent = 'Estimated Profit: ₹' + profit.toFixed(2);
  }
}
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
