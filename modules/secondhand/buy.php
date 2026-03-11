<?php
/**
 * Rani Mobiles ERP — Second-Hand Mobile: Buy Device
 */
require_once __DIR__ . '/../../config/auth.php';
require_auth();

$user      = current_user();
$branch_id = (int)$user['branch_id'];
$pdo       = db();

// Create uploads directory if needed
$uploadDir = BASE_PATH . '/uploads/secondhand/';
if (!is_dir($uploadDir)) { mkdir($uploadDir, 0755, true); }

$message = $error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_buy'])) {
    verify_csrf();

    $seller_name    = clean($_POST['seller_name'] ?? '');
    $seller_phone   = clean($_POST['seller_phone'] ?? '');
    $seller_address = clean($_POST['seller_address'] ?? '');
    $imei           = clean($_POST['imei'] ?? '');
    $brand_id       = (int)$_POST['brand_id'];
    $model          = clean($_POST['model'] ?? '');
    $condition      = clean($_POST['condition'] ?? 'good');
    $buy_price      = (float)$_POST['buy_price'];
    $notes          = clean($_POST['notes'] ?? '');

    if (!$seller_name || !$buy_price) {
        $error = 'Seller name and buy price are required.';
    } else {
        try {
            // Handle photo uploads
            $seller_photo   = null;
            $id_proof_photo = null;

            foreach (['seller_photo' => &$seller_photo, 'id_proof_photo' => &$id_proof_photo] as $key => &$dest) {
                if (!empty($_FILES[$key]['tmp_name']) && is_uploaded_file($_FILES[$key]['tmp_name'])) {
                    $ext  = strtolower(pathinfo($_FILES[$key]['name'], PATHINFO_EXTENSION));
                    $allowed = ['jpg','jpeg','png','webp'];
                    if (!in_array($ext, $allowed)) {
                        $error = 'Invalid photo format.'; break;
                    }
                    $filename = uniqid($key . '_', true) . '.' . $ext;
                    move_uploaded_file($_FILES[$key]['tmp_name'], $uploadDir . $filename);
                    $dest = 'secondhand/' . $filename;
                }
            }
            unset($dest);

            if (!$error) {
                $ref_no = next_serial($branch_id, 'SHB');
                $pdo->prepare(
                    'INSERT INTO second_hand_purchases
                     (ref_no,branch_id,seller_name,seller_phone,seller_address,seller_photo,
                      id_proof_photo,imei,brand_id,model,condition,buy_price,notes,created_by)
                     VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)'
                )->execute([
                    $ref_no, $branch_id, $seller_name, $seller_phone, $seller_address,
                    $seller_photo, $id_proof_photo, $imei ?: null,
                    $brand_id ?: null, $model, $condition, $buy_price, $notes, $user['id']
                ]);

                log_activity('SH_BUY', "Ref: $ref_no, IMEI: $imei");
                $message = $ref_no;
            }
        } catch (Throwable $e) {
            $error = 'Error: ' . $e->getMessage();
        }
    }
}

$brands = $pdo->query('SELECT id, name FROM brands ORDER BY name')->fetchAll();
$pageTitle = 'Buy Second-Hand Device';
?>
<?php include __DIR__ . '/../../includes/header.php'; ?>
<script>window.BASE_URL = '<?= BASE_URL ?>';</script>

<?php if ($message): ?>
  <div class="alert alert-success"><i class="bi bi-check-circle me-2"></i>Device purchased — Ref: <strong><?= clean($message) ?></strong>
    <a href="list.php" class="btn btn-sm btn-outline-success ms-2">View All</a></div>
<?php endif; ?>
<?php if ($error): ?>
  <div class="alert alert-danger"><?= clean($error) ?></div>
<?php endif; ?>

<div class="page-header">
  <h1 class="page-title"><i class="bi bi-phone-vibrate me-2"></i>Buy Second-Hand Device</h1>
  <a href="list.php" class="btn btn-outline-secondary btn-sm">All Devices</a>
</div>

<form method="POST" enctype="multipart/form-data">
  <input type="hidden" name="save_buy"   value="1">
  <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">

  <div class="row g-3">
    <div class="col-md-6">
      <div class="erp-form-card">
        <div class="card-heading"><i class="bi bi-person"></i>Seller Details</div>
        <div class="mb-2">
          <label class="form-label">Seller Name *</label>
          <input type="text" name="seller_name" class="form-control" required>
        </div>
        <div class="mb-2">
          <label class="form-label">Phone Number</label>
          <input type="text" name="seller_phone" class="form-control" maxlength="15">
        </div>
        <div class="mb-2">
          <label class="form-label">Address</label>
          <textarea name="seller_address" class="form-control" rows="2"></textarea>
        </div>
        <div class="row g-2">
          <div class="col-md-6">
            <label class="form-label">Seller Photo</label>
            <input type="file" name="seller_photo" class="form-control" accept="image/*">
          </div>
          <div class="col-md-6">
            <label class="form-label">ID Proof Photo</label>
            <input type="file" name="id_proof_photo" class="form-control" accept="image/*">
          </div>
        </div>
      </div>
    </div>

    <div class="col-md-6">
      <div class="erp-form-card">
        <div class="card-heading"><i class="bi bi-phone"></i>Device Details</div>
        <div class="row g-2">
          <div class="col-md-6">
            <label class="form-label">Brand</label>
            <select name="brand_id" class="form-select">
              <option value="">Select…</option>
              <?php foreach ($brands as $b): ?>
                <option value="<?= $b['id'] ?>"><?= clean($b['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-6">
            <label class="form-label">Model</label>
            <input type="text" name="model" class="form-control">
          </div>
          <div class="col-12">
            <label class="form-label">IMEI Number</label>
            <div class="input-group">
              <input type="text" name="imei" id="imeiInput" class="form-control" maxlength="20">
              <button type="button" class="btn btn-outline-secondary" onclick="openScanner()">
                <i class="bi bi-upc-scan"></i>
              </button>
            </div>
          </div>
          <div class="col-md-6">
            <label class="form-label">Condition</label>
            <select name="condition" class="form-select">
              <option value="excellent">Excellent</option>
              <option value="good" selected>Good</option>
              <option value="fair">Fair</option>
              <option value="poor">Poor</option>
            </select>
          </div>
          <div class="col-md-6">
            <label class="form-label">Buy Price (₹) *</label>
            <input type="number" name="buy_price" class="form-control" min="0" step="0.01" required>
          </div>
          <div class="col-12">
            <label class="form-label">Notes</label>
            <textarea name="notes" class="form-control" rows="2"></textarea>
          </div>
        </div>
      </div>
      <button type="submit" class="btn btn-success btn-lg w-100">
        <i class="bi bi-bag-check me-2"></i>Purchase Device
      </button>
    </div>
  </div>
</form>

<!-- Scanner Modal -->
<div class="modal fade" id="scannerModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header bg-dark text-white">
        <h5 class="modal-title"><i class="bi bi-upc-scan me-2"></i>Scan IMEI</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" onclick="Scanner.stop()"></button>
      </div>
      <div class="modal-body p-0">
        <video id="scannerVideo" style="width:100%;max-height:300px;background:#000" autoplay muted playsinline></video>
      </div>
    </div>
  </div>
</div>

<script src="https://unpkg.com/@zxing/library@latest/umd/index.min.js"></script>
<script>
function openScanner() {
  Scanner.open(code => { document.getElementById('imeiInput').value = code; });
}
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
