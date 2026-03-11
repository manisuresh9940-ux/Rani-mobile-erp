<?php
/**
 * Rani Mobiles ERP — Second-Hand Mobile: Sell Device
 */
require_once __DIR__ . '/../../config/auth.php';
require_auth();

$user      = current_user();
$branch_id = (int)$user['branch_id'];
$isAdmin   = is_admin();
$pdo       = db();

$message = $error = '';

// ── Handle Sale POST ─────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['sell_device'])) {
    verify_csrf();

    $sh_id       = (int)($_POST['sh_id'] ?? 0);
    $buyer_name  = clean($_POST['buyer_name']  ?? '');
    $buyer_phone = clean($_POST['buyer_phone'] ?? '');
    $sale_price  = (float)($_POST['sale_price'] ?? 0);
    $pay_mode    = in_array($_POST['payment_mode'] ?? '', ['cash','upi','card'])
                   ? $_POST['payment_mode'] : 'cash';
    $notes       = clean($_POST['notes'] ?? '');

    // Load the device (must belong to user's branch unless admin)
    $sq = $pdo->prepare(
        'SELECT sh.*, br.name AS brand_name
         FROM second_hand_purchases sh
         LEFT JOIN brands br ON br.id = sh.brand_id
         WHERE sh.id = ? AND sh.sold = 0' .
        ($isAdmin ? '' : ' AND sh.branch_id = ?')
    );
    $sq->execute($isAdmin ? [$sh_id] : [$sh_id, $branch_id]);
    $device = $sq->fetch();

    if (!$device) {
        $error = 'Device not found or already sold.';
    } elseif ($sale_price <= 0) {
        $error = 'Sale price must be greater than zero.';
    } else {
        try {
            $profit   = $sale_price - (float)$device['buy_price'];
            $sale_ref = next_serial((int)$device['branch_id'], 'SHS');

            $pdo->prepare(
                'INSERT INTO second_hand_sales
                 (purchase_id, sale_ref, branch_id, buyer_name, buyer_phone,
                  sale_price, profit, sale_date, created_by)
                 VALUES (?,?,?,?,?,?,?,CURDATE(),?)'
            )->execute([
                $device['id'], $sale_ref, (int)$device['branch_id'],
                $buyer_name, $buyer_phone,
                $sale_price, $profit, $user['id']
            ]);

            $pdo->prepare('UPDATE second_hand_purchases SET sold=1 WHERE id=?')
                ->execute([$device['id']]);

            log_activity('SH_SELL',
                "Ref: $sale_ref | Device: {$device['brand_name']} {$device['model']} " .
                "| IMEI: {$device['imei']} | Price: $sale_price | Profit: $profit | Mode: $pay_mode"
            );

            $message = $sale_ref;
        } catch (Throwable $e) {
            $error = 'Error saving sale: ' . $e->getMessage();
        }
    }
}

// ── Fetch available (unsold) devices ────────────────────────
$where  = ['sh.sold = 0'];
$params = [];

if (!$isAdmin) {
    $where[] = 'sh.branch_id = ?';
    $params[] = $branch_id;
}

$search = trim($_GET['q'] ?? '');
if ($search !== '') {
    $where[]  = '(sh.seller_name LIKE ? OR sh.model LIKE ? OR sh.imei LIKE ? OR br.name LIKE ?)';
    $like     = '%' . $search . '%';
    $params   = array_merge($params, [$like, $like, $like, $like]);
}

$fCond = $_GET['cond'] ?? '';
if (in_array($fCond, ['excellent','good','fair','poor'])) {
    $where[]  = 'sh.condition = ?';
    $params[] = $fCond;
}

$stmt = $pdo->prepare(
    'SELECT sh.*, br.name AS brand_name, b.code AS branch_code
     FROM second_hand_purchases sh
     LEFT JOIN brands br ON br.id = sh.brand_id
     JOIN branches b ON b.id = sh.branch_id
     WHERE ' . implode(' AND ', $where) .
    ' ORDER BY sh.id DESC LIMIT 200'
);
$stmt->execute($params);
$devices = $stmt->fetchAll();

$pageTitle = 'Sell Second-Hand Device';
?>
<?php include __DIR__ . '/../../includes/header.php'; ?>
<script>window.BASE_URL = '<?= BASE_URL ?>';</script>

<?php if ($message): ?>
  <div class="alert alert-success alert-dismissible">
    <i class="bi bi-check-circle-fill me-2"></i>
    Device sold successfully — Ref: <strong><?= clean($message) ?></strong>
    <a href="list.php" class="btn btn-sm btn-outline-success ms-2">View All Devices</a>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
  </div>
<?php endif; ?>
<?php if ($error): ?>
  <div class="alert alert-danger alert-dismissible">
    <i class="bi bi-exclamation-triangle me-2"></i><?= clean($error) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
  </div>
<?php endif; ?>

<div class="page-header">
  <h1 class="page-title"><i class="bi bi-phone-flip me-2"></i>Sell Second-Hand Device</h1>
  <div class="d-flex gap-2">
    <a href="buy.php"  class="btn btn-outline-primary btn-sm"><i class="bi bi-plus-circle me-1"></i>Buy Device</a>
    <a href="list.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-list-ul me-1"></i>All Devices</a>
  </div>
</div>

<!-- Search & Filter -->
<form method="GET" class="row g-2 mb-3">
  <div class="col-sm-6 col-md-5">
    <div class="input-group">
      <span class="input-group-text"><i class="bi bi-search"></i></span>
      <input type="text" name="q" class="form-control"
             placeholder="Search seller, model, IMEI, brand…"
             value="<?= clean($search) ?>">
    </div>
  </div>
  <div class="col-sm-4 col-md-3">
    <select name="cond" class="form-select">
      <option value="">All Conditions</option>
      <?php foreach (['excellent','good','fair','poor'] as $c): ?>
        <option value="<?= $c ?>" <?= $fCond === $c ? 'selected' : '' ?>><?= ucfirst($c) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="col-auto">
    <button type="submit" class="btn btn-primary"><i class="bi bi-funnel me-1"></i>Filter</button>
    <?php if ($search !== '' || $fCond !== ''): ?>
      <a href="sell.php" class="btn btn-outline-secondary ms-1"><i class="bi bi-x"></i> Clear</a>
    <?php endif; ?>
  </div>
</form>

<!-- Available Devices Table -->
<div class="erp-table">
  <div class="d-flex align-items-center justify-content-between p-3 border-bottom">
    <strong><i class="bi bi-phone-vibrate me-2 text-success"></i>Available Devices
      <span class="badge bg-success ms-1"><?= count($devices) ?></span>
    </strong>
    <small class="text-muted">Click <i class="bi bi-tag"></i> Sell to record a sale</small>
  </div>
  <div class="table-responsive">
    <table class="table table-hover mb-0">
      <thead>
        <tr>
          <th>#</th>
          <th>Ref</th>
          <th>Seller</th>
          <th>Device</th>
          <th>IMEI</th>
          <th>Condition</th>
          <th>Bought On</th>
          <th class="text-end">Buy Price</th>
          <th>Action</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($devices)): ?>
          <tr>
            <td colspan="9" class="text-center text-muted py-5">
              <i class="bi bi-inbox fs-2 d-block mb-2"></i>
              No available devices found
              <?= ($search !== '' || $fCond !== '') ? ' — try clearing the filter' : '' ?>
            </td>
          </tr>
        <?php else: ?>
          <?php
          $condColors = [
              'excellent' => 'success',
              'good'      => 'primary',
              'fair'      => 'warning',
              'poor'      => 'danger',
          ];
          foreach ($devices as $i => $d):
              $cc = $condColors[$d['condition']] ?? 'secondary';
          ?>
            <tr>
              <td class="text-muted"><?= $i + 1 ?></td>
              <td><code><?= clean($d['ref_no']) ?></code></td>
              <td>
                <div class="fw-semibold"><?= clean($d['seller_name']) ?></div>
                <?php if ($d['seller_phone']): ?>
                  <small class="text-muted"><i class="bi bi-telephone me-1"></i><?= clean($d['seller_phone']) ?></small>
                <?php endif; ?>
              </td>
              <td>
                <div><?= clean($d['brand_name'] ?? '') ?> <?= clean($d['model'] ?? '') ?></div>
                <?php if ($isAdmin): ?>
                  <small class="badge bg-light text-dark border"><?= clean($d['branch_code']) ?></small>
                <?php endif; ?>
              </td>
              <td class="font-monospace"><?= $d['imei'] ? clean($d['imei']) : '<span class="text-muted">—</span>' ?></td>
              <td><span class="badge bg-<?= $cc ?>"><?= ucfirst($d['condition']) ?></span></td>
              <td class="text-muted small"><?= fmt_date($d['created_at']) ?></td>
              <td class="text-end fw-semibold text-primary"><?= money((float)$d['buy_price']) ?></td>
              <td>
                <button type="button" class="btn btn-success btn-sm"
                        onclick="openSell(<?= $d['id'] ?>,
                                         '<?= addslashes(clean($d['brand_name'] ?? '') . ' ' . clean($d['model'] ?? '')) ?>',
                                         <?= (float)$d['buy_price'] ?>,
                                         '<?= clean($d['ref_no']) ?>')">
                  <i class="bi bi-tag me-1"></i>Sell
                </button>
                <?php if ($d['seller_photo']): ?>
                  <a href="<?= UPLOAD_URL ?>/<?= clean($d['seller_photo']) ?>"
                     target="_blank" class="btn btn-sm btn-outline-secondary ms-1"
                     title="Seller Photo">
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

<!-- ── Sell Modal ──────────────────────────────────────────── -->
<div class="modal fade" id="sellModal" tabindex="-1" aria-labelledby="sellModalLabel" aria-modal="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <form method="POST" id="sellForm">
        <input type="hidden" name="sell_device"  value="1">
        <input type="hidden" name="csrf_token"   value="<?= csrf_token() ?>">
        <input type="hidden" name="sh_id"        id="sellShId">

        <div class="modal-header bg-success text-white">
          <h5 class="modal-title" id="sellModalLabel">
            <i class="bi bi-tag me-2"></i>Sell Device
          </h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
        </div>

        <div class="modal-body">
          <!-- Device summary -->
          <div class="alert alert-light border mb-3 py-2" id="deviceSummary"></div>

          <!-- Buyer details -->
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label fw-semibold">Buyer Name</label>
              <input type="text" name="buyer_name" id="buyerName"
                     class="form-control" placeholder="Walk-in customer">
            </div>
            <div class="col-md-6">
              <label class="form-label fw-semibold">Buyer Phone</label>
              <input type="text" name="buyer_phone" id="buyerPhone"
                     class="form-control" placeholder="10-digit number" maxlength="15">
            </div>
            <div class="col-md-6">
              <label class="form-label fw-semibold">Sale Price (₹) <span class="text-danger">*</span></label>
              <div class="input-group">
                <span class="input-group-text">₹</span>
                <input type="number" name="sale_price" id="sellPrice"
                       class="form-control" min="1" step="0.01" required
                       placeholder="0.00">
              </div>
            </div>
            <div class="col-md-6">
              <label class="form-label fw-semibold">Payment Mode</label>
              <select name="payment_mode" class="form-select">
                <option value="cash">Cash</option>
                <option value="upi">UPI</option>
                <option value="card">Card</option>
              </select>
            </div>
            <div class="col-12">
              <label class="form-label fw-semibold">Notes</label>
              <textarea name="notes" class="form-control" rows="2"
                        placeholder="Optional remarks…"></textarea>
            </div>
          </div>

          <!-- Profit Preview -->
          <div id="profitPreview" class="alert mt-3 py-2 d-none"></div>
        </div>

        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-success">
            <i class="bi bi-check-circle me-1"></i>Confirm Sale
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
let _buyPrice = 0;

function openSell(id, device, buyPrice, ref) {
    _buyPrice = buyPrice;
    document.getElementById('sellShId').value    = id;
    document.getElementById('sellModalLabel').innerHTML =
        '<i class="bi bi-tag me-2"></i>Sell: ' + device;
    document.getElementById('deviceSummary').innerHTML =
        '<i class="bi bi-phone me-1 text-success"></i><strong>' + device + '</strong>' +
        ' &nbsp;|&nbsp; Ref: <code>' + ref + '</code>' +
        ' &nbsp;|&nbsp; Buy Price: <strong>₹' + buyPrice.toLocaleString('en-IN') + '</strong>';
    document.getElementById('sellPrice').value  = '';
    document.getElementById('buyerName').value  = '';
    document.getElementById('buyerPhone').value = '';
    updateProfit();
    new bootstrap.Modal(document.getElementById('sellModal')).show();
    setTimeout(() => document.getElementById('sellPrice').focus(), 300);
}

function updateProfit() {
    const price   = parseFloat(document.getElementById('sellPrice').value) || 0;
    const profit  = price - _buyPrice;
    const el      = document.getElementById('profitPreview');
    if (price <= 0) { el.classList.add('d-none'); return; }
    el.classList.remove('d-none');
    if (profit >= 0) {
        el.className = 'alert alert-success mt-3 py-2';
        el.innerHTML = '<i class="bi bi-graph-up-arrow me-1"></i>Estimated Profit: <strong>₹' +
                       profit.toLocaleString('en-IN', {minimumFractionDigits:2}) + '</strong>';
    } else {
        el.className = 'alert alert-danger mt-3 py-2';
        el.innerHTML = '<i class="bi bi-graph-down-arrow me-1"></i>Loss: <strong>₹' +
                       Math.abs(profit).toLocaleString('en-IN', {minimumFractionDigits:2}) + '</strong>';
    }
}

document.getElementById('sellPrice')?.addEventListener('input', updateProfit);
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
