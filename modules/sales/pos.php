<?php
/**
 * Rani Mobiles ERP — POS / New Sale
 */
require_once __DIR__ . '/../../config/auth.php';
require_auth();

$user      = current_user();
$branch_id = (int)$user['branch_id'];
$pdo       = db();

// Handle form submission
$message = $error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_sale'])) {
    verify_csrf();

    $customer_name  = clean($_POST['customer_name'] ?? '');
    $customer_phone = clean($_POST['customer_phone'] ?? '');
    $is_gst         = (int)($_POST['is_gst'] ?? 0);
    $paid_cash      = (float)($_POST['paid_cash'] ?? 0);
    $paid_upi       = (float)($_POST['paid_upi'] ?? 0);
    $paid_card      = (float)($_POST['paid_card'] ?? 0);
    $paid_credit    = (float)($_POST['paid_credit'] ?? 0);
    $notes          = clean($_POST['notes'] ?? '');
    $items          = json_decode($_POST['cart_json'] ?? '[]', true);

    if (empty($items)) {
        $error = 'Cart is empty. Add items to proceed.';
    } else {
        try {
            $pdo->beginTransaction();

            // Upsert customer
            $cust_id = null;
            if ($customer_phone) {
                $cs = $pdo->prepare('SELECT id FROM customers WHERE phone=?');
                $cs->execute([$customer_phone]);
                $cust_id = $cs->fetchColumn();
                if (!$cust_id) {
                    $pdo->prepare('INSERT INTO customers (name,phone) VALUES (?,?)')->execute([$customer_name, $customer_phone]);
                    $cust_id = $pdo->lastInsertId();
                }
            }

            $invoice_no = next_serial($branch_id, 'SAL');
            $subtotal = $gst_amount = 0;

            foreach ($items as $item) {
                $qty  = max(1, (int)$item['qty']);
                $price = (float)$item['sale_price'];
                $disc  = (float)($item['discount'] ?? 0);
                $gst_p = $is_gst ? (float)($item['gst_percent'] ?? 0) : 0;
                $line  = ($price * $qty) - $disc;
                $gst_l = $line * ($gst_p / 100);
                $subtotal   += $line;
                $gst_amount += $gst_l;
            }

            $total = $subtotal + $gst_amount;

            $pdo->prepare(
                'INSERT INTO sales (invoice_no,branch_id,customer_id,customer_name,customer_phone,
                 sale_date,subtotal,gst_amount,total,paid_cash,paid_upi,paid_card,paid_credit,
                 is_gst_invoice,notes,created_by)
                 VALUES (?,?,?,?,?,CURDATE(),?,?,?,?,?,?,?,?,?,?)'
            )->execute([
                $invoice_no, $branch_id, $cust_id ?: null, $customer_name, $customer_phone,
                $subtotal, $gst_amount, $total, $paid_cash, $paid_upi, $paid_card, $paid_credit,
                $is_gst, $notes, $user['id']
            ]);
            $sale_id = $pdo->lastInsertId();

            foreach ($items as $item) {
                $prod_id = (int)$item['id'];
                $qty     = max(1, (int)$item['qty']);
                $price   = (float)$item['sale_price'];
                $disc    = (float)($item['discount'] ?? 0);
                $gst_p   = $is_gst ? (float)($item['gst_percent'] ?? 0) : 0;
                $line    = ($price * $qty) - $disc;
                $gst_l   = $line * ($gst_p / 100);
                $imei    = clean($item['imei'] ?? '');

                $pdo->prepare(
                    'INSERT INTO sale_items (sale_id,product_id,imei,qty,unit_price,discount,gst_percent,gst_amount,total)
                     VALUES (?,?,?,?,?,?,?,?,?)'
                )->execute([$sale_id, $prod_id, $imei ?: null, $qty, $price, $disc, $gst_p, $gst_l, $line + $gst_l]);

                // Deduct stock
                update_stock($branch_id, $prod_id, -$qty);

                // Update IMEI status
                if ($imei) {
                    $pdo->prepare('UPDATE imei_numbers SET status=?,sale_id=? WHERE imei=?')
                        ->execute(['sold', $sale_id, $imei]);
                }
            }

            // Update customer totals
            if ($cust_id) {
                $pdo->prepare('UPDATE customers SET total_purchases=total_purchases+?, last_purchase=CURDATE() WHERE id=?')
                    ->execute([$total, $cust_id]);
            }

            $pdo->commit();
            log_activity('CREATE_SALE', "Invoice: $invoice_no, Total: $total");
            $message = $invoice_no;

        } catch (Throwable $e) {
            $pdo->rollBack();
            $error = 'Sale failed: ' . $e->getMessage();
        }
    }
}

$pageTitle = 'POS — New Sale';
?>
<?php include __DIR__ . '/../../includes/header.php'; ?>
<script>window.BASE_URL = '<?= BASE_URL ?>';</script>

<!-- Success modal after sale saved -->
<?php if ($message): ?>
<div class="modal fade show d-block" tabindex="-1" style="background:rgba(0,0,0,.5)">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content text-center p-4">
      <div style="font-size:4rem">✅</div>
      <h4 class="text-success fw-bold mt-2">Sale Saved!</h4>
      <p class="text-muted">Invoice: <strong><?= clean($message) ?></strong></p>
      <div class="d-flex gap-2 justify-content-center flex-wrap">
        <a href="<?= BASE_URL ?>/modules/sales/invoice.php?no=<?= urlencode($message) ?>" target="_blank" class="btn btn-primary">
          <i class="bi bi-printer me-1"></i>Print Invoice
        </a>
        <?php
          $invoiceUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http')
                        . '://' . APP_HOST . BASE_URL
                        . '/modules/sales/invoice.php?no=' . urlencode($message);
          $waMsg = urlencode("Your invoice from Rani Mobiles: " . $invoiceUrl);
        ?>
        <a href="https://wa.me/?text=<?= $waMsg ?>" target="_blank" class="btn btn-success">
          <i class="bi bi-whatsapp me-1"></i>WhatsApp Bill
        </a>
        <a href="<?= BASE_URL ?>/modules/sales/invoice.php?no=<?= urlencode($message) ?>&pdf=1" target="_blank" class="btn btn-outline-danger">
          <i class="bi bi-file-pdf me-1"></i>PDF Bill
        </a>
        <a href="<?= BASE_URL ?>/modules/sales/pos.php" class="btn btn-outline-success">
          <i class="bi bi-plus me-1"></i>New Sale
        </a>
      </div>
    </div>
  </div>
</div>
<?php endif; ?>

<div class="page-header">
  <div>
    <h1 class="page-title"><i class="bi bi-cart3 me-2"></i>POS — Point of Sale</h1>
    <p class="page-subtitle">Branch: <?= clean($user['branch']) ?></p>
  </div>
  <a href="<?= BASE_URL ?>/modules/sales/list.php" class="btn btn-outline-secondary btn-sm">
    <i class="bi bi-list-ul me-1"></i>Sales List
  </a>
</div>

<?php if ($error): ?>
  <div class="alert alert-danger"><i class="bi bi-exclamation-triangle me-2"></i><?= clean($error) ?></div>
<?php endif; ?>

<form id="posForm" method="POST">
  <input type="hidden" name="save_sale" value="1">
  <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
  <input type="hidden" name="cart_json" id="cartJson">

  <div class="row g-3">
    <!-- Left: Product Search + Cart -->
    <div class="col-lg-8">
      <!-- Search bar -->
      <div class="pos-search-wrap">
        <div class="row g-2 align-items-center">
          <div class="col">
            <div class="input-group">
              <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
              <input type="text" id="productSearch" class="form-control form-control-lg"
                     placeholder="Search product, brand, barcode, IMEI or price (e.g. 8000)..."
                     autocomplete="off">
            </div>
          </div>
          <div class="col-auto">
            <button type="button" class="btn btn-warning btn-lg" onclick="openScanner()" title="Scan barcode">
              <i class="bi bi-upc-scan"></i>
            </button>
          </div>
        </div>

        <!-- Search results dropdown -->
        <div id="searchResults" class="mt-2" style="display:none">
          <div class="bg-white rounded-3 shadow" id="searchList" style="max-height:300px;overflow-y:auto"></div>
        </div>
      </div>

      <!-- Cart table -->
      <div class="erp-table">
        <div class="table-responsive">
          <table class="table mb-0">
            <thead>
              <tr>
                <th>#</th>
                <th>Product</th>
                <th>Price</th>
                <th>Qty</th>
                <th>Discount</th>
                <th>Total</th>
                <th></th>
              </tr>
            </thead>
            <tbody id="posCartBody">
              <tr><td colspan="7" class="text-center text-muted py-4">
                <i class="bi bi-cart-x fs-2"></i><br>Cart is empty</td></tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Customer info -->
      <div class="erp-form-card mt-3">
        <div class="card-heading"><i class="bi bi-person"></i>Customer Details (Optional)</div>
        <div class="row g-2">
          <div class="col-md-6">
            <label class="form-label">Customer Name</label>
            <input type="text" name="customer_name" class="form-control" placeholder="Walk-in Customer" id="custName">
          </div>
          <div class="col-md-6">
            <label class="form-label">Phone Number</label>
            <div class="input-group">
              <input type="text" name="customer_phone" class="form-control" placeholder="10-digit phone" id="custPhone"
                     pattern="[0-9]{10}" maxlength="10"
                     oninput="lookupCustomer(this.value)">
              <span class="input-group-text" id="custLookupSpinner" style="display:none">
                <div class="spinner-border spinner-border-sm" role="status"></div>
              </span>
            </div>
          </div>
        </div>
        <div class="mt-2">
          <label class="form-label">Notes</label>
          <textarea name="notes" class="form-control" rows="2" placeholder="Optional notes…"></textarea>
        </div>
      </div>
    </div><!-- /col-lg-8 -->

    <!-- Right: Payment + Summary -->
    <div class="col-lg-4">
      <div class="erp-form-card">
        <div class="card-heading"><i class="bi bi-receipt"></i>Bill Summary</div>

        <!-- GST toggle -->
        <div class="form-check form-switch mb-3">
          <input type="checkbox" name="is_gst" value="1" id="gstToggle" class="form-check-input"
                 onchange="POS.gstEnabled=this.checked; POS.render();">
          <label class="form-check-label fw-semibold" for="gstToggle">GST Invoice</label>
        </div>

        <table class="table table-sm mb-0">
          <tr><td>Subtotal</td><td class="text-end fw-semibold" id="posSubtotal">₹0.00</td></tr>
          <tr><td>GST</td>    <td class="text-end" id="posGST">₹0.00</td></tr>
          <tr class="table-primary">
            <td class="fw-bold fs-5">Total</td>
            <td class="text-end fw-bold fs-5" id="posTotal">₹0.00</td>
          </tr>
        </table>
      </div>

      <!-- Payment modes -->
      <div class="erp-form-card">
        <div class="card-heading"><i class="bi bi-credit-card-2-front"></i>Payment</div>

        <ul class="nav nav-pills payment-tab mb-3" id="paymentTabs">
          <li class="nav-item"><button type="button" class="nav-link active" data-mode="cash">💵 Cash</button></li>
          <li class="nav-item"><button type="button" class="nav-link" data-mode="upi">📱 UPI</button></li>
          <li class="nav-item"><button type="button" class="nav-link" data-mode="card">💳 Card</button></li>
          <li class="nav-item"><button type="button" class="nav-link" data-mode="credit">📋 Credit</button></li>
        </ul>

        <div id="paymentInputs">
          <div id="cashInput" class="pay-block">
            <label class="form-label">Cash Amount</label>
            <input type="number" name="paid_cash" id="inpCash" class="form-control" value="0" min="0" step="0.01"
                   oninput="calcChange()">
          </div>
          <div id="upiInput" class="pay-block" style="display:none">
            <label class="form-label">UPI Amount</label>
            <input type="number" name="paid_upi" id="inpUpi" class="form-control" value="0" min="0" step="0.01">
          </div>
          <div id="cardInput" class="pay-block" style="display:none">
            <label class="form-label">Card Amount</label>
            <input type="number" name="paid_card" id="inpCard" class="form-control" value="0" min="0" step="0.01">
          </div>
          <div id="creditInput" class="pay-block" style="display:none">
            <label class="form-label">Credit Amount</label>
            <input type="number" name="paid_credit" id="inpCredit" class="form-control" value="0" min="0" step="0.01">
          </div>
        </div>

        <div class="mt-3 p-2 bg-light rounded">
          <div class="d-flex justify-content-between">
            <span>Total Payable</span><strong id="payTotal">₹0.00</strong>
          </div>
          <div class="d-flex justify-content-between mt-1 text-success">
            <span>Change</span><strong id="changeAmt">₹0.00</strong>
          </div>
        </div>
      </div>

      <button type="submit" class="btn btn-success btn-lg w-100" id="saveSaleBtn" onclick="return prepareSave()">
        <i class="bi bi-check-circle me-2"></i>Save Sale
      </button>
    </div><!-- /col-lg-4 -->
  </div><!-- /row -->
</form>

<!-- Scanner Modal -->
<div class="modal fade" id="scannerModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header bg-dark text-white">
        <h5 class="modal-title"><i class="bi bi-upc-scan me-2"></i>Scan Barcode / IMEI</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" onclick="Scanner.stop()"></button>
      </div>
      <div class="modal-body text-center p-0">
        <video id="scannerVideo" style="width:100%;max-height:320px;background:#000" autoplay muted playsinline></video>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" onclick="Scanner.stop()">Cancel</button>
      </div>
    </div>
  </div>
</div>

<script src="https://unpkg.com/@zxing/library@latest/umd/index.min.js"></script>
<script>
const branch_id = <?= $branch_id ?>;

// ── Payment tab toggle ─────────────────────────────────────
document.querySelectorAll('#paymentTabs .nav-link').forEach(btn => {
  btn.addEventListener('click', () => {
    document.querySelectorAll('#paymentTabs .nav-link').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    const mode = btn.dataset.mode;
    document.querySelectorAll('.pay-block').forEach(b => b.style.display='none');
    document.getElementById(mode + 'Input').style.display='block';
  });
});

function calcChange() {
  const total = POS.getTotal();
  const paid  = parseFloat(document.getElementById('inpCash').value)||0;
  const change = Math.max(0, paid - total);
  document.getElementById('changeAmt').textContent = formatMoney(change);
}

// ── Product search ─────────────────────────────────────────
const searchInput   = document.getElementById('productSearch');
const searchResults = document.getElementById('searchResults');
const searchList    = document.getElementById('searchList');

searchInput.addEventListener('input', debounce(async (e) => {
  const q = e.target.value.trim();
  if (q.length < 2) { searchResults.style.display='none'; return; }
  try {
    const data = await erpFetch(`${BASE_URL}/api/search_product.php?q=${encodeURIComponent(q)}&branch=${branch_id}`);
    renderResults(data.products || []);
  } catch(err) { console.error(err); }
}, 300));

function renderResults(products) {
  if (!products.length) {
    searchList.innerHTML = '<div class="p-3 text-muted text-center">No products found</div>';
    searchResults.style.display='block'; return;
  }
  searchList.innerHTML = products.map(p => `
    <div class="d-flex align-items-center gap-3 p-3 border-bottom cursor-pointer search-item"
         onclick='addFromSearch(${JSON.stringify(p)})' style="cursor:pointer">
      <div>
        <div class="fw-semibold">${p.name} <span class="text-muted small">${p.model||''}</span></div>
        <div class="small text-muted">${p.brand} · ${p.barcode||''}</div>
      </div>
      <div class="ms-auto text-end">
        <div class="fw-bold text-success">₹${parseFloat(p.sale_price).toLocaleString('en-IN')}</div>
        <div class="small ${parseInt(p.stock)<=0?'text-danger':parseInt(p.stock)<=parseInt(p.min_stock)?'text-warning':'text-success'}">
          Stock: ${p.stock}
        </div>
      </div>
    </div>
  `).join('');
  searchResults.style.display='block';
}

function addFromSearch(product) {
  // If IMEI tracked, prompt for IMEI
  if (parseInt(product.imei_tracking)) {
    const imei = window.prompt('Enter IMEI for ' + product.name + ':');
    if (!imei) return;
    product.imei = imei.trim();
  }
  POS.addItem(product);
  searchInput.value = '';
  searchResults.style.display='none';
  showToast(product.name + ' added to cart', 'success', 2000);
}

// Close search on outside click
document.addEventListener('click', e => {
  if (!e.target.closest('#productSearch') && !e.target.closest('#searchResults')) {
    searchResults.style.display='none';
  }
});

// ── Scanner ────────────────────────────────────────────────
async function openScanner() {
  Scanner.open(async (barcode) => {
    searchInput.value = barcode;
    const data = await erpFetch(`${BASE_URL}/api/search_product.php?q=${encodeURIComponent(barcode)}&branch=${branch_id}`);
    const prods = data.products || [];
    if (prods.length === 1) {
      addFromSearch(prods[0]);
    } else {
      renderResults(prods);
    }
  });
}

// ── Customer lookup ────────────────────────────────────────
async function lookupCustomer(phone) {
  if (phone.length !== 10) return;
  const spin = document.getElementById('custLookupSpinner');
  spin.style.display = 'flex';
  try {
    const data = await erpFetch(`${BASE_URL}/api/lookup_customer.php?phone=${encodeURIComponent(phone)}`);
    if (data.customer) {
      document.getElementById('custName').value = data.customer.name;
      showToast('Customer: ' + data.customer.name, 'info', 2500);
    }
  } catch(e) {}
  spin.style.display = 'none';
}

// ── Prepare & submit ───────────────────────────────────────
function prepareSave() {
  if (POS.cart.length === 0) { showToast('Cart is empty!', 'error'); return false; }
  document.getElementById('cartJson').value = JSON.stringify(POS.cart);
  return true;
}
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
