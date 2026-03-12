<?php
/**
 * Rani Mobiles ERP — New Purchase
 */
require_once __DIR__ . '/../../config/auth.php';
require_auth();

$user      = current_user();
$branch_id = (int)$user['branch_id'];
$pdo       = db();

$message = $error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_purchase'])) {
    verify_csrf();

    $vendor_name    = clean($_POST['vendor_name'] ?? '');
    $vendor_invoice = clean($_POST['vendor_invoice'] ?? '');
    $purchase_date  = $_POST['purchase_date'] ?? date('Y-m-d');
    $payment_mode   = clean($_POST['payment_mode'] ?? 'cash');
    $paid           = (float)($_POST['paid'] ?? 0);
    $notes          = clean($_POST['notes'] ?? '');
    $items          = json_decode($_POST['cart_json'] ?? '[]', true);

    if (empty($items)) {
        $error = 'No items added.';
    } else {
        try {
            $pdo->beginTransaction();

            // Upsert vendor
            $vid = null;
            if ($vendor_name) {
                $vs = $pdo->prepare('SELECT id FROM vendors WHERE name=?');
                $vs->execute([$vendor_name]);
                $vid = $vs->fetchColumn();
                if (!$vid) {
                    $pdo->prepare('INSERT INTO vendors (name) VALUES (?)')->execute([$vendor_name]);
                    $vid = $pdo->lastInsertId();
                }
            }

            $invoice_no = next_serial($branch_id, 'PUR');
            $subtotal   = 0;
            $gst_total  = 0;

            foreach ($items as $item) {
                $qty     = max(1, (int)$item['qty']);
                $cost    = (float)$item['cost'];
                $gst_p   = (float)($item['gst_percent'] ?? 0);
                $gst_l   = $cost * $qty * ($gst_p / 100);
                $subtotal  += $cost * $qty;
                $gst_total += $gst_l;
            }

            $total   = $subtotal + $gst_total;
            $balance = $total - $paid;

            $pdo->prepare(
                'INSERT INTO purchases
                 (invoice_no,branch_id,vendor_id,vendor_name,vendor_invoice,purchase_date,
                  subtotal,gst_amount,total,paid,balance,payment_mode,notes,created_by)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)'
            )->execute([
                $invoice_no, $branch_id, $vid ?: null, $vendor_name, $vendor_invoice,
                $purchase_date, $subtotal, $gst_total, $total, $paid, $balance,
                $payment_mode, $notes, $user['id']
            ]);
            $pur_id = $pdo->lastInsertId();

            foreach ($items as $item) {
                $prod_id = (int)$item['product_id'];
                $qty     = max(1, (int)$item['qty']);
                $cost    = (float)$item['cost'];
                $gst_p   = (float)($item['gst_percent'] ?? 0);
                $gst_l   = $cost * $qty * ($gst_p / 100);

                $pdo->prepare(
                    'INSERT INTO purchase_items (purchase_id,product_id,qty,cost,gst_percent,gst_amount,total)
                     VALUES (?,?,?,?,?,?,?)'
                )->execute([$pur_id, $prod_id, $qty, $cost, $gst_p, $gst_l, ($cost*$qty)+$gst_l]);

                // Add stock
                update_stock($branch_id, $prod_id, $qty);

                // Update purchase cost in product
                $pdo->prepare('UPDATE products SET purchase_cost=? WHERE id=?')->execute([$cost, $prod_id]);

                // IMEI numbers - with duplicate detection
                $imeisRaw = trim($item['imeis'] ?? '');
                if ($imeisRaw) {
                    $duplicateImeis = [];
                    foreach (preg_split('/[\s,\n]+/', $imeisRaw) as $imei) {
                        $imei = trim($imei);
                        if (!$imei) continue;
                        // Check for duplicate IMEI
                        $imeiCheckStmt = $pdo->prepare('SELECT id, status FROM imei_numbers WHERE imei=?');
                        $imeiCheckStmt->execute([$imei]);
                        $existing = $imeiCheckStmt->fetch();
                        if ($existing) {
                            $duplicateImeis[] = $imei . ' (status: ' . $existing['status'] . ')';
                            continue;
                        }
                        $pdo->prepare(
                            'INSERT INTO imei_numbers (product_id,branch_id,imei,purchase_id)
                             VALUES (?,?,?,?)'
                        )->execute([$prod_id, $branch_id, $imei, $pur_id]);
                    }
                    if ($duplicateImeis) {
                        throw new RuntimeException(
                            'Duplicate IMEI(s) detected — already in system: ' . implode(', ', $duplicateImeis)
                        );
                    }
                }
            }

            // Update vendor balance
            if ($vid && $balance > 0) {
                $pdo->prepare('UPDATE vendors SET balance=balance+? WHERE id=?')->execute([$balance, $vid]);
            }

            $pdo->commit();
            log_activity('CREATE_PURCHASE', "Invoice: $invoice_no, Total: $total");
            $message = $invoice_no;

        } catch (Throwable $e) {
            $pdo->rollBack();
            $error = 'Purchase failed: ' . $e->getMessage();
        }
    }
}

$products = $pdo->query(
    'SELECT p.id, p.name, p.model, p.purchase_cost, p.gst_percent, p.imei_tracking,
            br.name AS brand
     FROM products p JOIN brands br ON br.id=p.brand_id
     WHERE p.is_active=1 ORDER BY br.name, p.name'
)->fetchAll();

$pageTitle = 'New Purchase';
?>
<?php include __DIR__ . '/../../includes/header.php'; ?>
<script>window.BASE_URL = '<?= BASE_URL ?>';</script>

<?php if ($message): ?>
<div class="alert alert-success d-flex align-items-center gap-2">
  <i class="bi bi-check-circle-fill"></i>
  <div>Purchase saved — Invoice: <strong><?= clean($message) ?></strong>
    <a href="<?= BASE_URL ?>/modules/purchase/list.php" class="ms-2 btn btn-sm btn-outline-success">View List</a>
  </div>
</div>
<?php endif; ?>

<?php if ($error): ?>
  <div class="alert alert-danger"><i class="bi bi-exclamation-triangle me-2"></i><?= clean($error) ?></div>
<?php endif; ?>

<div class="page-header">
  <h1 class="page-title"><i class="bi bi-truck me-2"></i>New Purchase</h1>
  <a href="<?= BASE_URL ?>/modules/purchase/list.php" class="btn btn-outline-secondary btn-sm">Purchase List</a>
</div>

<form method="POST" id="purchaseForm">
  <input type="hidden" name="save_purchase" value="1">
  <input type="hidden" name="csrf_token"    value="<?= csrf_token() ?>">
  <input type="hidden" name="cart_json"     id="cartJson">

  <div class="row g-3">
    <!-- Left: Items -->
    <div class="col-lg-8">
      <div class="erp-form-card">
        <div class="card-heading"><i class="bi bi-plus-circle"></i>Add Items</div>

        <!-- Add item row -->
        <div class="row g-2 align-items-end mb-3" id="addItemRow">
          <div class="col-md-4">
            <label class="form-label">Product</label>
            <select id="selProduct" class="form-select">
              <option value="">Select product…</option>
              <?php foreach ($products as $p): ?>
                <option value="<?= $p['id'] ?>"
                        data-cost="<?= $p['purchase_cost'] ?>"
                        data-gst="<?= $p['gst_percent'] ?>"
                        data-imei="<?= $p['imei_tracking'] ?>"
                        data-name="<?= clean($p['brand'] . ' ' . $p['name'] . ' ' . ($p['model']??'')) ?>">
                  <?= clean($p['brand'] . ' ' . $p['name'] . ' ' . ($p['model']??'')) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-2">
            <label class="form-label">Cost (₹)</label>
            <input type="number" id="inpCost" class="form-control" min="0" step="0.01" placeholder="0.00">
          </div>
          <div class="col-md-2">
            <label class="form-label">Qty</label>
            <input type="number" id="inpQty" class="form-control" min="1" value="1">
          </div>
          <div class="col-md-2">
            <label class="form-label">GST %</label>
            <input type="number" id="inpGst" class="form-control" min="0" max="28" step="0.01" value="18">
          </div>
          <div class="col-md-2">
            <button type="button" class="btn btn-primary w-100" onclick="addPurchaseItem()">
              <i class="bi bi-plus"></i> Add
            </button>
          </div>
          <div class="col-12" id="imeiSection" style="display:none">
            <label class="form-label">IMEI Numbers (one per line or comma-separated)</label>
            <textarea id="inpImeis" class="form-control" rows="2" placeholder="Enter IMEI numbers…"></textarea>
          </div>
        </div>

        <!-- Cart -->
        <div class="table-responsive">
          <table class="table table-sm">
            <thead class="table-dark">
              <tr><th>#</th><th>Product</th><th>Cost</th><th>Qty</th><th>GST%</th><th>Total</th><th></th></tr>
            </thead>
            <tbody id="purchaseCartBody">
              <tr id="emptyRow"><td colspan="7" class="text-center text-muted py-3">No items added</td></tr>
            </tbody>
            <tfoot>
              <tr class="fw-bold">
                <td colspan="5" class="text-end">Total:</td>
                <td id="purTotal">₹0.00</td>
                <td></td>
              </tr>
            </tfoot>
          </table>
        </div>
      </div>
    </div>

    <!-- Right: Vendor + Payment -->
    <div class="col-lg-4">
      <div class="erp-form-card">
        <div class="card-heading"><i class="bi bi-shop"></i>Vendor Details</div>
        <div class="mb-2">
          <label class="form-label">Vendor Name</label>
          <input type="text" name="vendor_name" class="form-control" placeholder="Vendor name" list="vendorsList">
          <datalist id="vendorsList">
            <?php foreach ($pdo->query('SELECT name FROM vendors ORDER BY name') as $v): ?>
              <option value="<?= clean($v['name']) ?>">
            <?php endforeach; ?>
          </datalist>
        </div>
        <div class="mb-2">
          <label class="form-label">Vendor Invoice #</label>
          <input type="text" name="vendor_invoice" class="form-control" placeholder="Vendor invoice number">
        </div>
        <div class="mb-2">
          <label class="form-label">Purchase Date</label>
          <input type="date" name="purchase_date" class="form-control" value="<?= date('Y-m-d') ?>">
        </div>
      </div>

      <div class="erp-form-card">
        <div class="card-heading"><i class="bi bi-cash"></i>Payment</div>
        <div class="mb-2">
          <label class="form-label">Payment Mode</label>
          <select name="payment_mode" class="form-select">
            <option value="cash">Cash</option>
            <option value="upi">UPI</option>
            <option value="card">Card</option>
            <option value="bank">Bank Transfer</option>
            <option value="credit">Credit</option>
          </select>
        </div>
        <div class="mb-2">
          <label class="form-label">Amount Paid</label>
          <input type="number" name="paid" class="form-control" value="0" min="0" step="0.01">
        </div>
        <div class="mb-2">
          <label class="form-label">Notes</label>
          <textarea name="notes" class="form-control" rows="2" placeholder="Optional…"></textarea>
        </div>
      </div>

      <button type="submit" class="btn btn-success btn-lg w-100" onclick="return preparePurchase()">
        <i class="bi bi-check-circle me-2"></i>Save Purchase
      </button>
    </div>
  </div>
</form>

<script>
let purCart = [];

document.getElementById('selProduct').addEventListener('change', function() {
  const opt = this.options[this.selectedIndex];
  document.getElementById('inpCost').value = opt.dataset.cost || '';
  document.getElementById('inpGst').value  = opt.dataset.gst  || '18';
  document.getElementById('imeiSection').style.display = opt.dataset.imei == '1' ? 'block' : 'none';
});

function addPurchaseItem() {
  const sel  = document.getElementById('selProduct');
  const id   = sel.value;
  const name = sel.options[sel.selectedIndex]?.dataset?.name || '';
  if (!id) { showToast('Select a product', 'warning'); return; }

  const cost  = parseFloat(document.getElementById('inpCost').value) || 0;
  const qty   = parseInt(document.getElementById('inpQty').value)    || 1;
  const gst   = parseFloat(document.getElementById('inpGst').value)  || 0;
  const imeis = document.getElementById('inpImeis').value;

  const gst_amt  = cost * qty * (gst / 100);
  const lineTotal = cost * qty + gst_amt;

  purCart.push({ product_id: id, name, cost, qty, gst_percent: gst, imeis, total: lineTotal });
  renderPurCart();

  // Reset
  sel.value = '';
  document.getElementById('inpCost').value = '';
  document.getElementById('inpQty').value  = 1;
  document.getElementById('inpImeis').value = '';
  document.getElementById('imeiSection').style.display = 'none';
}

function removePurItem(i) {
  purCart.splice(i, 1);
  renderPurCart();
}

function renderPurCart() {
  const tbody = document.getElementById('purchaseCartBody');
  const empty = document.getElementById('emptyRow');

  if (!purCart.length) {
    tbody.innerHTML = '<tr id="emptyRow"><td colspan="7" class="text-center text-muted py-3">No items added</td></tr>';
    document.getElementById('purTotal').textContent = '₹0.00';
    return;
  }

  let total = 0;
  tbody.innerHTML = purCart.map((item, i) => {
    total += item.total;
    return `<tr>
      <td>${i+1}</td>
      <td>${item.name}${item.imeis ? '<br><small class="text-muted">' + item.imeis.split(/[\s,\n]+/).filter(Boolean).length + ' IMEIs</small>' : ''}</td>
      <td>₹${item.cost.toLocaleString('en-IN')}</td>
      <td>${item.qty}</td>
      <td>${item.gst_percent}%</td>
      <td>₹${item.total.toLocaleString('en-IN', {minimumFractionDigits:2})}</td>
      <td><button type="button" class="btn btn-sm btn-outline-danger" onclick="removePurItem(${i})"><i class="bi bi-trash"></i></button></td>
    </tr>`;
  }).join('');

  document.getElementById('purTotal').textContent = '₹' + total.toLocaleString('en-IN', {minimumFractionDigits:2});
}

function preparePurchase() {
  if (!purCart.length) { showToast('No items added!', 'error'); return false; }
  document.getElementById('cartJson').value = JSON.stringify(purCart);
  return true;
}
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
