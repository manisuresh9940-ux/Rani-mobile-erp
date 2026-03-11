<?php
/**
 * Rani Mobiles ERP — Products Management
 */
require_once __DIR__ . '/../../config/auth.php';
require_auth();

$user    = current_user();
$pdo     = db();
$message = $error = '';
$editProduct = null;

// ── Handle POST ────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $action = $_POST['action'] ?? '';

    if ($action === 'save') {
        $id             = (int)($_POST['id'] ?? 0);
        $name           = clean($_POST['name'] ?? '');
        $brand_id       = (int)$_POST['brand_id'];
        $category_id    = (int)$_POST['category_id'];
        $model          = clean($_POST['model'] ?? '');
        $barcode        = clean($_POST['barcode'] ?? '');
        $hsn_code       = clean($_POST['hsn_code'] ?? '');
        $gst_percent    = (float)$_POST['gst_percent'];
        $purchase_cost  = (float)$_POST['purchase_cost'];
        $sale_price     = (float)$_POST['sale_price'];
        $min_stock      = (int)$_POST['min_stock'];
        $imei_tracking  = (int)($_POST['imei_tracking'] ?? 0);
        $barcode_tracking = (int)($_POST['barcode_tracking'] ?? 0);
        $is_active      = (int)($_POST['is_active'] ?? 1);

        if (!$name || !$brand_id) {
            $error = 'Product name and brand are required.';
        } else {
            try {
                if ($id) {
                    // Track price history
                    $old = $pdo->prepare('SELECT purchase_cost, sale_price FROM products WHERE id=?');
                    $old->execute([$id]);
                    $oldRow = $old->fetch();
                    if ($oldRow && ($oldRow['purchase_cost'] != $purchase_cost || $oldRow['sale_price'] != $sale_price)) {
                        $pdo->prepare(
                            'INSERT INTO product_price_history (product_id,old_cost,new_cost,old_price,new_price,changed_by)
                             VALUES (?,?,?,?,?,?)'
                        )->execute([$id, $oldRow['purchase_cost'], $purchase_cost, $oldRow['sale_price'], $sale_price, $user['id']]);
                    }

                    $pdo->prepare(
                        'UPDATE products SET name=?,brand_id=?,category_id=?,model=?,barcode=?,
                         hsn_code=?,gst_percent=?,purchase_cost=?,sale_price=?,min_stock=?,
                         imei_tracking=?,barcode_tracking=?,is_active=? WHERE id=?'
                    )->execute([$name,$brand_id,$category_id,$model,$barcode,$hsn_code,
                                $gst_percent,$purchase_cost,$sale_price,$min_stock,
                                $imei_tracking,$barcode_tracking,$is_active,$id]);
                    $message = 'Product updated.';
                    log_activity('UPDATE_PRODUCT', "ID: $id, $name");
                } else {
                    $pdo->prepare(
                        'INSERT INTO products (name,brand_id,category_id,model,barcode,hsn_code,
                         gst_percent,purchase_cost,sale_price,min_stock,imei_tracking,barcode_tracking,is_active)
                         VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)'
                    )->execute([$name,$brand_id,$category_id,$model,$barcode,$hsn_code,
                                $gst_percent,$purchase_cost,$sale_price,$min_stock,
                                $imei_tracking,$barcode_tracking,$is_active]);
                    $message = 'Product added.';
                    log_activity('CREATE_PRODUCT', "Name: $name");
                }
            } catch (Throwable $e) {
                $error = 'Error: ' . $e->getMessage();
            }
        }
    } elseif ($action === 'delete') {
        $id = (int)$_POST['id'];
        $pdo->prepare('UPDATE products SET is_active=0 WHERE id=?')->execute([$id]);
        $message = 'Product deactivated.';
    }
}

// Edit mode
if (isset($_GET['edit'])) {
    $editProduct = $pdo->prepare('SELECT * FROM products WHERE id=?');
    $editProduct->execute([(int)$_GET['edit']]);
    $editProduct = $editProduct->fetch();
}

// Data
$brands     = $pdo->query('SELECT * FROM brands ORDER BY name')->fetchAll();
$categories = $pdo->query('SELECT * FROM categories ORDER BY name')->fetchAll();
$search     = trim($_GET['s'] ?? '');
$fBrand     = (int)($_GET['brand'] ?? 0);

$whereP  = ['p.is_active=1'];
$paramsP = [];
if ($search) {
    $whereP[] = '(p.name LIKE ? OR p.model LIKE ? OR p.barcode LIKE ?)';
    $paramsP[] = "%$search%"; $paramsP[] = "%$search%"; $paramsP[] = "%$search%";
}
if ($fBrand) {
    $whereP[] = 'p.brand_id=?';
    $paramsP[] = $fBrand;
}

$products = $pdo->prepare(
    'SELECT p.*, br.name AS brand_name, c.name AS category_name
     FROM products p
     JOIN brands br ON br.id=p.brand_id
     LEFT JOIN categories c ON c.id=p.category_id
     WHERE ' . implode(' AND ', $whereP) . ' ORDER BY br.name, p.name LIMIT 200'
);
$products->execute($paramsP);
$products = $products->fetchAll();

$pageTitle = 'Products';
?>
<?php include __DIR__ . '/../../includes/header.php'; ?>
<script>window.BASE_URL = '<?= BASE_URL ?>';</script>

<?php if ($message): ?>
  <div class="alert alert-success"><?= clean($message) ?></div>
<?php endif; ?>
<?php if ($error): ?>
  <div class="alert alert-danger"><?= clean($error) ?></div>
<?php endif; ?>

<div class="page-header">
  <h1 class="page-title"><i class="bi bi-phone me-2"></i>Products</h1>
  <button class="btn btn-primary btn-sm" data-bs-toggle="collapse" data-bs-target="#addForm">
    <i class="bi bi-plus-circle me-1"></i><?= $editProduct ? 'Edit' : 'Add' ?> Product
  </button>
</div>

<!-- Add / Edit Form -->
<div class="collapse <?= ($editProduct || $error) ? 'show' : '' ?>" id="addForm">
  <div class="erp-form-card mb-3">
    <div class="card-heading">
      <i class="bi bi-phone-fill"></i><?= $editProduct ? 'Edit Product' : 'Add New Product' ?>
    </div>
    <form method="POST">
      <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
      <input type="hidden" name="action" value="save">
      <?php if ($editProduct): ?>
        <input type="hidden" name="id" value="<?= $editProduct['id'] ?>">
      <?php endif; ?>

      <div class="row g-3">
        <div class="col-md-4">
          <label class="form-label">Product Name *</label>
          <input type="text" name="name" class="form-control" required
                 value="<?= clean($editProduct['name'] ?? '') ?>">
        </div>
        <div class="col-md-3">
          <label class="form-label">Brand *</label>
          <select name="brand_id" class="form-select" required>
            <option value="">Select brand…</option>
            <?php foreach ($brands as $b): ?>
              <option value="<?= $b['id'] ?>" <?= ($editProduct['brand_id']??0)==$b['id']?'selected':'' ?>>
                <?= clean($b['name']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-3">
          <label class="form-label">Category</label>
          <select name="category_id" class="form-select">
            <option value="">Select…</option>
            <?php foreach ($categories as $c): ?>
              <option value="<?= $c['id'] ?>" <?= ($editProduct['category_id']??0)==$c['id']?'selected':'' ?>>
                <?= clean($c['name']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-2">
          <label class="form-label">Model</label>
          <input type="text" name="model" class="form-control"
                 value="<?= clean($editProduct['model'] ?? '') ?>">
        </div>
        <div class="col-md-2">
          <label class="form-label">Barcode</label>
          <input type="text" name="barcode" class="form-control"
                 value="<?= clean($editProduct['barcode'] ?? '') ?>">
        </div>
        <div class="col-md-2">
          <label class="form-label">HSN Code</label>
          <input type="text" name="hsn_code" class="form-control"
                 value="<?= clean($editProduct['hsn_code'] ?? '') ?>">
        </div>
        <div class="col-md-2">
          <label class="form-label">GST %</label>
          <input type="number" name="gst_percent" class="form-control" min="0" max="28" step="0.01"
                 value="<?= $editProduct['gst_percent'] ?? 18 ?>">
        </div>
        <div class="col-md-2">
          <label class="form-label">Purchase Cost *</label>
          <input type="number" name="purchase_cost" class="form-control" min="0" step="0.01" required
                 value="<?= $editProduct['purchase_cost'] ?? 0 ?>">
        </div>
        <div class="col-md-2">
          <label class="form-label">Sale Price *</label>
          <input type="number" name="sale_price" class="form-control" min="0" step="0.01" required
                 value="<?= $editProduct['sale_price'] ?? 0 ?>">
        </div>
        <div class="col-md-2">
          <label class="form-label">Min Stock</label>
          <input type="number" name="min_stock" class="form-control" min="0"
                 value="<?= $editProduct['min_stock'] ?? 5 ?>">
        </div>
        <div class="col-12">
          <div class="row g-3">
            <div class="col-auto">
              <div class="form-check form-switch">
                <input type="checkbox" name="imei_tracking" value="1" class="form-check-input" id="imeiTrack"
                       <?= ($editProduct['imei_tracking']??0)?'checked':'' ?>>
                <label class="form-check-label" for="imeiTrack">Enable IMEI Tracking</label>
              </div>
            </div>
            <div class="col-auto">
              <div class="form-check form-switch">
                <input type="checkbox" name="barcode_tracking" value="1" class="form-check-input" id="barcodeTrack"
                       <?= ($editProduct['barcode_tracking']??0)?'checked':'' ?>>
                <label class="form-check-label" for="barcodeTrack">Enable Barcode Tracking</label>
              </div>
            </div>
            <div class="col-auto">
              <div class="form-check form-switch">
                <input type="checkbox" name="is_active" value="1" class="form-check-input" id="isActive"
                       <?= ($editProduct['is_active']??1)?'checked':'' ?>>
                <label class="form-check-label" for="isActive">Active</label>
              </div>
            </div>
          </div>
        </div>
      </div>

      <div class="mt-3">
        <button type="submit" class="btn btn-success">
          <i class="bi bi-check-circle me-1"></i><?= $editProduct ? 'Update Product' : 'Add Product' ?>
        </button>
        <?php if ($editProduct): ?>
          <a href="?" class="btn btn-outline-secondary ms-2">Cancel</a>
        <?php endif; ?>
      </div>
    </form>
  </div>
</div>

<!-- Search & filter -->
<div class="erp-form-card mb-3">
  <form method="GET" class="row g-2 align-items-end">
    <div class="col-md-3">
      <input type="text" name="s" class="form-control form-control-sm"
             placeholder="Search name, model, barcode…" value="<?= clean($search) ?>">
    </div>
    <div class="col-md-2">
      <select name="brand" class="form-select form-select-sm">
        <option value="">All Brands</option>
        <?php foreach ($brands as $b): ?>
          <option value="<?= $b['id'] ?>" <?= $fBrand==$b['id']?'selected':'' ?>><?= clean($b['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-md-auto">
      <button type="submit" class="btn btn-primary btn-sm">Search</button>
      <a href="?" class="btn btn-outline-secondary btn-sm ms-1">Reset</a>
    </div>
  </form>
</div>

<!-- Products table -->
<div class="erp-table">
  <div class="table-responsive">
    <table class="table table-hover mb-0">
      <thead>
        <tr>
          <th>#</th><th>Product</th><th>Brand</th><th>Barcode</th>
          <th class="text-end">Cost</th><th class="text-end">Price</th>
          <th class="text-end">GST%</th><th>Tracking</th><th>Status</th><th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($products)): ?>
          <tr><td colspan="10" class="text-center text-muted py-4">No products found</td></tr>
        <?php else: ?>
          <?php foreach ($products as $i => $p): ?>
            <tr>
              <td><?= $i+1 ?></td>
              <td>
                <div class="fw-semibold"><?= clean($p['name']) ?></div>
                <small class="text-muted"><?= clean($p['model'] ?? '') ?></small>
              </td>
              <td><?= clean($p['brand_name']) ?></td>
              <td><code><?= clean($p['barcode'] ?: '-') ?></code></td>
              <td class="text-end"><?= money((float)$p['purchase_cost']) ?></td>
              <td class="text-end fw-semibold"><?= money((float)$p['sale_price']) ?></td>
              <td class="text-end"><?= $p['gst_percent'] ?>%</td>
              <td>
                <?= $p['imei_tracking'] ? '<span class="badge bg-info text-dark">IMEI</span>' : '' ?>
                <?= $p['barcode_tracking'] ? '<span class="badge bg-secondary">Barcode</span>' : '' ?>
                <?= (!$p['imei_tracking'] && !$p['barcode_tracking']) ? '<span class="text-muted small">None</span>' : '' ?>
              </td>
              <td>
                <?= $p['is_active'] ? '<span class="badge bg-success">Active</span>' : '<span class="badge bg-danger">Inactive</span>' ?>
              </td>
              <td>
                <a href="?edit=<?= $p['id'] ?>" class="btn btn-sm btn-outline-primary">
                  <i class="bi bi-pencil"></i>
                </a>
                <a href="<?= BASE_URL ?>/modules/stock/inventory.php?product=<?= $p['id'] ?>"
                   class="btn btn-sm btn-outline-secondary" title="View Stock">
                  <i class="bi bi-boxes"></i>
                </a>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
