<?php
/**
 * Rani Mobiles ERP — Users / Staff Management
 */
require_once __DIR__ . '/../../config/auth.php';
require_auth();
require_admin();

$pdo     = db();
$message = $error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $action = $_POST['action'] ?? '';

    if ($action === 'save') {
        $id         = (int)($_POST['id'] ?? 0);
        $full_name  = clean($_POST['full_name'] ?? '');
        $username   = clean($_POST['username'] ?? '');
        $password   = $_POST['password'] ?? '';
        $branch_id  = (int)$_POST['branch_id'];
        $role_id    = (int)$_POST['role_id'];
        $phone      = clean($_POST['phone'] ?? '');
        $email      = clean($_POST['email'] ?? '');
        $is_active  = (int)($_POST['is_active'] ?? 1);

        if (!$full_name || !$username || !$branch_id || !$role_id) {
            $error = 'Name, username, branch and role are required.';
        } else {
            try {
                if ($id) {
                    if ($password) {
                        $pdo->prepare(
                            'UPDATE users SET full_name=?,username=?,password=?,branch_id=?,role_id=?,phone=?,email=?,is_active=? WHERE id=?'
                        )->execute([
                            $full_name, $username, password_hash($password, PASSWORD_BCRYPT, ['cost'=>12]),
                            $branch_id, $role_id, $phone, $email, $is_active, $id
                        ]);
                    } else {
                        $pdo->prepare(
                            'UPDATE users SET full_name=?,username=?,branch_id=?,role_id=?,phone=?,email=?,is_active=? WHERE id=?'
                        )->execute([$full_name, $username, $branch_id, $role_id, $phone, $email, $is_active, $id]);
                    }
                    $message = 'User updated.';
                } else {
                    if (!$password) { $error = 'Password is required for new user.'; }
                    else {
                        $pdo->prepare(
                            'INSERT INTO users (full_name,username,password,branch_id,role_id,phone,email,is_active)
                             VALUES (?,?,?,?,?,?,?,?)'
                        )->execute([
                            $full_name, $username, password_hash($password, PASSWORD_BCRYPT, ['cost'=>12]),
                            $branch_id, $role_id, $phone, $email, $is_active
                        ]);
                        $message = 'User created.';
                    }
                }
            } catch (Throwable $e) {
                $error = 'Error: ' . $e->getMessage();
            }
        }
    } elseif ($action === 'toggle') {
        $id = (int)$_POST['id'];
        $pdo->prepare('UPDATE users SET is_active = NOT is_active WHERE id=?')->execute([$id]);
        $message = 'User status toggled.';
    }
}

$editUser = null;
if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare('SELECT * FROM users WHERE id=?');
    $stmt->execute([(int)$_GET['edit']]);
    $editUser = $stmt->fetch();
}

$users    = $pdo->query(
    'SELECT u.*, b.code AS branch_code, r.name AS role_name
     FROM users u JOIN branches b ON b.id=u.branch_id JOIN roles r ON r.id=u.role_id
     ORDER BY b.code, u.full_name'
)->fetchAll();

$branches = $pdo->query('SELECT id, code, name FROM branches ORDER BY code')->fetchAll();
$roles    = $pdo->query('SELECT id, name FROM roles ORDER BY id')->fetchAll();

$pageTitle = 'Staff / Users';
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
  <h1 class="page-title"><i class="bi bi-people me-2"></i>Staff / Users</h1>
  <button class="btn btn-primary btn-sm" data-bs-toggle="collapse" data-bs-target="#userForm">
    <i class="bi bi-plus-circle me-1"></i>Add User
  </button>
</div>

<div class="collapse <?= $editUser || $error ? 'show' : '' ?>" id="userForm">
  <div class="erp-form-card mb-3">
    <div class="card-heading">
      <i class="bi bi-person-plus"></i><?= $editUser ? 'Edit User' : 'Add New User' ?>
    </div>
    <form method="POST">
      <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
      <input type="hidden" name="action" value="save">
      <?php if ($editUser): ?>
        <input type="hidden" name="id" value="<?= $editUser['id'] ?>">
      <?php endif; ?>

      <div class="row g-3">
        <div class="col-md-3">
          <label class="form-label">Full Name *</label>
          <input type="text" name="full_name" class="form-control" required
                 value="<?= clean($editUser['full_name'] ?? '') ?>">
        </div>
        <div class="col-md-3">
          <label class="form-label">Username *</label>
          <input type="text" name="username" class="form-control" required
                 value="<?= clean($editUser['username'] ?? '') ?>">
        </div>
        <div class="col-md-3">
          <label class="form-label">Password <?= $editUser ? '(leave blank to keep)' : '*' ?></label>
          <input type="password" name="password" class="form-control"
                 <?= !$editUser ? 'required' : '' ?> minlength="6">
        </div>
        <div class="col-md-3">
          <label class="form-label">Branch *</label>
          <select name="branch_id" class="form-select" required>
            <option value="">Select…</option>
            <?php foreach ($branches as $b): ?>
              <option value="<?= $b['id'] ?>" <?= ($editUser['branch_id']??0)==$b['id']?'selected':'' ?>>
                <?= clean($b['code']) ?> — <?= clean($b['name']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-3">
          <label class="form-label">Role *</label>
          <select name="role_id" class="form-select" required>
            <?php foreach ($roles as $r): ?>
              <option value="<?= $r['id'] ?>" <?= ($editUser['role_id']??0)==$r['id']?'selected':'' ?>>
                <?= clean($r['name']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-3">
          <label class="form-label">Phone</label>
          <input type="text" name="phone" class="form-control"
                 value="<?= clean($editUser['phone'] ?? '') ?>">
        </div>
        <div class="col-md-3">
          <label class="form-label">Email</label>
          <input type="email" name="email" class="form-control"
                 value="<?= clean($editUser['email'] ?? '') ?>">
        </div>
        <div class="col-md-3">
          <div class="form-check form-switch mt-4">
            <input type="checkbox" name="is_active" value="1" class="form-check-input" id="uActive"
                   <?= ($editUser['is_active']??1)?'checked':'' ?>>
            <label class="form-check-label" for="uActive">Active</label>
          </div>
        </div>
      </div>

      <div class="mt-3">
        <button type="submit" class="btn btn-success">
          <i class="bi bi-check-circle me-1"></i><?= $editUser ? 'Update User' : 'Create User' ?>
        </button>
        <?php if ($editUser): ?>
          <a href="?" class="btn btn-outline-secondary ms-2">Cancel</a>
        <?php endif; ?>
      </div>
    </form>
  </div>
</div>

<div class="erp-table">
  <div class="table-responsive">
    <table class="table table-hover mb-0">
      <thead>
        <tr><th>#</th><th>Name</th><th>Username</th><th>Branch</th><th>Role</th>
            <th>Phone</th><th>Status</th><th>Actions</th></tr>
      </thead>
      <tbody>
        <?php foreach ($users as $i => $u): ?>
          <tr>
            <td><?= $i+1 ?></td>
            <td><?= clean($u['full_name']) ?></td>
            <td><code><?= clean($u['username']) ?></code></td>
            <td><span class="badge bg-primary"><?= clean($u['branch_code']) ?></span></td>
            <td><span class="badge bg-secondary"><?= clean($u['role_name']) ?></span></td>
            <td><?= clean($u['phone'] ?? '-') ?></td>
            <td>
              <?= $u['is_active']
                ? '<span class="badge bg-success">Active</span>'
                : '<span class="badge bg-danger">Inactive</span>' ?>
            </td>
            <td>
              <a href="?edit=<?= $u['id'] ?>" class="btn btn-sm btn-outline-primary">
                <i class="bi bi-pencil"></i>
              </a>
              <?php if ($u['id'] !== (int)$user['id']): ?>
                <form method="POST" class="d-inline">
                  <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                  <input type="hidden" name="action" value="toggle">
                  <input type="hidden" name="id" value="<?= $u['id'] ?>">
                  <button type="submit" class="btn btn-sm btn-outline-<?= $u['is_active']?'warning':'success' ?>">
                    <i class="bi bi-<?= $u['is_active']?'pause':'play' ?>-circle"></i>
                  </button>
                </form>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
