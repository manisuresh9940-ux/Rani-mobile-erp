<?php
/**
 * Rani Mobiles ERP — Branch Expected Stock Baseline
 */
require_once __DIR__ . '/../../config/auth.php';
require_auth();
$user = current_user();
$pdo  = db();

$branch_id = (int)($_GET['branch'] ?? $user['branch_id']);
if (!can_see_all_branches()) $branch_id = $user['branch_id'];

$errors = []; $success = '';

// Save baseline (SUP only)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save') {
    verify_csrf();
    if (!is_sup()) {
        $errors[] = 'Only Supervisor/Owner can edit baseline.';
    } else {
        $item_id = (int)$_POST['item_id'];
        $min_qty = (int)$_POST['expected_min_qty'];
        $max_qty = (int)$_POST['expected_max_qty'];
        $active  = isset($_POST['active']) ? 1 : 0;
        $pdo->prepare(
            "INSERT INTO branch_expected_stock (branch_id,item_id,expected_min_qty,expected_max_qty,active,updated_by)
             VALUES (?,?,?,?,?,?)
             ON DUPLICATE KEY UPDATE
               expected_min_qty=VALUES(expected_min_qty),
               expected_max_qty=VALUES(expected_max_qty),
               active=VALUES(active),
               updated_by=VALUES(updated_by),
               updated_at=NOW()"
        )->execute([$branch_id, $item_id, $min_qty, $max_qty, $active, $user['id']]);
        $success = 'Baseline updated.';
    }
}

// Fetch items with current stock + baseline
$stmt = $pdo->prepare(
    "SELECT p.id, p.name, p.model, b2.name AS brand,
            COALESCE(s.qty, 0) AS current_qty,
            bes.expected_min_qty, bes.expected_max_qty, bes.active AS bes_active,
            CASE
              WHEN bes.id IS NULL                               THEN 'NEW'
              WHEN COALESCE(s.qty,0) = 0                       THEN 'OUT'
              WHEN COALESCE(s.qty,0) < bes.expected_min_qty    THEN 'SHORT'
              WHEN COALESCE(s.qty,0) > bes.expected_max_qty    THEN 'EXCESS'
              WHEN COALESCE(s.qty,0) <= p.min_stock            THEN 'LOW'
              ELSE 'OK'
            END AS stock_status
     FROM products p
     LEFT JOIN brands b2 ON b2.id = p.brand_id
     LEFT JOIN stock s   ON s.product_id = p.id AND s.branch_id = ?
     LEFT JOIN branch_expected_stock bes ON bes.item_id = p.id AND bes.branch_id = ?
     WHERE p.is_active = 1
     ORDER BY p.name"
);
$stmt->execute([$branch_id, $branch_id]);
$items = $stmt->fetchAll();

$branches = [];
if (can_see_all_branches()) {
    $branches = $pdo->query("SELECT * FROM branches WHERE is_active=1")->fetchAll();
}

$bq = $pdo->prepare("SELECT name FROM branches WHERE id=?");
$bq->execute([$branch_id]);
$branchName = $bq->fetchColumn() ?: '';

$pageTitle = 'Expected Stock Baseline';
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';
?>
<div class="content-wrapper">
<div class="container-fluid py-3">
  <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <h4 class="mb-0"><i class="bi bi-clipboard-check me-2"></i>Expected Stock Baseline — <?= clean($branchName) ?></h4>
    <?php if (can_see_all_branches()): ?>
    <div>
      <?php foreach ($branches as $br): ?>
        <a href="?branch=<?= $br['id'] ?>"
           class="btn btn-sm <?= $br['id'] == $branch_id ? 'btn-primary' : 'btn-outline-secondary' ?> me-1">
          <?= clean($br['code']) ?>
        </a>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </div>

  <?php foreach ($errors as $e): ?>
    <div class="alert alert-danger"><?= clean($e) ?></div>
  <?php endforeach; ?>
  <?php if ($success): ?>
    <div class="alert alert-success"><?= clean($success) ?></div>
  <?php endif; ?>

  <!-- Legend -->
  <div class="mb-2 d-flex flex-wrap gap-2 small">
    <span class="badge bg-success">OK</span>
    <span class="badge bg-danger">SHORT</span>
    <span class="badge bg-warning text-dark">EXCESS</span>
    <span class="badge bg-secondary">NEW ITEM</span>
    <span class="badge bg-dark">OUT</span>
    <span class="badge bg-info text-dark">LOW</span>
  </div>

  <div class="table-responsive">
  <table class="table table-sm table-hover align-middle">
    <thead class="table-dark">
      <tr>
        <th>Item</th><th>Brand</th><th>Qty</th><th>Min</th><th>Max</th><th>Status</th>
        <?php if (is_sup()): ?><th>Action</th><?php endif; ?>
      </tr>
    </thead>
    <tbody>
    <?php foreach ($items as $it):
      $badge = match($it['stock_status']) {
        'SHORT'  => 'danger',
        'EXCESS' => 'warning',
        'NEW'    => 'secondary',
        'OUT'    => 'dark',
        'LOW'    => 'info',
        default  => 'success',
      };
    ?>
      <tr>
        <td>
          <?= clean($it['name']) ?>
          <?= $it['model'] ? ' <small class="text-muted">' . clean($it['model']) . '</small>' : '' ?>
        </td>
        <td><?= clean($it['brand'] ?? '') ?></td>
        <td class="fw-bold"><?= $it['current_qty'] ?></td>
        <td><?= $it['expected_min_qty'] ?? '-' ?></td>
        <td><?= $it['expected_max_qty'] ?? '-' ?></td>
        <td>
          <span class="badge bg-<?= $badge ?> <?= $badge === 'warning' ? 'text-dark' : '' ?>">
            <?= $it['stock_status'] ?>
          </span>
        </td>
        <?php if (is_sup()): ?>
        <td>
          <button class="btn btn-sm btn-outline-primary"
            onclick="openBaseline(
              <?= $it['id'] ?>,
              <?= json_encode($it['name'], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
              <?= (int)$it['expected_min_qty'] ?>,
              <?= (int)$it['expected_max_qty'] ?>,
              <?= (int)($it['bes_active'] ?? 1) ?>
            )">Edit</button>
        </td>
        <?php endif; ?>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  </div>
</div>
</div>

<?php if (is_sup()): ?>
<!-- Edit Modal -->
<div class="modal fade" id="baselineModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Edit Baseline</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form method="post">
        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
        <input type="hidden" name="action" value="save">
        <input type="hidden" name="item_id" id="modal_item_id">
        <div class="modal-body">
          <p id="modal_item_name" class="fw-bold"></p>
          <div class="row g-3">
            <div class="col-6">
              <label class="form-label">Min Qty</label>
              <input type="number" min="0" name="expected_min_qty" id="modal_min" class="form-control" required>
            </div>
            <div class="col-6">
              <label class="form-label">Max Qty</label>
              <input type="number" min="0" name="expected_max_qty" id="modal_max" class="form-control" required>
            </div>
            <div class="col-12">
              <div class="form-check">
                <input class="form-check-input" type="checkbox" name="active" id="modal_active" value="1">
                <label class="form-check-label" for="modal_active">Active</label>
              </div>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="submit" class="btn btn-primary">Save</button>
        </div>
      </form>
    </div>
  </div>
</div>
<script>
function openBaseline(id, name, min_qty, max_qty, active) {
  document.getElementById('modal_item_id').value = id;
  document.getElementById('modal_item_name').textContent = name;
  document.getElementById('modal_min').value = min_qty;
  document.getElementById('modal_max').value = max_qty;
  document.getElementById('modal_active').checked = active === 1;
  new bootstrap.Modal(document.getElementById('baselineModal')).show();
}
</script>
<?php endif; ?>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
