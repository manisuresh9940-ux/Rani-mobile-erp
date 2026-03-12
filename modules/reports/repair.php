<?php
/**
 * Rani Mobiles ERP — Repair / Service Jobs Report
 */
require_once __DIR__ . '/../../config/auth.php';
require_auth();

$user      = current_user();
$branch_id = (int)$user['branch_id'];
$isAdmin   = is_admin();
$pdo       = db();

$fromDate = $_GET['from']   ?? date('Y-m-01');
$toDate   = $_GET['to']     ?? date('Y-m-d');
$fBranch  = $isAdmin ? (int)($_GET['branch'] ?? 0) : $branch_id;
$fStatus  = $_GET['status'] ?? '';

$where  = ['j.received_at BETWEEN ? AND ?'];
$params = [$fromDate . ' 00:00:00', $toDate . ' 23:59:59'];
if ($fBranch) { $where[] = 'j.branch_id=?'; $params[] = $fBranch; }
elseif (!$isAdmin) { $where[] = 'j.branch_id=?'; $params[] = $branch_id; }
if ($fStatus) { $where[] = 'j.status=?'; $params[] = $fStatus; }

$whereClause = implode(' AND ', $where);

// Summary
$summaryQ = $pdo->prepare(
    'SELECT COUNT(*) AS total_jobs,
            SUM(CASE WHEN j.status=\'delivered\' THEN 1 ELSE 0 END) AS delivered,
            SUM(CASE WHEN j.status=\'in_repair\' THEN 1 ELSE 0 END) AS in_repair,
            SUM(CASE WHEN j.status=\'ready\' THEN 1 ELSE 0 END) AS ready,
            SUM(CASE WHEN j.status=\'received\' THEN 1 ELSE 0 END) AS received,
            COALESCE(SUM(j.repair_cost),0) AS total_revenue,
            COALESCE(SUM(j.advance_paid),0) AS total_advance
     FROM service_jobs j WHERE ' . $whereClause
);
$summaryQ->execute($params);
$summary = $summaryQ->fetch();

// Job list
$jobsQ = $pdo->prepare(
    'SELECT j.job_no, j.received_at, j.customer_name, j.customer_phone,
            j.model, j.complaint, j.status, j.repair_cost, j.advance_paid,
            j.delivered_at, b.code AS branch,
            u.full_name AS technician
     FROM service_jobs j
     JOIN branches b ON b.id=j.branch_id
     LEFT JOIN users u ON u.id=j.technician_id
     WHERE ' . $whereClause . '
     ORDER BY j.received_at DESC'
);
$jobsQ->execute($params);
$jobs = $jobsQ->fetchAll();

// Top technicians
$techQ = $pdo->prepare(
    'SELECT u.full_name, COUNT(*) AS jobs, SUM(j.repair_cost) AS revenue
     FROM service_jobs j JOIN users u ON u.id=j.technician_id
     WHERE ' . $whereClause . '
     GROUP BY j.technician_id ORDER BY jobs DESC LIMIT 5'
);
$techQ->execute($params);
$topTechs = $techQ->fetchAll();

$branches = $pdo->query('SELECT id, code FROM branches ORDER BY code')->fetchAll();

// CSV export
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="repair_report_' . $fromDate . '_to_' . $toDate . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Job No','Date','Branch','Customer','Phone','Model','Complaint','Status','Repair Cost','Advance','Technician','Delivered At']);
    foreach ($jobs as $j) {
        fputcsv($out, [
            $j['job_no'], date('d-m-Y', strtotime($j['received_at'])),
            $j['branch'], $j['customer_name'], $j['customer_phone'],
            $j['model'], $j['complaint'], $j['status'],
            $j['repair_cost'], $j['advance_paid'],
            $j['technician'] ?? '-',
            $j['delivered_at'] ? date('d-m-Y', strtotime($j['delivered_at'])) : '-'
        ]);
    }
    fclose($out);
    exit;
}

$pageTitle = 'Repair Report';
?>
<?php include __DIR__ . '/../../includes/header.php'; ?>
<script>window.BASE_URL = '<?= BASE_URL ?>';</script>

<div class="page-header">
  <h1 class="page-title"><i class="bi bi-tools me-2"></i>Repair / Service Report</h1>
  <div class="d-flex gap-2 no-print">
    <button onclick="window.print()" class="btn btn-outline-secondary btn-sm">
      <i class="bi bi-printer me-1"></i>Print / PDF
    </button>
    <a href="?from=<?= urlencode($fromDate) ?>&to=<?= urlencode($toDate) ?>&branch=<?= $fBranch ?>&status=<?= urlencode($fStatus) ?>&export=csv"
       class="btn btn-outline-success btn-sm">
      <i class="bi bi-file-earmark-excel me-1"></i>Export CSV
    </a>
  </div>
</div>

<!-- Filters -->
<div class="erp-form-card mb-3 no-print">
  <form method="GET" class="row g-2 align-items-end">
    <div class="col-md-2">
      <label class="form-label">From</label>
      <input type="date" name="from" class="form-control form-control-sm" value="<?= $fromDate ?>">
    </div>
    <div class="col-md-2">
      <label class="form-label">To</label>
      <input type="date" name="to" class="form-control form-control-sm" value="<?= $toDate ?>">
    </div>
    <?php if ($isAdmin): ?>
    <div class="col-md-2">
      <label class="form-label">Branch</label>
      <select name="branch" class="form-select form-select-sm">
        <option value="">All Branches</option>
        <?php foreach ($branches as $b): ?>
          <option value="<?= $b['id'] ?>" <?= $fBranch == $b['id'] ? 'selected' : '' ?>><?= clean($b['code']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <?php endif; ?>
    <div class="col-md-2">
      <label class="form-label">Status</label>
      <select name="status" class="form-select form-select-sm">
        <option value="">All Status</option>
        <option value="received" <?= $fStatus === 'received' ? 'selected' : '' ?>>Received</option>
        <option value="in_repair" <?= $fStatus === 'in_repair' ? 'selected' : '' ?>>In Repair</option>
        <option value="ready" <?= $fStatus === 'ready' ? 'selected' : '' ?>>Ready</option>
        <option value="delivered" <?= $fStatus === 'delivered' ? 'selected' : '' ?>>Delivered</option>
      </select>
    </div>
    <div class="col-auto">
      <button type="submit" class="btn btn-primary btn-sm">Generate</button>
      <div class="d-inline-flex gap-1 ms-1">
        <a href="?from=<?= date('Y-m-d') ?>&to=<?= date('Y-m-d') ?>" class="btn btn-outline-secondary btn-sm">Today</a>
        <a href="?from=<?= date('Y-m-01') ?>&to=<?= date('Y-m-d') ?>" class="btn btn-outline-secondary btn-sm">Month</a>
      </div>
    </div>
  </form>
</div>

<!-- KPI Summary -->
<div class="row g-3 mb-4">
  <div class="col-6 col-md-2">
    <div class="kpi-card p-3">
      <div class="kpi-label">Total Jobs</div>
      <div class="kpi-value"><?= (int)$summary['total_jobs'] ?></div>
    </div>
  </div>
  <div class="col-6 col-md-2">
    <div class="kpi-card kpi-sales p-3">
      <div class="kpi-label">Delivered</div>
      <div class="kpi-value"><?= (int)$summary['delivered'] ?></div>
    </div>
  </div>
  <div class="col-6 col-md-2">
    <div class="kpi-card kpi-purchase p-3">
      <div class="kpi-label">In Repair</div>
      <div class="kpi-value"><?= (int)$summary['in_repair'] ?></div>
    </div>
  </div>
  <div class="col-6 col-md-2">
    <div class="kpi-card p-3" style="border-left: 4px solid #ffc107">
      <div class="kpi-label">Ready</div>
      <div class="kpi-value"><?= (int)$summary['ready'] ?></div>
    </div>
  </div>
  <div class="col-6 col-md-2">
    <div class="kpi-card kpi-profit p-3">
      <div class="kpi-label">Revenue</div>
      <div class="kpi-value"><?= money((float)$summary['total_revenue']) ?></div>
    </div>
  </div>
  <div class="col-6 col-md-2">
    <div class="kpi-card kpi-cash p-3">
      <div class="kpi-label">Advance Collected</div>
      <div class="kpi-value"><?= money((float)$summary['total_advance']) ?></div>
    </div>
  </div>
</div>

<div class="row g-3 mb-4">
  <!-- Status distribution chart -->
  <div class="col-lg-5">
    <div class="chart-card">
      <div class="chart-title">Job Status Distribution</div>
      <canvas id="statusChart" height="160"></canvas>
    </div>
  </div>
  <!-- Top Technicians -->
  <?php if (!empty($topTechs)): ?>
  <div class="col-lg-7">
    <div class="erp-table h-100">
      <div class="p-3 border-bottom fw-bold"><i class="bi bi-person-badge me-2"></i>Top Technicians</div>
      <table class="table table-sm mb-0">
        <thead>
          <tr><th>Technician</th><th class="text-end">Jobs</th><th class="text-end">Revenue</th></tr>
        </thead>
        <tbody>
          <?php foreach ($topTechs as $t): ?>
            <tr>
              <td><?= clean($t['full_name']) ?></td>
              <td class="text-end"><?= (int)$t['jobs'] ?></td>
              <td class="text-end fw-semibold"><?= money((float)$t['revenue']) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
  <?php endif; ?>
</div>

<!-- Jobs Table -->
<div class="erp-table">
  <div class="p-3 border-bottom fw-bold"><i class="bi bi-list-ul me-2"></i>Job Cards (<?= count($jobs) ?>)</div>
  <div class="table-responsive">
    <table class="table table-hover mb-0" id="jobsTable">
      <thead>
        <tr>
          <th>Job No</th>
          <th>Date</th>
          <th>Branch</th>
          <th>Customer</th>
          <th>Model</th>
          <th>Complaint</th>
          <th>Technician</th>
          <th>Status</th>
          <th class="text-end">Cost</th>
          <th class="text-end">Advance</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($jobs)): ?>
          <tr><td colspan="10" class="text-center text-muted py-3">No jobs found</td></tr>
        <?php else: ?>
          <?php foreach ($jobs as $j): ?>
            <?php
              $statusClasses = [
                'received'  => 'bg-secondary',
                'diagnosed' => 'bg-info',
                'in_repair' => 'bg-warning text-dark',
                'ready'     => 'bg-primary',
                'delivered' => 'bg-success',
              ];
              $sc = $statusClasses[$j['status']] ?? 'bg-secondary';
            ?>
            <tr>
              <td><code><?= clean($j['job_no']) ?></code></td>
              <td><?= date('d-m-Y', strtotime($j['received_at'])) ?></td>
              <td><span class="badge bg-primary"><?= clean($j['branch']) ?></span></td>
              <td>
                <?= clean($j['customer_name']) ?>
                <?php if ($j['customer_phone']): ?>
                  <br><small class="text-muted"><?= clean($j['customer_phone']) ?></small>
                <?php endif; ?>
              </td>
              <td><?= clean($j['model'] ?? '-') ?></td>
              <td class="small"><?= clean(mb_substr($j['complaint'] ?? '-', 0, 50)) ?><?= strlen($j['complaint'] ?? '') > 50 ? '…' : '' ?></td>
              <td><?= clean($j['technician'] ?? '-') ?></td>
              <td><span class="badge <?= $sc ?>"><?= ucfirst(str_replace('_', ' ', $j['status'])) ?></span></td>
              <td class="text-end"><?= money((float)$j['repair_cost']) ?></td>
              <td class="text-end"><?= money((float)$j['advance_paid']) ?></td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<script>
(function() {
  const ctx = document.getElementById('statusChart').getContext('2d');
  new Chart(ctx, {
    type: 'doughnut',
    data: {
      labels: ['Received', 'In Repair', 'Ready', 'Delivered'],
      datasets: [{
        data: [
          <?= (int)$summary['received'] ?>,
          <?= (int)$summary['in_repair'] ?>,
          <?= (int)$summary['ready'] ?>,
          <?= (int)$summary['delivered'] ?>
        ],
        backgroundColor: ['#6c757d', '#ffc107', '#0d6efd', '#28a745'],
        borderWidth: 2
      }]
    },
    options: {
      responsive: true,
      plugins: {
        legend: { position: 'bottom' },
        tooltip: { callbacks: { label: ctx => ctx.label + ': ' + ctx.parsed } }
      }
    }
  });
})();
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
