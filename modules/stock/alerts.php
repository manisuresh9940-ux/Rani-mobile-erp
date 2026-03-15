<?php
/**
 * Rani Mobiles ERP — Alerts
 */
require_once __DIR__ . '/../../config/auth.php';
require_auth();
$user = current_user();
$pdo  = db();
$branch_id = $user['branch_id'];

$filterStatus = $_GET['status'] ?? 'OPEN';
$filterType   = $_GET['type']   ?? '';

// Dismiss alert
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'close') {
    verify_csrf();
    $id = (int)$_POST['alert_id'];
    $pdo->prepare("UPDATE alerts SET status='CLOSED', closed_at=NOW() WHERE id=?")->execute([$id]);
    header('Location: alerts.php?status=OPEN');
    exit;
}

$where  = can_see_all_branches() ? "WHERE a.status=?" : "WHERE a.branch_id=? AND a.status=?";
$params = can_see_all_branches() ? [$filterStatus] : [$branch_id, $filterStatus];

if ($filterType) {
    $where   .= " AND a.alert_type=?";
    $params[] = $filterType;
}

$stmt = $pdo->prepare(
    "SELECT a.*, b.name AS branch_name
     FROM alerts a
     LEFT JOIN branches b ON b.id = a.branch_id
     $where
     ORDER BY a.created_at DESC
     LIMIT 200"
);
$stmt->execute($params);
$alerts = $stmt->fetchAll();

$pageTitle = 'Alerts';
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';
?>
<div class="content-wrapper">
<div class="container-fluid py-3">
  <h4 class="mb-3"><i class="bi bi-bell-fill me-2"></i>Alerts</h4>

  <div class="mb-3 d-flex flex-wrap gap-2">
    <a href="?status=OPEN"
       class="btn btn-sm <?= $filterStatus === 'OPEN' ? 'btn-danger' : 'btn-outline-danger' ?>"><?= __('status_open') ?></a>
    <a href="?status=CLOSED"
       class="btn btn-sm <?= $filterStatus === 'CLOSED' ? 'btn-secondary' : 'btn-outline-secondary' ?>"><?= __('status_closed') ?></a>
    <?php foreach (['COLLECTION_TARGET_REMAINING','PAYMENT_NOT_GIVEN','VENDOR_DUE','UNSOLD_NEW_STOCK','SHORT','EXCESS','NEW_ITEM','ITEM_CHANGED'] as $t): ?>
      <a href="?status=<?= $filterStatus ?>&type=<?= $t ?>"
         class="btn btn-sm btn-outline-secondary <?= $filterType == $t ? 'active' : '' ?>"><?= $t ?></a>
    <?php endforeach; ?>
  </div>

  <div class="table-responsive">
  <table class="table table-sm align-middle">
    <thead class="table-dark">
      <tr><th><?= __('date') ?></th><th><?= __('branch') ?></th><th><?= __('alert_type') ?></th><th><?= __('severity') ?></th><th><?= __('message') ?></th><th><?= __('status') ?></th><th><?= __('action') ?></th></tr>
    </thead>
    <tbody>
    <?php foreach ($alerts as $a):
      $sev = match($a['severity']) { 'CRITICAL' => 'danger', 'WARN' => 'warning', 'INFO' => 'info', default => 'secondary' };
    ?>
      <tr>
        <td><?= $a['business_date'] ? clean($a['business_date']) : fmt_date($a['created_at']) ?></td>
        <td><?= clean($a['branch_name'] ?? 'All') ?></td>
        <td><span class="badge bg-secondary"><?= $a['alert_type'] ?></span></td>
        <td>
          <span class="badge bg-<?= $sev ?> <?= $sev === 'warning' ? 'text-dark' : '' ?>">
            <?= $a['severity'] ?>
          </span>
        </td>
        <td><?= clean($a['message'] ?? '') ?></td>
        <td>
          <span class="badge <?= $a['status'] === 'OPEN' ? 'bg-danger' : 'bg-success' ?>">
            <?= $a['status'] ?>
          </span>
        </td>
        <td>
          <?php if ($a['status'] === 'OPEN'): ?>
          <form method="post" class="d-inline">
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
            <input type="hidden" name="action" value="close">
            <input type="hidden" name="alert_id" value="<?= $a['id'] ?>">
            <button class="btn btn-sm btn-outline-success"><?= __('btn_dismiss') ?></button>
          </form>
          <?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$alerts): ?>
      <tr><td colspan="7" class="text-center text-muted py-4"><?= __('no_alerts') ?></td></tr>
    <?php endif; ?>
    </tbody>
  </table>
  </div>
</div>
</div>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
