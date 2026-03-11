<?php
/**
 * Rani Mobiles ERP — General Settings
 */
require_once __DIR__ . '/../../config/auth.php';
require_auth();
require_admin();

$pdo     = db();
$message = $error = '';

// Load existing settings
$settings = [];
foreach ($pdo->query('SELECT key_name, key_value FROM system_settings') as $r) {
    $settings[$r['key_name']] = $r['key_value'];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_settings'])) {
    verify_csrf();

    $updateFields = [
        'business_name', 'address', 'phone', 'gstin', 'email',
        'currency_symbol', 'login_start_time', 'login_end_time'
    ];

    $stmt = $pdo->prepare(
        'INSERT INTO system_settings (key_name, key_value) VALUES (?,?)
         ON DUPLICATE KEY UPDATE key_value=?'
    );

    foreach ($updateFields as $field) {
        $val = clean($_POST[$field] ?? '');
        $stmt->execute([$field, $val, $val]);
    }

    log_activity('UPDATE_SETTINGS', 'General settings updated');
    $message = 'Settings saved.';

    // Reload
    foreach ($pdo->query('SELECT key_name, key_value FROM system_settings') as $r) {
        $settings[$r['key_name']] = $r['key_value'];
    }
}

$pageTitle = 'General Settings';
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
  <h1 class="page-title"><i class="bi bi-sliders me-2"></i>General Settings</h1>
</div>

<form method="POST">
  <input type="hidden" name="save_settings" value="1">
  <input type="hidden" name="csrf_token"    value="<?= csrf_token() ?>">

  <div class="row g-3">
    <div class="col-md-6">
      <div class="erp-form-card">
        <div class="card-heading"><i class="bi bi-shop"></i>Business Information</div>

        <div class="mb-3">
          <label class="form-label">Business Name</label>
          <input type="text" name="business_name" class="form-control"
                 value="<?= clean($settings['business_name'] ?? '') ?>">
        </div>
        <div class="mb-3">
          <label class="form-label">Address</label>
          <textarea name="address" class="form-control" rows="2"><?= clean($settings['address'] ?? '') ?></textarea>
        </div>
        <div class="row g-2">
          <div class="col-md-6">
            <label class="form-label">Phone</label>
            <input type="text" name="phone" class="form-control"
                   value="<?= clean($settings['phone'] ?? '') ?>">
          </div>
          <div class="col-md-6">
            <label class="form-label">Email</label>
            <input type="email" name="email" class="form-control"
                   value="<?= clean($settings['email'] ?? '') ?>">
          </div>
        </div>
        <div class="mt-2">
          <label class="form-label">GSTIN</label>
          <input type="text" name="gstin" class="form-control"
                 value="<?= clean($settings['gstin'] ?? '') ?>">
        </div>
      </div>
    </div>

    <div class="col-md-6">
      <div class="erp-form-card">
        <div class="card-heading"><i class="bi bi-clock"></i>Operational Settings</div>

        <div class="mb-3">
          <label class="form-label">Currency Symbol</label>
          <input type="text" name="currency_symbol" class="form-control"
                 value="<?= clean($settings['currency_symbol'] ?? '₹') ?>">
        </div>
        <div class="row g-2">
          <div class="col-md-6">
            <label class="form-label">Staff Login Start Time</label>
            <input type="time" name="login_start_time" class="form-control"
                   value="<?= clean($settings['login_start_time'] ?? '09:00') ?>">
          </div>
          <div class="col-md-6">
            <label class="form-label">Staff Login End Time</label>
            <input type="time" name="login_end_time" class="form-control"
                   value="<?= clean($settings['login_end_time'] ?? '21:00') ?>">
          </div>
        </div>
      </div>
    </div>
  </div>

  <button type="submit" class="btn btn-success">
    <i class="bi bi-check-circle me-1"></i>Save Settings
  </button>
</form>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
