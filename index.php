<?php
/**
 * Rani Mobiles ERP — Login Page
 */
require_once __DIR__ . '/config/auth.php';

// Redirect if already logged in
if (!empty($_SESSION['user_id'])) {
    header('Location: ' . BASE_URL . '/dashboard.php');
    exit;
}

$error = '';

$distanceMeters = static function (float $lat1, float $lon1, float $lat2, float $lon2): float {
  $earthRadius = 6371000.0;
  $dLat = deg2rad($lat2 - $lat1);
  $dLon = deg2rad($lon2 - $lon1);

  $a = sin($dLat / 2) * sin($dLat / 2)
    + cos(deg2rad($lat1)) * cos(deg2rad($lat2))
    * sin($dLon / 2) * sin($dLon / 2);

  return $earthRadius * (2 * atan2(sqrt($a), sqrt(1 - $a)));
};

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

  $gpsLatRaw      = trim((string)($_POST['gps_lat'] ?? ''));
  $gpsLngRaw      = trim((string)($_POST['gps_lng'] ?? ''));
  $gpsAccuracyRaw = trim((string)($_POST['gps_accuracy'] ?? ''));
  $gpsErrorRaw    = trim((string)($_POST['gps_error'] ?? ''));

  $gpsLat = (is_numeric($gpsLatRaw) && (float)$gpsLatRaw >= -90 && (float)$gpsLatRaw <= 90)
    ? round((float)$gpsLatRaw, 8)
    : null;
  $gpsLng = (is_numeric($gpsLngRaw) && (float)$gpsLngRaw >= -180 && (float)$gpsLngRaw <= 180)
    ? round((float)$gpsLngRaw, 8)
    : null;
  $gpsAccuracy = (is_numeric($gpsAccuracyRaw) && (float)$gpsAccuracyRaw >= 0)
    ? round((float)$gpsAccuracyRaw, 2)
    : null;
  $gpsError = $gpsErrorRaw !== '' ? clean($gpsErrorRaw) : '';

    if ($username && $password) {
        $stmt = db()->prepare(
            'SELECT u.id, u.username, u.password, u.full_name, u.branch_id, u.is_active,
          r.name AS role, b.code AS branch_code, b.name AS branch_name,
          b.latitude AS branch_latitude, b.longitude AS branch_longitude, b.radius_m
             FROM users u
             JOIN roles    r ON r.id = u.role_id
             JOIN branches b ON b.id = u.branch_id
             WHERE u.username = ?'
        );
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if ($user && $user['is_active'] && password_verify($password, $user['password'])) {
            session_regenerate_id(true);
            $_SESSION['user_id']   = $user['id'];
            $_SESSION['user_name'] = $user['full_name'];
            $_SESSION['role']      = $user['role'];
            $_SESSION['branch_id'] = $user['branch_id'];
            $_SESSION['branch']    = $user['branch_code'];
            $_SESSION['last_regenerated'] = time();

            log_activity('LOGIN', 'User logged in from ' . ($_SERVER['REMOTE_ADDR'] ?? ''));

            // Record attendance
            $today = date('Y-m-d');
            $isLate = 0;
            $settingsStmt = db()->prepare("SELECT key_value FROM system_settings WHERE key_name='login_end_time' LIMIT 1");
            $settingsStmt->execute();
            $loginEndTime = (string)($settingsStmt->fetchColumn() ?: '');
            if ($loginEndTime !== '') {
              $cutoff = strtotime(date('Y-m-d') . ' ' . $loginEndTime);
              if ($cutoff !== false && time() > $cutoff) {
                $isLate = 1;
              }
            }

            $notes = [];
            if ($gpsError !== '') {
              $notes[] = 'GPS: ' . $gpsError;
            }
            if ($gpsAccuracy !== null) {
              $notes[] = 'Accuracy: ' . $gpsAccuracy . 'm';
            }

            $branchLat = isset($user['branch_latitude']) && $user['branch_latitude'] !== null ? (float)$user['branch_latitude'] : null;
            $branchLng = isset($user['branch_longitude']) && $user['branch_longitude'] !== null ? (float)$user['branch_longitude'] : null;
            $radiusM = max(50, (int)($user['radius_m'] ?? 100));

            if ($gpsLat !== null && $gpsLng !== null && $branchLat !== null && $branchLng !== null) {
              $distance = round($distanceMeters($gpsLat, $gpsLng, $branchLat, $branchLng), 2);
              $notes[] = 'Distance: ' . $distance . 'm';
              if ($distance > $radiusM) {
                $notes[] = 'Outside radius';
              }
            }

            db()->prepare(
              'INSERT IGNORE INTO staff_attendance
               (user_id, branch_id, date, login_time, latitude, longitude, is_late, notes)
               VALUES (?,?,?,NOW(),?,?,?,?)'
            )->execute([
              $user['id'],
              $user['branch_id'],
              $today,
              $gpsLat,
              $gpsLng,
              $isLate,
              $notes ? implode(' | ', $notes) : null,
            ]);

            header('Location: ' . BASE_URL . '/dashboard.php');
            exit;
        } else {
            $error = 'Invalid username or password.';
        }
    } else {
        $error = 'Please enter username and password.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Login — Rani Mobiles ERP</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.min.css">
<style>
  body {
    background: linear-gradient(135deg, #1a1a2e 0%, #16213e 50%, #0f3460 100%);
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    font-family: 'Segoe UI', sans-serif;
  }
  .login-card {
    background: rgba(255,255,255,0.05);
    backdrop-filter: blur(20px);
    border: 1px solid rgba(255,255,255,0.1);
    border-radius: 20px;
    padding: 2.5rem;
    width: 100%;
    max-width: 420px;
    box-shadow: 0 20px 60px rgba(0,0,0,0.4);
    animation: slideUp 0.5s ease;
  }
  @keyframes slideUp {
    from { opacity:0; transform:translateY(30px); }
    to   { opacity:1; transform:translateY(0); }
  }
  .brand-logo {
    width: 80px; height: 80px;
    background: linear-gradient(135deg, #e94560, #0f3460);
    border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    margin: 0 auto 1.5rem;
    font-size: 2rem;
    box-shadow: 0 8px 25px rgba(233,69,96,0.4);
  }
  .form-control {
    background: rgba(255,255,255,0.08);
    border: 1px solid rgba(255,255,255,0.15);
    color: #fff;
    border-radius: 10px;
    padding: 0.75rem 1rem;
  }
  .form-control:focus {
    background: rgba(255,255,255,0.12);
    border-color: #e94560;
    color: #fff;
    box-shadow: 0 0 0 0.2rem rgba(233,69,96,0.25);
  }
  .form-control::placeholder { color: rgba(255,255,255,0.4); }
  .btn-login {
    background: linear-gradient(135deg, #e94560, #c2185b);
    border: none;
    border-radius: 10px;
    padding: 0.75rem;
    font-weight: 600;
    letter-spacing: 0.5px;
    transition: all 0.3s;
  }
  .btn-login:hover { transform: translateY(-2px); box-shadow: 0 8px 20px rgba(233,69,96,0.4); }
  .input-group-text {
    background: rgba(255,255,255,0.08);
    border: 1px solid rgba(255,255,255,0.15);
    color: rgba(255,255,255,0.6);
    border-radius: 10px 0 0 10px;
  }
</style>
</head>
<body>
<div class="login-card text-center text-white">
  <div class="brand-logo">📱</div>
  <h4 class="fw-bold mb-1">Rani Mobiles ERP</h4>
  <p class="text-white-50 mb-4">Sales &amp; Service Management</p>

  <?php if ($error): ?>
    <div class="alert alert-danger alert-dismissible py-2" role="alert">
      <i class="bi bi-exclamation-triangle me-1"></i><?= clean($error) ?>
      <button type="button" class="btn-close btn-close-white" data-bs-dismiss="alert"></button>
    </div>
  <?php endif; ?>

  <form method="POST" autocomplete="off" id="loginForm">
    <input type="hidden" name="gps_lat" id="gpsLat" value="">
    <input type="hidden" name="gps_lng" id="gpsLng" value="">
    <input type="hidden" name="gps_accuracy" id="gpsAccuracy" value="">
    <input type="hidden" name="gps_error" id="gpsError" value="">
    <div class="mb-3">
      <div class="input-group">
        <span class="input-group-text"><i class="bi bi-person"></i></span>
        <input type="text" name="username" class="form-control" placeholder="Username" required autofocus>
      </div>
    </div>
    <div class="mb-4">
      <div class="input-group">
        <span class="input-group-text"><i class="bi bi-lock"></i></span>
        <input type="password" name="password" id="passwordInput" class="form-control" placeholder="Password" required>
        <button type="button" class="btn btn-outline-secondary" onclick="togglePwd()" style="border-color:rgba(255,255,255,.15);color:rgba(255,255,255,.6)">
          <i class="bi bi-eye" id="eyeIcon"></i>
        </button>
      </div>
    </div>
    <button type="submit" class="btn btn-login btn-primary w-100">
      <i class="bi bi-box-arrow-in-right me-2"></i>Sign In
    </button>
  </form>
  <div id="gpsStatus" class="mt-2 text-white-50 small">Checking GPS location...</div>
  <p class="mt-3 text-white-50 small">Default: admin / Admin@1234</p>
</div>
<script>
function togglePwd() {
  const inp = document.getElementById('passwordInput');
  const ico = document.getElementById('eyeIcon');
  if (inp.type === 'password') { inp.type='text'; ico.className='bi bi-eye-slash'; }
  else                          { inp.type='password'; ico.className='bi bi-eye'; }
}

(function initGpsCapture() {
  const statusEl = document.getElementById('gpsStatus');
  const latEl = document.getElementById('gpsLat');
  const lngEl = document.getElementById('gpsLng');
  const accEl = document.getElementById('gpsAccuracy');
  const errEl = document.getElementById('gpsError');

  if (!navigator.geolocation) {
    statusEl.textContent = 'GPS not supported by this browser.';
    errEl.value = 'Browser does not support geolocation';
    return;
  }

  navigator.geolocation.getCurrentPosition(
    function (position) {
      latEl.value = position.coords.latitude.toFixed(8);
      lngEl.value = position.coords.longitude.toFixed(8);
      accEl.value = Number(position.coords.accuracy || 0).toFixed(2);
      errEl.value = '';
      statusEl.textContent = 'GPS location captured.';
    },
    function (error) {
      const messages = {
        1: 'Location permission denied',
        2: 'Location unavailable',
        3: 'Location timeout'
      };
      const msg = messages[error.code] || 'Unknown GPS error';
      errEl.value = msg;
      statusEl.textContent = 'GPS unavailable: ' + msg;
    },
    {
      enableHighAccuracy: true,
      timeout: 10000,
      maximumAge: 60000
    }
  );
})();
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
