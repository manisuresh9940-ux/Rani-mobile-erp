<?php
/**
 * Rani Mobiles ERP — User Self-Registration Page
 * New staff members register here; accounts require Admin approval before use.
 */
require_once __DIR__ . '/config/database.php';

// Redirect if already logged in
session_set_cookie_params([
    'lifetime' => SESSION_LIFETIME,
    'path'     => '/',
    'httponly' => true,
    'samesite' => 'Strict',
]);
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!empty($_SESSION['user_id'])) {
    header('Location: ' . BASE_URL . '/dashboard.php');
    exit;
}

// CSRF token (reuse session-based approach)
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$error   = '';
$success = '';

// Fetch branches and (non-Admin) roles for the form
$pdo      = db();
$branches = $pdo->query('SELECT id, code, name FROM branches WHERE is_active=1 ORDER BY code')->fetchAll();
$roles    = $pdo->query("SELECT id, name FROM roles WHERE name != 'Admin' ORDER BY id")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF
    $token = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
        http_response_code(403);
        die('CSRF validation failed.');
    }

    $full_name  = trim($_POST['full_name'] ?? '');
    $username   = trim($_POST['username']  ?? '');
    $password   = $_POST['password']       ?? '';
    $confirm    = $_POST['confirm_password'] ?? '';
    $branch_id  = (int)($_POST['branch_id'] ?? 0);
    $role_id    = (int)($_POST['role_id']   ?? 0);
    $phone      = trim($_POST['phone']      ?? '');
    $email      = trim($_POST['email']      ?? '');

    // Validation
    if (!$full_name || !$username || !$password || !$branch_id || !$role_id) {
        $error = 'Full name, username, password, branch and role are required.';
    } elseif (strlen($password) < 8) {
        $error = 'Password must be at least 8 characters long.';
    } elseif ($password !== $confirm) {
        $error = 'Passwords do not match.';
    } elseif (!preg_match('/^[a-zA-Z0-9_\.]+$/', $username) || preg_match('/\.{2,}|^\.|\.+$/', $username)) {
        $error = 'Username may only contain letters, numbers, underscores and single dots (not at start or end).';
    } else {
        // Check for duplicate username
        $check = $pdo->prepare('SELECT id FROM users WHERE username = ?');
        $check->execute([$username]);
        if ($check->fetch()) {
            $error = 'Username already taken. Please choose a different username.';
        } else {
            // Verify selected role is not Admin
            $roleCheck = $pdo->prepare("SELECT name FROM roles WHERE id = ?");
            $roleCheck->execute([$role_id]);
            $roleName = $roleCheck->fetchColumn();
            if ($roleName === 'Admin') {
                $error = 'Admin role cannot be selected during registration.';
            } else {
                try {
                    $pdo->prepare(
                        'INSERT INTO users (branch_id, role_id, username, password, full_name, phone, email, is_active)
                         VALUES (?, ?, ?, ?, ?, ?, ?, 0)'
                    )->execute([
                        $branch_id,
                        $role_id,
                        $username,
                        password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]),
                        $full_name,
                        $phone,
                        $email,
                    ]);
                    $success = 'Registration submitted successfully! Your account is pending approval by an administrator. You will be able to log in once your account is activated.';
                } catch (Throwable $e) {
                    $error = 'Registration failed. Please try again.';
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Register — Rani Mobiles ERP</title>
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
    padding: 2rem 0;
  }
  .register-card {
    background: rgba(255,255,255,0.05);
    backdrop-filter: blur(20px);
    border: 1px solid rgba(255,255,255,0.1);
    border-radius: 20px;
    padding: 2.5rem;
    width: 100%;
    max-width: 580px;
    box-shadow: 0 20px 60px rgba(0,0,0,0.4);
    animation: slideUp 0.5s ease;
  }
  @keyframes slideUp {
    from { opacity:0; transform:translateY(30px); }
    to   { opacity:1; transform:translateY(0); }
  }
  .brand-logo {
    width: 70px; height: 70px;
    background: linear-gradient(135deg, #e94560, #0f3460);
    border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    margin: 0 auto 1.2rem;
    font-size: 1.8rem;
    box-shadow: 0 8px 25px rgba(233,69,96,0.4);
  }
  .form-label { color: rgba(255,255,255,0.75); font-size: 0.875rem; margin-bottom: 0.3rem; }
  .form-control, .form-select {
    background: rgba(255,255,255,0.08);
    border: 1px solid rgba(255,255,255,0.15);
    color: #fff;
    border-radius: 10px;
    padding: 0.65rem 1rem;
  }
  .form-control:focus, .form-select:focus {
    background: rgba(255,255,255,0.12);
    border-color: #e94560;
    color: #fff;
    box-shadow: 0 0 0 0.2rem rgba(233,69,96,0.25);
  }
  .form-control::placeholder { color: rgba(255,255,255,0.35); }
  .form-select option { background: #16213e; color: #fff; }
  .input-group-text {
    background: rgba(255,255,255,0.08);
    border: 1px solid rgba(255,255,255,0.15);
    color: rgba(255,255,255,0.6);
  }
  .btn-register {
    background: linear-gradient(135deg, #e94560, #c2185b);
    border: none;
    border-radius: 10px;
    padding: 0.75rem;
    font-weight: 600;
    letter-spacing: 0.5px;
    transition: all 0.3s;
  }
  .btn-register:hover { transform: translateY(-2px); box-shadow: 0 8px 20px rgba(233,69,96,0.4); }
  .divider { border-color: rgba(255,255,255,0.15); }
  a.login-link { color: #e94560; text-decoration: none; }
  a.login-link:hover { text-decoration: underline; }
  .pending-notice {
    background: rgba(255,193,7,0.12);
    border: 1px solid rgba(255,193,7,0.3);
    border-radius: 10px;
    padding: 1rem;
    color: rgba(255,255,255,0.85);
    font-size: 0.875rem;
  }
</style>
</head>
<body>
<div class="register-card text-white">
  <div class="brand-logo">📱</div>
  <h4 class="fw-bold text-center mb-1">Create an Account</h4>
  <p class="text-white-50 text-center mb-4 small">Rani Mobiles ERP — Staff Registration</p>

  <?php if ($success): ?>
    <div class="pending-notice mb-3 text-center">
      <i class="bi bi-clock-history fs-3 d-block mb-2 text-warning"></i>
      <strong>Registration Submitted!</strong><br>
      <?= htmlspecialchars($success, ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>
    </div>
    <div class="text-center mt-3">
      <a href="<?= BASE_URL ?>/index.php" class="btn btn-outline-light btn-sm">
        <i class="bi bi-arrow-left me-1"></i>Back to Login
      </a>
    </div>
  <?php else: ?>

    <?php if ($error): ?>
      <div class="alert alert-danger alert-dismissible py-2" role="alert">
        <i class="bi bi-exclamation-triangle me-1"></i><?= htmlspecialchars($error, ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="alert"></button>
      </div>
    <?php endif; ?>

    <form method="POST" autocomplete="off">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>">

      <div class="row g-3">
        <div class="col-12">
          <label class="form-label">Full Name *</label>
          <div class="input-group">
            <span class="input-group-text"><i class="bi bi-person-badge"></i></span>
            <input type="text" name="full_name" class="form-control" placeholder="Your full name"
                   value="<?= htmlspecialchars($_POST['full_name'] ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>" required>
          </div>
        </div>

        <div class="col-md-6">
          <label class="form-label">Username *</label>
          <div class="input-group">
            <span class="input-group-text"><i class="bi bi-person"></i></span>
            <input type="text" name="username" class="form-control" placeholder="Choose a username"
                   value="<?= htmlspecialchars($_POST['username'] ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>"
                   pattern="[a-zA-Z0-9_\.]+" title="Letters, numbers, underscores and dots only" required>
          </div>
        </div>

        <div class="col-md-6">
          <label class="form-label">Phone</label>
          <div class="input-group">
            <span class="input-group-text"><i class="bi bi-telephone"></i></span>
            <input type="tel" name="phone" class="form-control" placeholder="Mobile number"
                   value="<?= htmlspecialchars($_POST['phone'] ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>">
          </div>
        </div>

        <div class="col-md-6">
          <label class="form-label">Password *</label>
          <div class="input-group">
            <span class="input-group-text"><i class="bi bi-lock"></i></span>
            <input type="password" name="password" id="pwdInput" class="form-control"
                   placeholder="At least 8 characters" minlength="8" required>
            <button type="button" class="btn btn-outline-secondary" onclick="togglePwd('pwdInput','eyeIcon1')"
                    style="border-color:rgba(255,255,255,.15);color:rgba(255,255,255,.6)">
              <i class="bi bi-eye" id="eyeIcon1"></i>
            </button>
          </div>
        </div>

        <div class="col-md-6">
          <label class="form-label">Confirm Password *</label>
          <div class="input-group">
            <span class="input-group-text"><i class="bi bi-lock-fill"></i></span>
            <input type="password" name="confirm_password" id="confirmInput" class="form-control"
                   placeholder="Re-enter password" minlength="8" required>
            <button type="button" class="btn btn-outline-secondary" onclick="togglePwd('confirmInput','eyeIcon2')"
                    style="border-color:rgba(255,255,255,.15);color:rgba(255,255,255,.6)">
              <i class="bi bi-eye" id="eyeIcon2"></i>
            </button>
          </div>
        </div>

        <div class="col-md-6">
          <label class="form-label">Branch *</label>
          <select name="branch_id" class="form-select" required>
            <option value="">Select branch…</option>
            <?php foreach ($branches as $b): ?>
              <option value="<?= $b['id'] ?>"
                <?= ((int)($_POST['branch_id'] ?? 0) === (int)$b['id']) ? 'selected' : '' ?>>
                <?= htmlspecialchars($b['code'] . ' — ' . $b['name'], ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="col-md-6">
          <label class="form-label">Role *</label>
          <select name="role_id" class="form-select" required>
            <option value="">Select role…</option>
            <?php foreach ($roles as $r): ?>
              <option value="<?= $r['id'] ?>"
                <?= ((int)($_POST['role_id'] ?? 0) === (int)$r['id']) ? 'selected' : '' ?>>
                <?= htmlspecialchars($r['name'], ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="col-12">
          <label class="form-label">Email</label>
          <div class="input-group">
            <span class="input-group-text"><i class="bi bi-envelope"></i></span>
            <input type="email" name="email" class="form-control" placeholder="your@email.com"
                   value="<?= htmlspecialchars($_POST['email'] ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>">
          </div>
        </div>
      </div>

      <div class="mt-3 mb-3 pending-notice small">
        <i class="bi bi-info-circle me-1 text-warning"></i>
        Your account will be <strong>pending admin approval</strong> after registration. An administrator must activate your account before you can log in.
      </div>

      <button type="submit" class="btn btn-register btn-primary w-100">
        <i class="bi bi-person-plus me-2"></i>Register
      </button>
    </form>

    <hr class="divider my-3">
    <p class="text-center text-white-50 small mb-0">
      Already have an account?
      <a href="<?= BASE_URL ?>/index.php" class="login-link">Sign In</a>
    </p>

  <?php endif; ?>
</div>

<script>
function togglePwd(inputId, iconId) {
  const inp = document.getElementById(inputId);
  const ico = document.getElementById(iconId);
  if (inp.type === 'password') { inp.type = 'text'; ico.className = 'bi bi-eye-slash'; }
  else                          { inp.type = 'password'; ico.className = 'bi bi-eye'; }
}
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
