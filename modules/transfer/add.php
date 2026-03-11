<?php
/**
 * Rani Mobiles ERP — Branch Transfer (New)
 */
require_once __DIR__ . '/../../config/auth.php';
require_auth();

$user      = current_user();
$branch_id = (int)$user['branch_id'];
$pdo       = db();

$message = $error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_transfer'])) {
    verify_csrf();

    $to_branch_id  = (int)$_POST['to_branch_id'];
    $transfer_date = $_POST['transfer_date'] ?? date('Y-m-d');
    $notes         = clean($_POST['notes'] ?? '');
    $items         = json_decode($_POST['cart_json'] ?? '[]', true);

    if (!$to_branch_id || $to_branch_id === $branch_id) {
        $error = 'Select a different destination branch.';
    } elseif (empty($items)) {
        $error = 'No items added.';
    } else {
        try {
            $pdo->beginTransaction();

            $transfer_no   = next_serial($branch_id, 'TRF');
            $total_cost    = 0;
            $transfer_price = 0;

            foreach ($items as $item) {
                $qty   = max(1, (int)$item['qty']);
                $cost  = (float)$item['cost'];
                $price = (float)$item['transfer_price'];
                $total_cost     += $cost  * $qty;
                $transfer_price += $price * $qty;
            }

            $profit  = $transfer_price - $total_cost;
            $paid    = (float)($_POST['paid'] ?? 0);
            $balance = $transfer_price - $paid;

            $pdo->prepare(
                'INSERT INTO branch_transfers
                 (transfer_no,from_branch_id,to_branch_id,transfer_date,total_cost,transfer_price,
                  profit,paid,balance,notes,created_by)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?)'
            )->execute([
                $transfer_no, $branch_id, $to_branch_id, $transfer_date,
                $total_cost, $transfer_price, $profit, $paid, $balance,
                $notes, $user['id']
            ]);
            $trfId = $pdo->lastInsertId();

            foreach ($items as $item) {
                $prod_id = (int)$item['product_id'];
                $qty     = max(1, (int)$item['qty']);
                $cost    = (float)$item['cost'];
                $price   = (float)$item['transfer_price'];
                $imei    = clean($item['imei'] ?? '');

                $pdo->prepare(
                    'INSERT INTO branch_transfer_items (transfer_id,product_id,imei,qty,cost,transfer_price)
                     VALUES (?,?,?,?,?,?)'
                )->execute([$trfId, $prod_id, $imei ?: null, $qty, $cost, $price]);

                // Deduct from source, add to destination
                update_stock($branch_id, $prod_id, -$qty);
                update_stock($to_branch_id, $prod_id, $qty);

                if ($imei) {
                    $pdo->prepare('UPDATE imei_numbers SET branch_id=?,status=? WHERE imei=?')
                        ->execute([$to_branch_id, 'in_stock', $imei]);
                }
            }

            $pdo->commit();
            log_activity('BRANCH_TRANSFER', "Ref: $transfer_no to branch $to_branch_id");
            $message = $transfer_no;

        } catch (Throwable $e) {
            $pdo->rollBack();
            $error = 'Transfer failed: ' . $e->getMessage();
        }
    }
}

$branches = $pdo->query(
    'SELECT id, code, name FROM branches WHERE is_active=1 ORDER BY code'
)->fetchAll();

$products = $pdo->prepare(
    'SELECT p.id, p.name, p.model, p.purchase_cost, p.sale_price, p.imei_tracking,
            br.name AS brand,
            COALESCE(s.qty, 0) AS qty
     FROM products p
     JOIN brands br ON br.id=p.brand_id
     LEFT JOIN stock s ON s.product_id=p.id AND s.branch_id=?
     WHERE p.is_active=1 AND COALESCE(s.qty, 0) > 0
     ORDER BY br.name, p.name'
);
$products->execute([$branch_id]);
$products = $products->fetchAll();

$pageTitle = 'Branch Transfer';
?>
<?php include __DIR__ . '/../../includes/header.php'; ?>
<script>window.BASE_URL = '<?= BASE_URL ?>';</script>

<?php if ($message): ?>
<div class="alert alert-success">
  <i class="bi bi-check-circle me-2"></i>Transfer created — Ref: <strong><?= clean($message) ?></strong>
  <a href="<?= BASE_URL ?>/modules/transfer/list.php" class="ms-2 btn btn-sm btn-outline-success">View List</a>
</div>
<?php endif; ?>
<?php if ($error): ?>
  <div class="alert alert-danger"><?= clean($error) ?></div>
<?php endif; ?>

<div class="page-header">
  <h1 class="page-title"><i class="bi bi-arrow-left-right me-2"></i>Branch Transfer</h1>
  <a href="<?= BASE_URL ?>/modules/transfer/list.php" class="btn btn-outline-secondary btn-sm">Transfer List</a>
</div>

<form method="POST" id="transferForm">
  <input type="hidden" name="save_transfer" value="1">
  <input type="hidden" name="csrf_token"    value="<?= csrf_token() ?>">
  <input type="hidden" name="cart_json"     id="cartJson">

  <div class="row g-3">
    <!-- Items -->
    <div class="col-lg-8">
      <div class="erp-form-card">
        <div class="card-heading"><i class="bi bi-plus-circle"></i>Add Products</div>

        <div class="row g-2 align-items-end mb-3">
          <div class="col-md-4">
            <label class="form-label">Product</label>
            <select id="tSelProduct" class="form-select">
              <option value="">Select…</option>
              <?php foreach ($products as $p): ?>
                <option value="<?= $p['id'] ?>"
                        data-cost="<?= $p['purchase_cost'] ?>"
                        data-price="<?= $p['sale_price'] ?>"
                        data-imei="<?= $p['imei_tracking'] ?>"
                        data-qty="<?= $p['qty'] ?>"
                        data-name="<?= clean($p['brand'].' '.$p['name'].' '.($p['model']??'')) ?>">
                  <?= clean($p['brand'].' '.$p['name'].' '.($p['model']??'')) ?> (Qty: <?= $p['qty'] ?>)
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-2">
            <label class="form-label">Qty</label>
            <input type="number" id="tInpQty" class="form-control" min="1" value="1">
          </div>
          <div class="col-md-2">
            <label class="form-label">Our Cost</label>
            <input type="number" id="tInpCost" class="form-control" step="0.01" placeholder="0.00">
          </div>
          <div class="col-md-2">
            <label class="form-label">Transfer Price</label>
            <input type="number" id="tInpPrice" class="form-control" step="0.01" placeholder="0.00">
          </div>
          <div class="col-md-2">
            <button type="button" class="btn btn-primary w-100" onclick="addTrfItem()">
              <i class="bi bi-plus"></i> Add
            </button>
          </div>
          <div id="tImeiSection" style="display:none" class="col-12">
            <label class="form-label">IMEI</label>
            <input type="text" id="tInpImei" class="form-control" placeholder="IMEI number">
          </div>
        </div>

        <div class="table-responsive">
          <table class="table table-sm">
            <thead class="table-dark">
              <tr><th>#</th><th>Product</th><th>Cost</th><th>Transfer Price</th><th>Qty</th><th>Profit</th><th></th></tr>
            </thead>
            <tbody id="trfCartBody">
              <tr id="trfEmpty"><td colspan="7" class="text-center text-muted py-3">No items</td></tr>
            </tbody>
            <tfoot id="trfFoot" style="display:none">
              <tr class="table-info fw-bold">
                <td colspan="3">Totals</td>
                <td id="trfTotalPrice">₹0</td>
                <td></td>
                <td id="trfTotalProfit">₹0</td>
                <td></td>
              </tr>
            </tfoot>
          </table>
        </div>
      </div>
    </div>

    <!-- Right: Transfer details -->
    <div class="col-lg-4">
      <div class="erp-form-card">
        <div class="card-heading"><i class="bi bi-arrow-right-circle"></i>Transfer Details</div>
        <div class="mb-2">
          <label class="form-label">From Branch</label>
          <input type="text" class="form-control" value="<?= clean($user['branch']) ?>" disabled>
        </div>
        <div class="mb-2">
          <label class="form-label">To Branch *</label>
          <select name="to_branch_id" class="form-select" required>
            <option value="">Select destination…</option>
            <?php foreach ($branches as $b): ?>
              <?php if ($b['id'] !== $branch_id): ?>
                <option value="<?= $b['id'] ?>"><?= clean($b['code']) ?> — <?= clean($b['name']) ?></option>
              <?php endif; ?>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="mb-2">
          <label class="form-label">Transfer Date</label>
          <input type="date" name="transfer_date" class="form-control" value="<?= date('Y-m-d') ?>">
        </div>
        <div class="mb-2">
          <label class="form-label">Amount Received</label>
          <input type="number" name="paid" class="form-control" value="0" min="0" step="0.01">
        </div>
        <div class="mb-2">
          <label class="form-label">Notes</label>
          <textarea name="notes" class="form-control" rows="2"></textarea>
        </div>
      </div>
      <button type="submit" class="btn btn-success btn-lg w-100" onclick="return prepareTransfer()">
        <i class="bi bi-send me-2"></i>Send Transfer
      </button>
    </div>
  </div>
</form>

<script>
let trfCart = [];

document.getElementById('tSelProduct').addEventListener('change', function() {
  const opt = this.options[this.selectedIndex];
  document.getElementById('tInpCost').value   = opt.dataset.cost  || '';
  document.getElementById('tInpPrice').value  = opt.dataset.price || '';
  document.getElementById('tImeiSection').style.display = opt.dataset.imei == '1' ? 'block' : 'none';
});

function addTrfItem() {
  const sel   = document.getElementById('tSelProduct');
  const id    = sel.value;
  const name  = sel.options[sel.selectedIndex]?.dataset?.name || '';
  if (!id) { showToast('Select a product', 'warning'); return; }

  const qty   = parseInt(document.getElementById('tInpQty').value)   || 1;
  const cost  = parseFloat(document.getElementById('tInpCost').value) || 0;
  const price = parseFloat(document.getElementById('tInpPrice').value)|| 0;
  const imei  = document.getElementById('tInpImei').value.trim();

  trfCart.push({ product_id: id, name, qty, cost, transfer_price: price, imei });
  renderTrfCart();

  sel.value = '';
  document.getElementById('tInpImei').value = '';
  document.getElementById('tImeiSection').style.display = 'none';
}

function removeTrfItem(i) { trfCart.splice(i,1); renderTrfCart(); }

function renderTrfCart() {
  const tbody = document.getElementById('trfCartBody');
  const foot  = document.getElementById('trfFoot');
  if (!trfCart.length) {
    tbody.innerHTML = '<tr id="trfEmpty"><td colspan="7" class="text-center text-muted py-3">No items</td></tr>';
    foot.style.display='none'; return;
  }
  let totalPrice=0, totalProfit=0;
  tbody.innerHTML = trfCart.map((item,i) => {
    const profit = (item.transfer_price - item.cost) * item.qty;
    totalPrice  += item.transfer_price * item.qty;
    totalProfit += profit;
    return `<tr>
      <td>${i+1}</td>
      <td>${item.name}${item.imei?'<br><small>'+item.imei+'</small>':''}</td>
      <td>₹${item.cost}</td>
      <td>₹${item.transfer_price}</td>
      <td>${item.qty}</td>
      <td class="${profit>=0?'text-success':'text-danger'}">₹${profit.toFixed(2)}</td>
      <td><button type="button" class="btn btn-sm btn-outline-danger" onclick="removeTrfItem(${i})"><i class="bi bi-trash"></i></button></td>
    </tr>`;
  }).join('');
  document.getElementById('trfTotalPrice').textContent  = '₹' + totalPrice.toFixed(2);
  document.getElementById('trfTotalProfit').textContent = '₹' + totalProfit.toFixed(2);
  foot.style.display = '';
}

function prepareTransfer() {
  if (!trfCart.length) { showToast('No items added!', 'error'); return false; }
  document.getElementById('cartJson').value = JSON.stringify(trfCart);
  return true;
}
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
