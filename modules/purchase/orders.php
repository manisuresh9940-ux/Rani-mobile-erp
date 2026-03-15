<?php
/**
 * Rani Mobiles ERP — Purchase Orders (PO)
 */
require_once __DIR__ . '/../../config/auth.php';
require_auth();
$user = current_user();
$pdo  = db();
$branch_id = $user['branch_id'];
$errors = []; $success = '';

// Create PO
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'create') {
    verify_csrf();
    $vendor_id = (int)($_POST['vendor_id'] ?? 0) ?: null;
    $expected  = clean($_POST['expected_date'] ?? '');
    $notes     = clean($_POST['notes'] ?? '');
    $items_raw = $_POST['items'] ?? [];

    $po_no = 'PO-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -4));
    $pdo->prepare(
        "INSERT INTO purchase_orders (po_no,branch_id,vendor_id,po_date,expected_date,notes,created_by)
         VALUES (?,?,?,CURDATE(),?,?,?)"
    )->execute([$po_no, $branch_id, $vendor_id, $expected ?: null, $notes, $user['id']]);
    $po_id = (int)$pdo->lastInsertId();

    foreach ($items_raw as $row) {
        $item_id = (int)($row['item_id'] ?? 0);
        $qty     = (int)($row['qty']     ?? 1);
        $cost    = (float)($row['est_cost'] ?? 0);
        if ($item_id > 0 && $qty > 0) {
            $pdo->prepare(
                "INSERT INTO purchase_order_items (po_id,item_id,qty,est_cost) VALUES (?,?,?,?)"
            )->execute([$po_id, $item_id, $qty, $cost]);
        }
    }
    $success = "PO $po_no created.";
}

// Status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'status') {
    verify_csrf();
    $id = (int)$_POST['po_id'];
    $st = in_array($_POST['status'], ['DRAFT','SENT','RECEIVED','CANCELLED']) ? $_POST['status'] : 'DRAFT';
    $pdo->prepare("UPDATE purchase_orders SET status=? WHERE id=?")->execute([$st, $id]);
    $success = 'PO status updated.';
}

$where  = can_see_all_branches() ? '' : 'WHERE po.branch_id=?';
$params = can_see_all_branches() ? [] : [$branch_id];
$stmt   = $pdo->prepare(
    "SELECT po.*, b.name AS branch_name, v.name AS vendor_name
     FROM purchase_orders po
     LEFT JOIN branches b ON b.id = po.branch_id
     LEFT JOIN vendors  v ON v.id = po.vendor_id
     $where
     ORDER BY po.created_at DESC
     LIMIT 100"
);
$stmt->execute($params);
$orders = $stmt->fetchAll();

$vendors  = $pdo->query("SELECT id, name FROM vendors ORDER BY name")->fetchAll();
$products = $pdo->query("SELECT id, name, model FROM products WHERE is_active=1 ORDER BY name")->fetchAll();

$pageTitle = 'Purchase Orders';
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';
?>
<div class="content-wrapper">
<div class="container-fluid py-3">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0"><i class="bi bi-file-earmark-text me-2"></i>Purchase Orders</h4>
    <button class="btn btn-primary" data-bs-toggle="collapse" data-bs-target="#createPO">
      <i class="bi bi-plus"></i> New PO
    </button>
  </div>

  <?php foreach ($errors as $e): ?>
    <div class="alert alert-danger"><?= clean($e) ?></div>
  <?php endforeach; ?>
  <?php if ($success): ?>
    <div class="alert alert-success"><?= clean($success) ?></div>
  <?php endif; ?>

  <!-- Create PO form -->
  <div class="collapse mb-3" id="createPO">
    <div class="card">
      <div class="card-header fw-bold">Create Purchase Order</div>
      <div class="card-body">
        <form method="post">
          <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
          <input type="hidden" name="action" value="create">
          <div class="row g-3 mb-3">
            <div class="col-md-4">
              <label class="form-label">Vendor</label>
              <select name="vendor_id" class="form-select">
                <option value="">-- Select --</option>
                <?php foreach ($vendors as $v): ?>
                  <option value="<?= $v['id'] ?>"><?= clean($v['name']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-3">
              <label class="form-label">Expected Delivery</label>
              <input type="date" name="expected_date" class="form-control">
            </div>
            <div class="col-md-5">
              <label class="form-label">Notes</label>
              <input type="text" name="notes" class="form-control">
            </div>
          </div>

          <div id="poItems">
            <div class="row g-2 align-items-end mb-2 po-item-row">
              <div class="col-md-5">
                <label class="form-label">Item</label>
                <select name="items[0][item_id]" class="form-select">
                  <option value="">-- Select --</option>
                  <?php foreach ($products as $p): ?>
                    <option value="<?= $p['id'] ?>"><?= clean($p['name']) ?><?= $p['model'] ? ' - ' . clean($p['model']) : '' ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="col-md-2">
                <label class="form-label">Qty</label>
                <input type="number" min="1" name="items[0][qty]" class="form-control" value="1">
              </div>
              <div class="col-md-3">
                <label class="form-label">Est. Cost (₹)</label>
                <input type="number" step="0.01" min="0" name="items[0][est_cost]" class="form-control" value="0">
              </div>
            </div>
          </div>

          <button type="button" onclick="addPORow()" class="btn btn-outline-secondary btn-sm mb-3">
            <i class="bi bi-plus"></i> Add Item
          </button>
          <div><button class="btn btn-primary">Create PO</button></div>
        </form>
      </div>
    </div>
  </div>

  <!-- PO list -->
  <div class="table-responsive">
  <table class="table table-sm table-hover align-middle">
    <thead class="table-dark">
      <tr>
        <th>PO No</th>
        <?php if (can_see_all_branches()): ?><th>Branch</th><?php endif; ?>
        <th>Vendor</th><th>Date</th><th>Expected</th><th>Status</th><th>Action</th>
      </tr>
    </thead>
    <tbody>
    <?php foreach ($orders as $o): ?>
      <tr>
        <td class="fw-bold"><?= clean($o['po_no']) ?></td>
        <?php if (can_see_all_branches()): ?>
          <td><?= clean($o['branch_name']) ?></td>
        <?php endif; ?>
        <td><?= clean($o['vendor_name'] ?? '-') ?></td>
        <td><?= fmt_date($o['po_date']) ?></td>
        <td><?= $o['expected_date'] ? fmt_date($o['expected_date']) : '-' ?></td>
        <td>
          <span class="badge <?= match($o['status']) {
            'RECEIVED'  => 'bg-success',
            'CANCELLED' => 'bg-danger',
            'SENT'      => 'bg-info text-dark',
            default     => 'bg-secondary',
          } ?>"><?= $o['status'] ?></span>
        </td>
        <td>
          <?php if (!in_array($o['status'], ['RECEIVED','CANCELLED'])): ?>
          <form method="post" class="d-inline">
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
            <input type="hidden" name="action" value="status">
            <input type="hidden" name="po_id" value="<?= $o['id'] ?>">
            <select name="status" class="form-select form-select-sm d-inline w-auto" onchange="this.form.submit()">
              <?php foreach (['DRAFT','SENT','RECEIVED','CANCELLED'] as $s): ?>
                <option value="<?= $s ?>" <?= $o['status'] == $s ? 'selected' : '' ?>><?= $s ?></option>
              <?php endforeach; ?>
            </select>
          </form>
          <?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$orders): ?>
      <tr><td colspan="7" class="text-center text-muted py-4">No POs found</td></tr>
    <?php endif; ?>
    </tbody>
  </table>
  </div>
</div>
</div>

<script>
let poIdx = 1;
const productData = <?= json_encode(array_map(fn($p) => [
    'id'    => $p['id'],
    'label' => $p['name'] . ($p['model'] ? ' - ' . $p['model'] : ''),
], $products), JSON_HEX_TAG) ?>;
const productOptions = productData.map(p =>
  `<option value="${p.id}">${p.label.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;')}</option>`
).join('');
function addPORow() {
  const d = document.createElement('div');
  d.className = 'row g-2 align-items-end mb-2 po-item-row';
  d.innerHTML = `
    <div class="col-md-5">
      <select name="items[${poIdx}][item_id]" class="form-select">
        <option value="">-- Select --</option>${productOptions}
      </select>
    </div>
    <div class="col-md-2">
      <input type="number" min="1" name="items[${poIdx}][qty]" class="form-control" value="1">
    </div>
    <div class="col-md-3">
      <input type="number" step="0.01" min="0" name="items[${poIdx}][est_cost]" class="form-control" value="0">
    </div>
    <div class="col-md-1">
      <button type="button" class="btn btn-outline-danger btn-sm"
              onclick="this.closest('.po-item-row').remove()">✕</button>
    </div>`;
  document.getElementById('poItems').appendChild(d);
  poIdx++;
}
</script>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
