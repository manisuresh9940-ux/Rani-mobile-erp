<?php
/**
 * Rani Mobiles ERP — Service Job Cards List
 */
require_once __DIR__ . '/../../config/auth.php';
require_auth();

$user      = current_user();
$branch_id = (int)$user['branch_id'];
$isAdmin   = is_admin();
$pdo       = db();

$fStatus = $_GET['status'] ?? '';
$search  = trim($_GET['s'] ?? '');

$where  = ['1=1'];
$params = [];
if (!$isAdmin) {
    $where[] = 'j.branch_id=?'; $params[] = $branch_id;
}
if ($fStatus) {
    $where[] = 'j.status=?'; $params[] = $fStatus;
}
if ($search) {
    $where[] = '(j.job_no LIKE ? OR j.customer_name LIKE ? OR j.imei LIKE ? OR j.model LIKE ?)';
    $params[] = "%$search%"; $params[] = "%$search%"; $params[] = "%$search%"; $params[] = "%$search%";
}

$jobs = $pdo->prepare(
    'SELECT j.*, b.code AS branch_code, br.name AS brand_name, u.full_name AS tech_name
     FROM service_jobs j
     JOIN branches b ON b.id=j.branch_id
     LEFT JOIN brands br ON br.id=j.brand_id
     LEFT JOIN users u ON u.id=j.technician_id
     WHERE ' . implode(' AND ', $where) . ' ORDER BY j.id DESC LIMIT 200'
);
$jobs->execute($params);
$jobs = $jobs->fetchAll();

$pageTitle = 'Job Cards';
?>
<?php include __DIR__ . '/../../includes/header.php'; ?>
<script>window.BASE_URL = '<?= BASE_URL ?>';</script>

<div class="page-header">
  <h1 class="page-title"><i class="bi bi-tools me-2"></i>Service Job Cards</h1>
  <a href="<?= BASE_URL ?>/modules/service/add.php" class="btn btn-primary btn-sm">
    <i class="bi bi-plus-circle me-1"></i>New Job Card
  </a>
</div>

<!-- Status filter pills -->
<div class="mb-3 d-flex gap-2 flex-wrap">
  <?php
    $statusList = [''=>'All','received'=>'Received','diagnosed'=>'Diagnosed',
                   'in_repair'=>'In Repair','ready'=>'Ready','delivered'=>'Delivered'];
    foreach ($statusList as $val => $label):
  ?>
    <a href="?status=<?= urlencode($val) ?><?= $search?"&s=".urlencode($search):'' ?>"
       class="btn btn-sm <?= $fStatus===$val?'btn-primary':'btn-outline-secondary' ?>">
      <?= $label ?> (<?= count(array_filter($jobs, fn($j) => $val==='' || $j['status']===$val)) ?>)
    </a>
  <?php endforeach; ?>
</div>

<div class="erp-form-card mb-3">
  <form method="GET" class="row g-2 align-items-end">
    <input type="hidden" name="status" value="<?= clean($fStatus) ?>">
    <div class="col-md-4">
      <input type="text" name="s" class="form-control form-control-sm"
             placeholder="Job No, customer, IMEI, model…" value="<?= clean($search) ?>">
    </div>
    <div class="col-auto">
      <button type="submit" class="btn btn-primary btn-sm">Search</button>
    </div>
  </form>
</div>

<div class="erp-table">
  <div class="table-responsive">
    <table class="table table-hover mb-0">
      <thead>
        <tr><th>#</th><th>Job No</th><th>Customer</th><th>Device</th><th>IMEI</th>
            <th>Technician</th><th class="text-end">Cost</th><th>Status</th><th>Rating</th><th>Received</th><th>Actions</th></tr>
      </thead>
      <tbody>
        <?php if (empty($jobs)): ?>
          <tr><td colspan="11" class="text-center text-muted py-4">No job cards found</td></tr>
        <?php else: ?>
          <?php foreach ($jobs as $i => $j): ?>
            <tr>
              <td><?= $i+1 ?></td>
              <td><code><?= clean($j['job_no']) ?></code></td>
              <td>
                <div><?= clean($j['customer_name']) ?></div>
                <small class="text-muted"><?= clean($j['customer_phone']) ?></small>
              </td>
              <td><?= clean($j['brand_name'] ?? '') ?> <?= clean($j['model'] ?? '') ?></td>
              <td><?= clean($j['imei'] ?? '-') ?></td>
              <td><?= clean($j['tech_name'] ?? 'Unassigned') ?></td>
              <td class="text-end"><?= money((float)$j['repair_cost']) ?></td>
              <td>
                <span class="badge status-<?= $j['status'] ?> px-2 py-1">
                  <?= ucwords(str_replace('_', ' ', $j['status'])) ?>
                </span>
              </td>
              <td><?= date('d-m-Y', strtotime($j['received_at'])) ?></td>
              <td>
                <?php if (!empty($j['rating'])): ?>
                  <span class="text-warning" title="<?= (int)$j['rating'] ?>/5 stars">
                    <?= str_repeat('★', (int)$j['rating']) ?><?= str_repeat('☆', 5 - (int)$j['rating']) ?>
                  </span>
                <?php else: ?>
                  <span class="text-muted small">—</span>
                <?php endif; ?>
              </td>
              <td>
                <button class="btn btn-sm btn-outline-primary" onclick="updateStatus(<?= (int)$j['id'] ?>, '<?= htmlspecialchars($j['status'], ENT_QUOTES, 'UTF-8') ?>', <?= (int)($j['rating'] ?? 0) ?>)">
                  <i class="bi bi-pencil"></i>
                </button>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Status update modal -->
<div class="modal fade" id="statusModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="POST" action="<?= BASE_URL ?>/api/update_job_status.php">
        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
        <input type="hidden" name="job_id" id="modalJobId">
        <input type="hidden" name="rating" id="modalRatingValue" value="0">
        <div class="modal-header">
          <h5 class="modal-title">Update Job Status</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <label class="form-label">Status</label>
          <select name="status" id="modalStatus" class="form-select">
            <option value="received">Received</option>
            <option value="diagnosed">Diagnosed</option>
            <option value="in_repair">In Repair</option>
            <option value="ready">Ready for Pickup</option>
            <option value="delivered">Delivered</option>
          </select>
          <div class="mt-2">
            <label class="form-label">Notes</label>
            <textarea name="notes" class="form-control" rows="2"></textarea>
          </div>
          <!-- Customer satisfaction rating (shown only when Delivered) -->
          <div id="ratingSection" class="mt-3" style="display:none">
            <label class="form-label fw-semibold">Customer Satisfaction</label>
            <div id="ratingWidget"></div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary">Update</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
/* ─── Raty star-image base path (CDN) ─────────────────────── */
const RATY_IMG = 'https://cdn.jsdelivr.net/npm/jquery.raty@2.9.0/lib/images/';

function initRaty(score) {
  if (typeof $.fn.raty === 'undefined') return;
  $('#ratingWidget').raty({
    path:    RATY_IMG,
    score:   score || 0,
    number:  5,
    hints:   ['Poor', 'Fair', 'Good', 'Very Good', 'Excellent'],
    click: function(s) {
      document.getElementById('modalRatingValue').value = s;
    }
  });
  document.getElementById('modalRatingValue').value = score || 0;
}

function toggleRatingSection(status, score) {
  const ratingSection = document.getElementById('ratingSection');
  if (status === 'delivered') {
    ratingSection.style.display = 'block';
    initRaty(score || 0);
  } else {
    ratingSection.style.display = 'none';
  }
}

function updateStatus(id, currentStatus, currentRating) {
  document.getElementById('modalJobId').value  = id;
  document.getElementById('modalStatus').value = currentStatus;
  toggleRatingSection(currentStatus, currentRating);
  new bootstrap.Modal(document.getElementById('statusModal')).show();
}

/* Show/hide rating section when status changes inside the modal */
document.getElementById('modalStatus').addEventListener('change', function () {
  toggleRatingSection(this.value, parseInt(document.getElementById('modalRatingValue').value) || 0);
});
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
