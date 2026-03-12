<?php
/**
 * Rani Mobiles ERP — Suppliers / Vendors Management
 */
require_once __DIR__ . '/../../config/auth.php';
require_auth();
require_admin();

$pdo     = db();
$message = $error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'save') {
        $id      = (int)($_POST['id'] ?? 0);
        $name    = clean($_POST['name'] ?? '');
        $phone   = clean($_POST['phone'] ?? '');
        $email   = clean($_POST['email'] ?? '');
        $address = clean($_POST['address'] ?? '');
        $gstin   = clean($_POST['gstin'] ?? '');

        if (!$name) {
            $error = 'Supplier name is required.';
        } else {
            try {
                if ($id) {
                    $pdo->prepare(
                        'UPDATE vendors SET name=?,phone=?,email=?,address=?,gstin=? WHERE id=?'
                    )->execute([$name, $phone, $email, $address, $gstin, $id]);
                    $message = 'Supplier updated.';
                } else {
                    $pdo->prepare(
                        'INSERT INTO vendors (name,phone,email,address,gstin) VALUES (?,?,?,?,?)'
                    )->execute([$name, $phone, $email, $address, $gstin]);
                    $message = 'Supplier added.';
                }
                log_activity('SUPPLIER_SAVE', "Name: $name");
            } catch (Throwable $e) {
                $error = 'Error: ' . $e->getMessage();
            }
        }
    } elseif ($action === 'delete') {
        $id = (int)$_POST['id'];
        try {
            $pdo->prepare('DELETE FROM vendors WHERE id=?')->execute([$id]);
            $message = 'Supplier deleted.';
        } catch (Throwable $e) {
            $error = 'Cannot delete supplier (may have purchases linked): ' . $e->getMessage();
        }
    }
}

$editVendor = null;
if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare('SELECT * FROM vendors WHERE id=?');
    $stmt->execute([(int)$_GET['edit']]);
    $editVendor = $stmt->fetch();
}

$vendors   = $pdo->query(
    'SELECT v.*, COUNT(p.id) AS purchase_count, COALESCE(SUM(p.total),0) AS total_purchase,
            v.balance
     FROM vendors v LEFT JOIN purchases p ON p.vendor_id=v.id
     GROUP BY v.id ORDER BY v.name'
)->fetchAll();

$pageTitle = 'Suppliers';
?>
<?php include __DIR__ . '/../../includes/header.php'; ?>
<script>window.BASE_URL = '<?= BASE_URL ?>';</script>

<?php if ($message): ?>
  <div class="alert alert-success"><i class="bi bi-check-circle me-2"></i><?= clean($message) ?></div>
<?php endif; ?>
<?php if ($error): ?>
  <div class="alert alert-danger"><i class="bi bi-exclamation-triangle me-2"></i><?= clean($error) ?></div>
<?php endif; ?>

<div class="page-header">
  <h1 class="page-title"><i class="bi bi-building me-2"></i>Suppliers / Vendors</h1>
  <button class="btn btn-primary btn-sm" data-bs-toggle="collapse" data-bs-target="#vendorForm">
    <i class="bi bi-plus-circle me-1"></i>Add Supplier
  </button>
</div>

<div class="collapse <?= $editVendor || $error ? 'show' : '' ?>" id="vendorForm">
  <div class="erp-form-card mb-3">
    <div class="card-heading">
      <i class="bi bi-building-add"></i><?= $editVendor ? 'Edit Supplier' : 'Add New Supplier' ?>
    </div>
    <form method="POST">
      <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
      <input type="hidden" name="action" value="save">
      <?php if ($editVendor): ?>
        <input type="hidden" name="id" value="<?= $editVendor['id'] ?>">
      <?php endif; ?>

      <div class="row g-3">
        <div class="col-md-3">
          <label class="form-label">Supplier Name *</label>
          <input type="text" name="name" class="form-control" required
                 value="<?= clean($editVendor['name'] ?? '') ?>">
        </div>
        <div class="col-md-2">
          <label class="form-label">Phone</label>
          <input type="text" name="phone" class="form-control"
                 value="<?= clean($editVendor['phone'] ?? '') ?>">
        </div>
        <div class="col-md-3">
          <label class="form-label">Email</label>
          <input type="email" name="email" class="form-control"
                 value="<?= clean($editVendor['email'] ?? '') ?>">
        </div>
        <div class="col-md-2">
          <label class="form-label">GSTIN</label>
          <input type="text" name="gstin" class="form-control"
                 value="<?= clean($editVendor['gstin'] ?? '') ?>">
        </div>
        <div class="col-md-2">
          <label class="form-label">Address</label>
          <input type="text" name="address" class="form-control"
                 value="<?= clean($editVendor['address'] ?? '') ?>">
        </div>
      </div>

      <div class="mt-3">
        <button type="submit" class="btn btn-success">
          <i class="bi bi-check-circle me-1"></i><?= $editVendor ? 'Update' : 'Add Supplier' ?>
        </button>
        <?php if ($editVendor): ?>
          <a href="?" class="btn btn-outline-secondary ms-2">Cancel</a>
        <?php endif; ?>
      </div>
    </form>
  </div>
</div>

<div class="erp-table">
  <div class="table-responsive">
    <table class="table table-hover mb-0">
      <thead>
        <tr>
          <th>#</th>
          <th>Supplier Name</th>
          <th>Phone</th>
          <th>GSTIN</th>
          <th class="text-end">Purchases</th>
          <th class="text-end">Total Value</th>
          <th class="text-end">Balance Due</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($vendors)): ?>
          <tr><td colspan="8" class="text-center text-muted py-3">No suppliers added yet</td></tr>
        <?php else: ?>
          <?php foreach ($vendors as $i => $v): ?>
            <tr>
              <td><?= $i + 1 ?></td>
              <td class="fw-semibold"><?= clean($v['name']) ?></td>
              <td><?= clean($v['phone'] ?? '-') ?></td>
              <td><small><?= clean($v['gstin'] ?? '-') ?></small></td>
              <td class="text-end"><?= (int)$v['purchase_count'] ?></td>
              <td class="text-end"><?= money((float)$v['total_purchase']) ?></td>
              <td class="text-end <?= $v['balance'] > 0 ? 'text-danger fw-semibold' : '' ?>">
                <?= money((float)$v['balance']) ?>
              </td>
              <td>
                <a href="?edit=<?= $v['id'] ?>" class="btn btn-sm btn-outline-primary">
                  <i class="bi bi-pencil"></i>
                </a>
                <?php if ($v['purchase_count'] == 0): ?>
                  <form method="POST" class="d-inline" onsubmit="return confirm('Delete this supplier?')">
                    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" value="<?= $v['id'] ?>">
                    <button type="submit" class="btn btn-sm btn-outline-danger">
                      <i class="bi bi-trash"></i>
                    </button>
                  </form>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
