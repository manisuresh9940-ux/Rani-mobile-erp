<?php
/**
 * Rani Mobiles ERP — Item Change Requests
 */
require_once __DIR__ . '/../../config/auth.php';
require_auth();
$user = current_user();
$pdo  = db();
$errors = []; $success = '';

// Approve / Reject (SUP only)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($_POST['action'] ?? '', ['approve', 'reject'])) {
    verify_csrf();
    if (!is_sup()) {
        $errors[] = 'Permission denied.';
    } else {
        $id     = (int)$_POST['request_id'];
        $notes  = clean($_POST['owner_notes'] ?? '');
        $status = $_POST['action'] === 'approve' ? 'APPROVED' : 'REJECTED';
        $pdo->prepare(
            "UPDATE item_change_requests SET status=?, owner_notes=?, reviewed_by=?, reviewed_at=NOW() WHERE id=?"
        )->execute([$status, $notes, $user['id'], $id]);
        $success = "Request $status.";
    }
}

$where = can_see_all_branches() ? '1=1' : "icr.branch_id={$user['branch_id']}";
$reqs  = $pdo->query(
    "SELECT icr.*, b.name AS branch_name, p.name AS item_name,
            p2.name AS sub_item_name, u.full_name AS req_user
     FROM item_change_requests icr
     LEFT JOIN branches b  ON b.id  = icr.branch_id
     LEFT JOIN products p  ON p.id  = icr.item_id
     LEFT JOIN products p2 ON p2.id = icr.substitute_for_item_id
     LEFT JOIN users u     ON u.id  = icr.requested_by
     WHERE $where
     ORDER BY icr.created_at DESC
     LIMIT 200"
)->fetchAll();

$pageTitle = 'Item Change Requests';
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';
?>
<div class="content-wrapper">
<div class="container-fluid py-3">
  <h4 class="mb-3"><i class="bi bi-arrow-repeat me-2"></i>Item Change Requests</h4>

  <?php foreach ($errors as $e): ?>
    <div class="alert alert-danger"><?= clean($e) ?></div>
  <?php endforeach; ?>
  <?php if ($success): ?>
    <div class="alert alert-success"><?= clean($success) ?></div>
  <?php endif; ?>

  <div class="table-responsive">
  <table class="table table-sm align-middle">
    <thead class="table-dark">
      <tr>
        <th>Date</th><th>Branch</th><th>Item</th><th>Substitute For</th>
        <th>Requested By</th><th>Status</th><th>Notes</th><th>Action</th>
      </tr>
    </thead>
    <tbody>
    <?php foreach ($reqs as $r): ?>
      <tr>
        <td><?= fmt_date($r['created_at']) ?></td>
        <td><?= clean($r['branch_name']) ?></td>
        <td class="fw-bold"><?= clean($r['item_name']) ?></td>
        <td><?= $r['sub_item_name'] ? clean($r['sub_item_name']) : '-' ?></td>
        <td><?= clean($r['req_user'] ?? '') ?></td>
        <td>
          <span class="badge <?= match($r['status']) {
            'APPROVED' => 'bg-success',
            'REJECTED' => 'bg-danger',
            default    => 'bg-warning text-dark',
          } ?>"><?= $r['status'] ?></span>
        </td>
        <td><?= clean($r['owner_notes'] ?? '') ?></td>
        <td>
          <?php if ($r['status'] === 'PENDING_OWNER' && is_sup()): ?>
          <form method="post" class="d-inline">
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
            <input type="hidden" name="request_id" value="<?= $r['id'] ?>">
            <input type="text" name="owner_notes" class="form-control form-control-sm d-inline w-auto"
                   placeholder="Notes (optional)">
            <button name="action" value="approve" class="btn btn-sm btn-success ms-1">✓ Approve</button>
            <button name="action" value="reject"  class="btn btn-sm btn-danger ms-1">✗ Reject</button>
          </form>
          <?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$reqs): ?>
      <tr><td colspan="8" class="text-center text-muted py-4">No requests</td></tr>
    <?php endif; ?>
    </tbody>
  </table>
  </div>
</div>
</div>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
