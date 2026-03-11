<?php
/**
 * Rani Mobiles ERP — Activity Log
 */
require_once __DIR__ . '/../../config/auth.php';
require_auth();
require_admin();

$pdo    = db();
$search = trim($_GET['s'] ?? '');
$from   = $_GET['from'] ?? date('Y-m-d');
$to     = $_GET['to']   ?? date('Y-m-d');
$userId = (int)($_GET['user'] ?? 0);

$where  = ['a.created_at BETWEEN ? AND ?'];
$params = [$from . ' 00:00:00', $to . ' 23:59:59'];

if ($search) {
    $where[] = '(a.action LIKE ? OR a.description LIKE ?)';
    $params[] = "%$search%"; $params[] = "%$search%";
}
if ($userId) {
    $where[] = 'a.user_id=?'; $params[] = $userId;
}

$logs = $pdo->prepare(
    'SELECT a.*, u.full_name, u.username, b.code AS branch_code
     FROM activity_log a
     LEFT JOIN users u ON u.id=a.user_id
     LEFT JOIN branches b ON b.id=a.branch_id
     WHERE ' . implode(' AND ', $where) . ' ORDER BY a.id DESC LIMIT 500'
);
$logs->execute($params);
$logs = $logs->fetchAll();

$users    = $pdo->query('SELECT id, full_name FROM users ORDER BY full_name')->fetchAll();
$pageTitle = 'Activity Log';
?>
<?php include __DIR__ . '/../../includes/header.php'; ?>
<script>window.BASE_URL = '<?= BASE_URL ?>';</script>

<div class="page-header">
  <h1 class="page-title"><i class="bi bi-activity me-2"></i>Activity Log</h1>
</div>

<div class="erp-form-card mb-3">
  <form method="GET" class="row g-2 align-items-end">
    <div class="col-md-2"><label class="form-label">From</label>
      <input type="date" name="from" class="form-control form-control-sm" value="<?= $from ?>"></div>
    <div class="col-md-2"><label class="form-label">To</label>
      <input type="date" name="to" class="form-control form-control-sm" value="<?= $to ?>"></div>
    <div class="col-md-2">
      <label class="form-label">User</label>
      <select name="user" class="form-select form-select-sm">
        <option value="">All Users</option>
        <?php foreach ($users as $u): ?>
          <option value="<?= $u['id'] ?>" <?= $userId==$u['id']?'selected':'' ?>><?= clean($u['full_name']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-md-3"><label class="form-label">Search</label>
      <input type="text" name="s" class="form-control form-control-sm"
             placeholder="Action, description…" value="<?= clean($search) ?>"></div>
    <div class="col-auto">
      <button type="submit" class="btn btn-primary btn-sm">Search</button></div>
  </form>
</div>

<div class="erp-table">
  <div class="table-responsive">
    <table class="table table-hover mb-0">
      <thead>
        <tr><th>#</th><th>Date & Time</th><th>User</th><th>Branch</th>
            <th>Action</th><th>Description</th><th>IP</th></tr>
      </thead>
      <tbody>
        <?php if (empty($logs)): ?>
          <tr><td colspan="7" class="text-center text-muted py-4">No logs found</td></tr>
        <?php else: ?>
          <?php foreach ($logs as $i => $log): ?>
            <tr>
              <td><?= $i+1 ?></td>
              <td><?= date('d-m-Y H:i', strtotime($log['created_at'])) ?></td>
              <td>
                <div><?= clean($log['full_name'] ?? 'System') ?></div>
                <small class="text-muted"><?= clean($log['username'] ?? '') ?></small>
              </td>
              <td><span class="badge bg-primary"><?= clean($log['branch_code'] ?? '-') ?></span></td>
              <td><span class="badge bg-dark"><?= clean($log['action']) ?></span></td>
              <td><?= clean($log['description'] ?? '-') ?></td>
              <td><small class="text-muted"><?= clean($log['ip_address'] ?? '-') ?></small></td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
