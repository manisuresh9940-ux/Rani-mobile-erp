<?php
/**
 * Rani Mobiles ERP — Branches Management
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
        $id       = (int)($_POST['id'] ?? 0);
        $code     = strtoupper(trim(clean($_POST['code'] ?? '')));
        $name     = clean($_POST['name'] ?? '');
        $address  = clean($_POST['address'] ?? '');
        $phone    = clean($_POST['phone'] ?? '');
        $gstin    = clean($_POST['gstin'] ?? '');
        $lat      = $_POST['latitude']  ?? null;
        $lng      = $_POST['longitude'] ?? null;
        $radius   = (int)($_POST['radius_m'] ?? 100);
        $is_active= (int)($_POST['is_active'] ?? 1);

        if (!$code || !$name) {
            $error = 'Branch code and name required.';
        } else {
            try {
                if ($id) {
                    $pdo->prepare(
                        'UPDATE branches SET code=?,name=?,address=?,phone=?,gstin=?,latitude=?,longitude=?,radius_m=?,is_active=? WHERE id=?'
                    )->execute([$code,$name,$address,$phone,$gstin,$lat?:(null),$lng?:(null),$radius,$is_active,$id]);
                    $message = 'Branch updated.';
                } else {
                    $pdo->prepare(
                        'INSERT INTO branches (code,name,address,phone,gstin,latitude,longitude,radius_m,is_active)
                         VALUES (?,?,?,?,?,?,?,?,?)'
                    )->execute([$code,$name,$address,$phone,$gstin,$lat?:(null),$lng?:(null),$radius,$is_active]);
                    $message = 'Branch created.';
                }
            } catch (Throwable $e) {
                $error = 'Error: ' . $e->getMessage();
            }
        }
    }
}

$editBranch = null;
if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare('SELECT * FROM branches WHERE id=?');
    $stmt->execute([(int)$_GET['edit']]);
    $editBranch = $stmt->fetch();
}

$branches = $pdo->query('SELECT * FROM branches ORDER BY code')->fetchAll();
$pageTitle = 'Branches';
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
  <h1 class="page-title"><i class="bi bi-shop me-2"></i>Branch Management</h1>
  <button class="btn btn-primary btn-sm" data-bs-toggle="collapse" data-bs-target="#branchForm">
    <i class="bi bi-plus-circle me-1"></i>Add Branch
  </button>
</div>

<div class="collapse <?= $editBranch || $error ? 'show' : '' ?>" id="branchForm">
  <div class="erp-form-card mb-3">
    <div class="card-heading">
      <i class="bi bi-shop"></i><?= $editBranch ? 'Edit Branch' : 'Add Branch' ?>
    </div>
    <form method="POST">
      <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
      <input type="hidden" name="action" value="save">
      <?php if ($editBranch): ?>
        <input type="hidden" name="id" value="<?= $editBranch['id'] ?>">
      <?php endif; ?>

      <div class="row g-2">
        <div class="col-md-2">
          <label class="form-label">Branch Code *</label>
          <input type="text" name="code" class="form-control" maxlength="10" required
                 value="<?= clean($editBranch['code'] ?? '') ?>" placeholder="R1">
        </div>
        <div class="col-md-4">
          <label class="form-label">Branch Name *</label>
          <input type="text" name="name" class="form-control" required
                 value="<?= clean($editBranch['name'] ?? '') ?>">
        </div>
        <div class="col-md-3">
          <label class="form-label">Phone</label>
          <input type="text" name="phone" class="form-control"
                 value="<?= clean($editBranch['phone'] ?? '') ?>">
        </div>
        <div class="col-md-3">
          <label class="form-label">GSTIN</label>
          <input type="text" name="gstin" class="form-control"
                 value="<?= clean($editBranch['gstin'] ?? '') ?>">
        </div>
        <div class="col-md-6">
          <label class="form-label">Address</label>
          <textarea name="address" class="form-control" rows="2"><?= clean($editBranch['address'] ?? '') ?></textarea>
        </div>
        <div class="col-md-2">
          <label class="form-label">Latitude</label>
          <input type="text" name="latitude" class="form-control"
                 value="<?= clean($editBranch['latitude'] ?? '') ?>" placeholder="13.0827">
        </div>
        <div class="col-md-2">
          <label class="form-label">Longitude</label>
          <input type="text" name="longitude" class="form-control"
                 value="<?= clean($editBranch['longitude'] ?? '') ?>" placeholder="80.2707">
        </div>
        <div class="col-md-2">
          <label class="form-label">GPS Radius (m)</label>
          <input type="number" name="radius_m" class="form-control"
                 value="<?= $editBranch['radius_m'] ?? 100 ?>" min="50">
        </div>
        <div class="col-md-2">
          <div class="form-check form-switch mt-4">
            <input type="checkbox" name="is_active" value="1" class="form-check-input"
                   <?= ($editBranch['is_active']??1)?'checked':'' ?>>
            <label class="form-check-label">Active</label>
          </div>
        </div>
        <div class="col-12">
          <button type="submit" class="btn btn-success">
            <i class="bi bi-check-circle me-1"></i><?= $editBranch ? 'Update' : 'Create' ?> Branch
          </button>
          <?php if ($editBranch): ?>
            <a href="?" class="btn btn-outline-secondary ms-2">Cancel</a>
          <?php endif; ?>
        </div>
      </div>
    </form>
  </div>
</div>

<div class="erp-table">
  <div class="table-responsive">
    <table class="table table-hover mb-0">
      <thead>
        <tr><th>Code</th><th>Name</th><th>Phone</th><th>GSTIN</th>
            <th>GPS Radius</th><th>Status</th><th>Actions</th></tr>
      </thead>
      <tbody>
        <?php foreach ($branches as $b): ?>
          <tr>
            <td><strong><?= clean($b['code']) ?></strong></td>
            <td><?= clean($b['name']) ?></td>
            <td><?= clean($b['phone'] ?? '-') ?></td>
            <td><?= clean($b['gstin'] ?? '-') ?></td>
            <td><?= $b['radius_m'] ?>m</td>
            <td>
              <?= $b['is_active']
                ? '<span class="badge bg-success">Active</span>'
                : '<span class="badge bg-danger">Inactive</span>' ?>
            </td>
            <td>
              <a href="?edit=<?= $b['id'] ?>" class="btn btn-sm btn-outline-primary">
                <i class="bi bi-pencil"></i>
              </a>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
