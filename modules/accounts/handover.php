<?php
/**
 * Rani Mobiles ERP — Cash Handover
 */
require_once __DIR__ . '/../../config/auth.php';
require_auth();
$user = current_user();
$pdo  = db();
$today = date('Y-m-d');
// SUP/Admin can view any branch's handovers via ?branch= parameter
$branch_id = can_see_all_branches() && !empty($_GET['branch'])
    ? (int)$_GET['branch']
    : (int)$user['branch_id'];

$errors = []; $success = '';

// Confirm handover (SUP only)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'confirm') {
    verify_csrf();
    if (!is_sup()) {
        $errors[] = 'Only Supervisor can confirm.';
    } else {
        $id = (int)$_POST['handover_id'];
        $pdo->prepare(
            "UPDATE cash_handovers SET status='CONFIRMED', confirmed_by=?, confirmed_at=NOW() WHERE id=?"
        )->execute([$user['id'], $id]);
        $success = 'Handover confirmed.';
    }
}

// New handover
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add') {
    verify_csrf();
    $amount = (float)($_POST['amount'] ?? 0);
    $notes  = clean($_POST['notes'] ?? '');
    if ($amount <= 0) {
        $errors[] = 'Amount must be > 0.';
    } else {
        $pdo->prepare(
            "INSERT INTO cash_handovers (branch_id,business_date,amount,handover_by,notes) VALUES (?,?,?,?,?)"
        )->execute([$branch_id, $today, $amount, $user['id'], $notes]);
        $success = 'Handover submitted. Awaiting owner confirmation.';
    }
}

// List all handovers for today (branch)
$q = $pdo->prepare(
    "SELECT ch.*, u.full_name AS handover_user, cu.full_name AS confirmed_user
     FROM cash_handovers ch
     LEFT JOIN users u  ON u.id = ch.handover_by
     LEFT JOIN users cu ON cu.id = ch.confirmed_by
     WHERE ch.branch_id=? AND ch.business_date=?
     ORDER BY ch.created_at DESC"
);
$q->execute([$branch_id, $today]);
$handovers = $q->fetchAll();

$pageTitle = 'Cash Handover';
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';
?>
<div class="content-wrapper">
<div class="container-fluid py-3">
  <h4 class="mb-3"><i class="bi bi-cash me-2"></i>Cash Handover — <?= clean($user['branch']) ?> (<?= $today ?>)</h4>

  <?php foreach ($errors as $e): ?>
    <div class="alert alert-danger"><?= clean($e) ?></div>
  <?php endforeach; ?>
  <?php if ($success): ?>
    <div class="alert alert-success"><?= clean($success) ?></div>
  <?php endif; ?>

  <div class="card mb-3">
    <div class="card-header fw-bold"><?= __('submit_handover') ?></div>
    <div class="card-body">
      <form method="post" class="row g-3">
        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
        <input type="hidden" name="action" value="add">
        <div class="col-md-4">
          <label class="form-label"><?= __('amount') ?> (₹)</label>
          <input type="number" step="0.01" min="0.01" name="amount" class="form-control" required>
        </div>
        <div class="col-md-6">
          <label class="form-label"><?= __('notes') ?></label>
          <input type="text" name="notes" class="form-control">
        </div>
        <div class="col-12">
          <button class="btn btn-primary"><?= __('btn_submit') ?></button>
        </div>
      </form>
    </div>
  </div>

  <div class="card">
    <div class="card-header fw-bold"><?= __('todays_handovers') ?></div>
    <div class="card-body p-0">
      <table class="table table-sm mb-0">
        <thead>
          <tr><th><?= __('date') ?></th><th><?= __('amount') ?></th><th><?= __('handover_by') ?></th><th><?= __('notes') ?></th><th><?= __('status') ?></th><th><?= __('action') ?></th></tr>
        </thead>
        <tbody>
        <?php foreach ($handovers as $h): ?>
          <tr>
            <td><?= date('H:i', strtotime($h['created_at'])) ?></td>
            <td><?= money((float)$h['amount']) ?></td>
            <td><?= clean($h['handover_user']) ?></td>
            <td><?= clean($h['notes'] ?? '') ?></td>
            <td>
              <span class="badge <?= $h['status'] === 'CONFIRMED' ? 'bg-success' : 'bg-warning text-dark' ?>">
                <?= $h['status'] ?>
              </span>
            </td>
            <td>
              <?php if ($h['status'] === 'PENDING_CONFIRM' && is_sup()): ?>
              <form method="post" class="d-inline">
                <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                <input type="hidden" name="action" value="confirm">
                <input type="hidden" name="handover_id" value="<?= $h['id'] ?>">
                <button class="btn btn-sm btn-success"><?= __('btn_confirm') ?></button>
              </form>
              <?php else: ?>
                <?= $h['confirmed_user'] ? clean($h['confirmed_user']) : '-' ?>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$handovers): ?>
          <tr><td colspan="6" class="text-center text-muted"><?= __('no_handovers') ?></td></tr>
        <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
</div>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
