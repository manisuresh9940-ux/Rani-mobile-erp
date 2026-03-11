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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username && $password) {
        $stmt = db()->prepare(
            'SELECT u.id, u.username, u.password, u.full_name, u.branch_id, u.is_active,
                    r.name AS role, b.code AS branch_code, b.name AS branch_name
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
            $settings = db()->prepare("SELECT key_value FROM system_settings WHERE key_name='login_end_time'")->execute() ? null : null;
            db()->prepare(
                'INSERT IGNORE INTO staff_attendance (user_id, branch_id, date, login_time)
                 VALUES (?,?,?,NOW())'
            )->execute([$user['id'], $user['branch_id'], $today]);

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

  <form method="POST" autocomplete="off">
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
  <p class="mt-3 text-white-50 small">Default: admin / Admin@1234</p>
</div>
<script>
function togglePwd() {
  const inp = document.getElementById('passwordInput');
  const ico = document.getElementById('eyeIcon');
  if (inp.type === 'password') { inp.type='text'; ico.className='bi bi-eye-slash'; }
  else                          { inp.type='password'; ico.className='bi bi-eye'; }
}
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
