<?php
/**
 * Rani Mobiles ERP — User Profile
 */
require_once __DIR__ . '/../../config/auth.php';
require_auth();

$pdo     = db();
$user    = current_user();
$message = $error = '';

$stmt = $pdo->prepare('SELECT * FROM users WHERE id=?');
$stmt->execute([$user['id']]);
$profile = $stmt->fetch();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_profile'])) {
    verify_csrf();

    $full_name  = clean($_POST['full_name'] ?? '');
    $phone      = clean($_POST['phone'] ?? '');
    $email      = clean($_POST['email'] ?? '');
    $old_pwd    = $_POST['old_password'] ?? '';
    $new_pwd    = $_POST['new_password'] ?? '';

    if ($new_pwd && !password_verify($old_pwd, $profile['password'])) {
        $error = 'Current password is incorrect.';
    } else {
        if ($new_pwd) {
            $pdo->prepare('UPDATE users SET full_name=?,phone=?,email=?,password=? WHERE id=?')
                ->execute([$full_name, $phone, $email, password_hash($new_pwd, PASSWORD_BCRYPT, ['cost'=>12]), $user['id']]);
        } else {
            $pdo->prepare('UPDATE users SET full_name=?,phone=?,email=? WHERE id=?')
                ->execute([$full_name, $phone, $email, $user['id']]);
        }
        $_SESSION['user_name'] = $full_name;
        $message = 'Profile updated.';
    }
}

$pageTitle = 'My Profile';
?>
<?php include __DIR__ . '/../../includes/header.php'; ?>
<script>window.BASE_URL = '<?= BASE_URL ?>';</script>

<?php if ($message): ?>
  <div class="alert alert-success"><?= clean($message) ?></div>
<?php endif; ?>
<?php if ($error): ?>
  <div class="alert alert-danger"><?= clean($error) ?></div>
<?php endif; ?>

<div class="page-header">
  <h1 class="page-title"><i class="bi bi-person-circle me-2"></i>My Profile</h1>
</div>

<div class="row g-3">
  <div class="col-md-4">
    <div class="erp-form-card text-center">
      <div class="user-avatar mx-auto mb-3" style="width:80px;height:80px;font-size:2rem">
        <?= strtoupper(substr($profile['full_name'], 0, 1)) ?>
      </div>
      <h5><?= clean($profile['full_name']) ?></h5>
      <p class="text-muted"><?= clean($user['role']) ?></p>
      <span class="badge bg-primary"><?= clean($user['branch']) ?></span>
    </div>
  </div>

  <div class="col-md-8">
    <div class="erp-form-card">
      <div class="card-heading"><i class="bi bi-pencil"></i>Edit Profile</div>
      <form method="POST">
        <input type="hidden" name="save_profile" value="1">
        <input type="hidden" name="csrf_token"   value="<?= csrf_token() ?>">

        <div class="row g-2">
          <div class="col-md-6">
            <label class="form-label">Full Name</label>
            <input type="text" name="full_name" class="form-control"
                   value="<?= clean($profile['full_name']) ?>" required>
          </div>
          <div class="col-md-6">
            <label class="form-label">Username (read-only)</label>
            <input type="text" class="form-control bg-light" value="<?= clean($profile['username']) ?>" readonly>
          </div>
          <div class="col-md-6">
            <label class="form-label">Phone</label>
            <input type="text" name="phone" class="form-control"
                   value="<?= clean($profile['phone'] ?? '') ?>">
          </div>
          <div class="col-md-6">
            <label class="form-label">Email</label>
            <input type="email" name="email" class="form-control"
                   value="<?= clean($profile['email'] ?? '') ?>">
          </div>
          <div class="col-12"><hr><p class="text-muted small">Leave blank to keep current password</p></div>
          <div class="col-md-6">
            <label class="form-label">Current Password</label>
            <input type="password" name="old_password" class="form-control">
          </div>
          <div class="col-md-6">
            <label class="form-label">New Password</label>
            <input type="password" name="new_password" class="form-control" minlength="6">
          </div>
        </div>

        <div class="mt-3">
          <button type="submit" class="btn btn-success">
            <i class="bi bi-check-circle me-1"></i>Save Changes
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
