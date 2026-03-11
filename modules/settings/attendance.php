<?php
/**
 * Rani Mobiles ERP — Staff GPS Attendance
 */
require_once __DIR__ . '/../../config/auth.php';
require_auth();

$user      = current_user();
$branch_id = (int)$user['branch_id'];
$isAdmin   = is_admin();
$pdo       = db();

$today    = date('Y-m-d');
$viewDate = $_GET['date'] ?? $today;
$viewUser = (int)($_GET['user'] ?? 0);
$fBranch  = (int)($_GET['branch'] ?? 0);
$message  = $error = '';

// Handle GPS check-in/check-out via AJAX POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    verify_csrf();

    $action = $_POST['action'];
    $lat    = (float)($_POST['lat'] ?? 0);
    $lng    = (float)($_POST['lng'] ?? 0);

    if ($action === 'checkin') {
        // Get branch GPS config
        $b = $pdo->prepare('SELECT latitude, longitude, radius_m FROM branches WHERE id=?');
        $b->execute([$branch_id]);
        $branch = $b->fetch();

        $withinRadius = true;
        if ($branch && $branch['latitude'] && $branch['longitude'] && $lat && $lng) {
            $dist = haversine_distance($lat, $lng, (float)$branch['latitude'], (float)$branch['longitude']);
            $withinRadius = $dist <= ($branch['radius_m'] ?: 100);
        }

        if (!$withinRadius) {
            echo json_encode(['success' => false, 'message' => 'You are not within ' . ($branch['radius_m'] ?: 100) . 'm of your branch.']);
            exit;
        }

        // Check login time restriction
        $settings = [];
        foreach ($pdo->query("SELECT key_name, key_value FROM system_settings WHERE key_name IN ('login_start_time','login_end_time')") as $r) {
            $settings[$r['key_name']] = $r['key_value'];
        }
        $now = date('H:i');
        if (!empty($settings['login_start_time']) && $now < $settings['login_start_time']) {
            echo json_encode(['success' => false, 'message' => 'Login not allowed before ' . $settings['login_start_time']]);
            exit;
        }
        if (!empty($settings['login_end_time']) && $now > $settings['login_end_time']) {
            echo json_encode(['success' => false, 'message' => 'Login not allowed after ' . $settings['login_end_time']]);
            exit;
        }

        $pdo->prepare(
            'INSERT INTO staff_attendance (user_id, branch_id, date, login_time, latitude, longitude)
             VALUES (?,?,?,NOW(),?,?)
             ON DUPLICATE KEY UPDATE login_time=NOW(), latitude=?, longitude=?'
        )->execute([$user['id'], $branch_id, $today, $lat, $lng, $lat, $lng]);

        log_activity('CHECKIN', "GPS: $lat,$lng");
        echo json_encode(['success' => true, 'message' => 'Check-in recorded at ' . date('H:i')]);
        exit;
    }

    if ($action === 'checkout') {
        $existing = $pdo->prepare('SELECT id FROM staff_attendance WHERE user_id=? AND date=?');
        $existing->execute([$user['id'], $today]);
        if (!$existing->fetchColumn()) {
            echo json_encode(['success' => false, 'message' => 'No check-in found for today.']);
            exit;
        }

        $pdo->prepare(
            'UPDATE staff_attendance SET logout_time=NOW() WHERE user_id=? AND date=?'
        )->execute([$user['id'], $today]);

        log_activity('CHECKOUT', 'GPS check-out');
        echo json_encode(['success' => true, 'message' => 'Check-out recorded at ' . date('H:i')]);
        exit;
    }

    echo json_encode(['success' => false, 'message' => 'Unknown action']);
    exit;
}

// ── Load attendance records ─────────────────────────────────────
$where  = ['sa.date=?'];
$params = [$viewDate];

if ($isAdmin) {
    if ($viewUser) { $where[] = 'sa.user_id=?'; $params[] = $viewUser; }
    if ($fBranch)  { $where[] = 'sa.branch_id=?'; $params[] = $fBranch; }
} else {
    $where[] = 'sa.branch_id=?';
    $params[] = $branch_id;
}

$records = $pdo->prepare(
    'SELECT sa.*, u.full_name, u.username, b.code AS branch_code, b.name AS branch_name,
            b.latitude AS b_lat, b.longitude AS b_lng
     FROM staff_attendance sa
     JOIN users u ON u.id=sa.user_id
     JOIN branches b ON b.id=sa.branch_id
     WHERE ' . implode(' AND ', $where) . '
     ORDER BY sa.login_time ASC'
);
$records->execute($params);
$records = $records->fetchAll();

// My today attendance
$myAttendance = $pdo->prepare('SELECT * FROM staff_attendance WHERE user_id=? AND date=?');
$myAttendance->execute([$user['id'], $today]);
$myToday = $myAttendance->fetch();

// Staff & branch lists for filter
$staffList = $pdo->query('SELECT id, full_name FROM users WHERE is_active=1 ORDER BY full_name')->fetchAll();
$branches  = $pdo->query('SELECT id, code, name FROM branches ORDER BY code')->fetchAll();

$pageTitle = 'Staff Attendance';

/**
 * Haversine distance in metres between two GPS coordinates.
 */
function haversine_distance(float $lat1, float $lon1, float $lat2, float $lon2): float {
    $R = 6371000;
    $dLat = deg2rad($lat2 - $lat1);
    $dLon = deg2rad($lon2 - $lon1);
    $a = sin($dLat/2)**2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon/2)**2;
    return $R * 2 * atan2(sqrt($a), sqrt(1-$a));
}
?>
<?php include __DIR__ . '/../../includes/header.php'; ?>
<script>window.BASE_URL = '<?= BASE_URL ?>';</script>

<div class="page-header">
  <div>
    <h1 class="page-title"><i class="bi bi-geo-alt me-2"></i>Staff GPS Attendance</h1>
    <p class="page-subtitle"><?= date('l, d F Y') ?></p>
  </div>
  <a href="<?= BASE_URL ?>/api/export_report.php?type=attendance&date=<?= urlencode($viewDate) ?>"
     class="btn btn-outline-success btn-sm">
    <i class="bi bi-download me-1"></i>Export CSV
  </a>
</div>

<!-- My Attendance Card -->
<div class="erp-form-card mb-3">
  <div class="card-heading"><i class="bi bi-person-check"></i>My Attendance — <?= date('d M Y') ?></div>
  <div class="row g-3 align-items-center">
    <div class="col-md-4">
      <div class="d-flex gap-3 align-items-center">
        <div class="kpi-icon" style="background:rgba(40,167,69,.15);color:#28a745;width:56px;height:56px;border-radius:14px;font-size:1.6rem;display:flex;align-items:center;justify-content:center;">
          <i class="bi bi-clock"></i>
        </div>
        <div>
          <div class="fw-bold">Check-In</div>
          <div class="text-muted"><?= $myToday ? date('H:i', strtotime($myToday['login_time'])) : '—' ?></div>
        </div>
      </div>
    </div>
    <div class="col-md-4">
      <div class="d-flex gap-3 align-items-center">
        <div class="kpi-icon" style="background:rgba(220,53,69,.15);color:#dc3545;width:56px;height:56px;border-radius:14px;font-size:1.6rem;display:flex;align-items:center;justify-content:center;">
          <i class="bi bi-clock-history"></i>
        </div>
        <div>
          <div class="fw-bold">Check-Out</div>
          <div class="text-muted">
            <?= ($myToday && $myToday['logout_time']) ? date('H:i', strtotime($myToday['logout_time'])) : '—' ?>
          </div>
        </div>
      </div>
    </div>
    <div class="col-md-4">
      <div id="gpsStatus" class="text-muted small mb-2"><i class="bi bi-geo-alt me-1"></i>Location not yet obtained</div>
      <div class="d-flex gap-2">
        <button class="btn btn-success btn-sm" id="btnCheckin" onclick="doAttendance('checkin')">
          <i class="bi bi-box-arrow-in-right me-1"></i>Check In
        </button>
        <button class="btn btn-danger btn-sm" id="btnCheckout" onclick="doAttendance('checkout')">
          <i class="bi bi-box-arrow-right me-1"></i>Check Out
        </button>
      </div>
    </div>
  </div>
</div>

<!-- Filters -->
<div class="erp-form-card mb-3">
  <form method="GET" class="row g-2 align-items-end">
    <div class="col-md-2">
      <label class="form-label">Date</label>
      <input type="date" name="date" class="form-control form-control-sm" value="<?= $viewDate ?>">
    </div>
    <?php if ($isAdmin): ?>
      <div class="col-md-2">
        <label class="form-label">Branch</label>
        <select name="branch" class="form-select form-select-sm">
          <option value="">All Branches</option>
          <?php foreach ($branches as $b): ?>
            <option value="<?= $b['id'] ?>" <?= $fBranch==$b['id']?'selected':'' ?>>
              <?= clean($b['code']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-3">
        <label class="form-label">Staff Member</label>
        <select name="user" class="form-select form-select-sm">
          <option value="">All Staff</option>
          <?php foreach ($staffList as $s): ?>
            <option value="<?= $s['id'] ?>" <?= $viewUser==$s['id']?'selected':'' ?>>
              <?= clean($s['full_name']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
    <?php endif; ?>
    <div class="col-auto">
      <button type="submit" class="btn btn-primary btn-sm">
        <i class="bi bi-search me-1"></i>Filter
      </button>
    </div>
  </form>
</div>

<!-- Attendance Records Table -->
<div class="erp-table">
  <div class="p-3 border-bottom">
    <strong><i class="bi bi-table me-2"></i>Attendance — <?= fmt_date($viewDate) ?>
      (<?= count($records) ?> records)</strong>
  </div>
  <div class="table-responsive">
    <table class="table table-hover mb-0">
      <thead>
        <tr>
          <th>#</th>
          <th>Staff</th>
          <th>Branch</th>
          <th>Check-In</th>
          <th>Check-Out</th>
          <th>Work Duration</th>
          <th>Location</th>
          <th>Status</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($records)): ?>
          <tr><td colspan="8" class="text-center text-muted py-4">
            <i class="bi bi-calendar-x fs-2"></i><br>No attendance records for <?= fmt_date($viewDate) ?>
          </td></tr>
        <?php else: ?>
          <?php foreach ($records as $i => $r): ?>
            <?php
              $loginDt  = $r['login_time'] ? new DateTime($r['login_time']) : null;
              $logoutDt = $r['logout_time'] ? new DateTime($r['logout_time']) : null;
              $duration = '—';
              if ($loginDt && $logoutDt) {
                  $diff = $loginDt->diff($logoutDt);
                  $duration = sprintf('%dh %dm', $diff->h + $diff->days * 24, $diff->i);
              }
            ?>
            <tr>
              <td><?= $i + 1 ?></td>
              <td>
                <div class="fw-semibold"><?= clean($r['full_name']) ?></div>
                <small class="text-muted"><?= clean($r['username']) ?></small>
              </td>
              <td><span class="badge bg-primary"><?= clean($r['branch_code']) ?></span></td>
              <td>
                <?php if ($loginDt): ?>
                  <span class="text-success fw-semibold"><?= $loginDt->format('H:i') ?></span>
                <?php else: ?>
                  <span class="text-muted">—</span>
                <?php endif; ?>
              </td>
              <td>
                <?php if ($logoutDt): ?>
                  <span class="text-danger fw-semibold"><?= $logoutDt->format('H:i') ?></span>
                <?php else: ?>
                  <span class="badge bg-warning text-dark">Still In</span>
                <?php endif; ?>
              </td>
              <td><?= $duration ?></td>
              <td>
                <?php if ($r['latitude'] && $r['longitude']): ?>
                  <a href="https://maps.google.com/?q=<?= $r['latitude'] ?>,<?= $r['longitude'] ?>"
                     target="_blank" class="btn btn-xs btn-outline-secondary btn-sm">
                    <i class="bi bi-map me-1"></i>Map
                  </a>
                <?php else: ?>
                  <span class="text-muted small">—</span>
                <?php endif; ?>
              </td>
              <td>
                <?php if ($r['is_late']): ?>
                  <span class="badge bg-danger">Late</span>
                <?php else: ?>
                  <span class="badge bg-success">On Time</span>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<script>
let userLat = null, userLng = null;

// Try to get location on page load
if (navigator.geolocation) {
  navigator.geolocation.getCurrentPosition(
    pos => {
      userLat = pos.coords.latitude;
      userLng = pos.coords.longitude;
      document.getElementById('gpsStatus').innerHTML =
        '<i class="bi bi-geo-alt-fill text-success me-1"></i>Location obtained (' +
        userLat.toFixed(5) + ', ' + userLng.toFixed(5) + ')';
    },
    err => {
      document.getElementById('gpsStatus').innerHTML =
        '<i class="bi bi-exclamation-triangle text-warning me-1"></i>Location not available: ' + err.message;
    },
    { enableHighAccuracy: true, timeout: 10000 }
  );
}

async function doAttendance(action) {
  const btn = document.getElementById(action === 'checkin' ? 'btnCheckin' : 'btnCheckout');
  btn.disabled = true;

  try {
    const fd = new FormData();
    fd.append('action', action);
    fd.append('csrf_token', '<?= csrf_token() ?>');
    if (userLat) { fd.append('lat', userLat); fd.append('lng', userLng); }

    const res  = await fetch(location.href, { method: 'POST', body: fd });
    const data = await res.json();

    showToast(data.message, data.success ? 'success' : 'error');
    if (data.success) setTimeout(() => location.reload(), 1500);
  } catch(e) {
    showToast('Request failed: ' + e.message, 'error');
  } finally {
    btn.disabled = false;
  }
}
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
