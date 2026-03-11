<?php
/**
 * Rani Mobiles ERP — Backup Management
 */
require_once __DIR__ . '/../../config/auth.php';
require_auth();
require_admin();

$pdo     = db();
$message = $error = '';

// Manual backup trigger
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['do_backup'])) {
    verify_csrf();

    $backupDir = BASE_PATH . '/backups/';
    if (!is_dir($backupDir)) { mkdir($backupDir, 0755, true); }

    $filename   = 'rani_erp_backup_' . date('Ymd_His') . '.sql';
    $backupFile = $backupDir . $filename;

    // Generate SQL dump via MySQL dump commands list
    $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    $sql = "-- Rani Mobiles ERP Backup\n-- Date: " . date('Y-m-d H:i:s') . "\n\nSET foreign_key_checks=0;\n\n";

    foreach ($tables as $table) {
        $create = $pdo->query("SHOW CREATE TABLE `$table`")->fetch(PDO::FETCH_NUM);
        $sql .= "DROP TABLE IF EXISTS `$table`;\n" . $create[1] . ";\n\n";

        $rows = $pdo->query("SELECT * FROM `$table`")->fetchAll(PDO::FETCH_NUM);
        if ($rows) {
            $sql .= "INSERT INTO `$table` VALUES\n";
            $valueSets = [];
            foreach ($rows as $row) {
                $vals = array_map(fn($v) => $v === null ? 'NULL' : $pdo->quote($v), $row);
                $valueSets[] = '(' . implode(',', $vals) . ')';
            }
            $sql .= implode(",\n", $valueSets) . ";\n\n";
        }
    }

    $sql .= "SET foreign_key_checks=1;\n";
    file_put_contents($backupFile, $sql);

    log_activity('MANUAL_BACKUP', "File: $filename");
    $message = "Backup created: $filename";
}

// List backups
$backupDir  = BASE_PATH . '/backups/';
$backupFiles = [];
if (is_dir($backupDir)) {
    $backupFiles = array_filter(scandir($backupDir), fn($f) => str_ends_with($f, '.sql'));
    rsort($backupFiles);
}

$pageTitle = 'Backup';
?>
<?php include __DIR__ . '/../../includes/header.php'; ?>
<script>window.BASE_URL = '<?= BASE_URL ?>';</script>

<?php if ($message): ?>
  <div class="alert alert-success"><i class="bi bi-check-circle me-2"></i><?= clean($message) ?></div>
<?php endif; ?>

<div class="page-header">
  <h1 class="page-title"><i class="bi bi-cloud-upload me-2"></i>Database Backup</h1>
</div>

<div class="row g-3">
  <div class="col-md-4">
    <div class="erp-form-card text-center">
      <div class="fs-1 mb-2">☁️</div>
      <h5>Manual Backup</h5>
      <p class="text-muted small">Create an immediate backup of all ERP data.</p>
      <form method="POST">
        <input type="hidden" name="do_backup"  value="1">
        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
        <button type="submit" class="btn btn-primary w-100">
          <i class="bi bi-cloud-upload me-2"></i>Create Backup Now
        </button>
      </form>
    </div>
  </div>

  <div class="col-md-8">
    <div class="erp-form-card">
      <div class="card-heading"><i class="bi bi-archive"></i>Backup Files</div>
      <?php if (empty($backupFiles)): ?>
        <div class="text-center text-muted py-4">
          <i class="bi bi-folder-x fs-2"></i><br>No backup files yet.
        </div>
      <?php else: ?>
        <div class="table-responsive">
          <table class="table table-sm table-hover">
            <thead><tr><th>#</th><th>Filename</th><th>Size</th><th>Date</th></tr></thead>
            <tbody>
              <?php foreach (array_values($backupFiles) as $i => $file): ?>
                <?php
                  $path = $backupDir . $file;
                  $size = filesize($path);
                  $sizeFmt = $size > 1024*1024 ? round($size/1024/1024, 1).'MB' : round($size/1024, 0).'KB';
                ?>
                <tr>
                  <td><?= $i+1 ?></td>
                  <td><?= clean($file) ?></td>
                  <td><?= $sizeFmt ?></td>
                  <td><?= date('d-m-Y H:i', filemtime($path)) ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
