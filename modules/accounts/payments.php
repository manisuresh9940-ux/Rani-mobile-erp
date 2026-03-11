<?php
/**
 * Rani Mobiles ERP — Payments (Received & Made)
 */
require_once __DIR__ . '/../../config/auth.php';
require_auth();

$user      = current_user();
$branch_id = (int)$user['branch_id'];
$pdo       = db();

$tab     = $_GET['tab'] ?? 'received';
$message = $error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $payment_date = $_POST['payment_date'] ?? date('Y-m-d');
    $payment_mode = clean($_POST['payment_mode'] ?? 'cash');
    $amount       = (float)$_POST['amount'];
    $notes        = clean($_POST['notes'] ?? '');
    $reference    = clean($_POST['reference'] ?? '');

    if ($amount <= 0) {
        $error = 'Amount must be greater than zero.';
    } elseif (isset($_POST['save_received'])) {
        $cust_id   = (int)($_POST['customer_id'] ?? 0) ?: null;
        $cust_name = clean($_POST['customer_name'] ?? '');
        $ref_no    = next_serial($branch_id, 'REC');
        $pdo->prepare(
            'INSERT INTO payments_received (ref_no,branch_id,customer_id,customer_name,amount,payment_mode,reference,payment_date,notes,created_by)
             VALUES (?,?,?,?,?,?,?,?,?,?)'
        )->execute([$ref_no,$branch_id,$cust_id,$cust_name,$amount,$payment_mode,$reference,$payment_date,$notes,$user['id']]);
        log_activity('PAYMENT_RECEIVED', "Ref: $ref_no, Amount: $amount");
        $message = 'Payment received recorded.';
    } elseif (isset($_POST['save_made'])) {
        $vendor_id   = (int)($_POST['vendor_id'] ?? 0) ?: null;
        $vendor_name = clean($_POST['vendor_name'] ?? '');
        $ref_no      = next_serial($branch_id, 'PAY');
        $pdo->prepare(
            'INSERT INTO payments_made (ref_no,branch_id,vendor_id,vendor_name,amount,payment_mode,reference,payment_date,notes,created_by)
             VALUES (?,?,?,?,?,?,?,?,?,?)'
        )->execute([$ref_no,$branch_id,$vendor_id,$vendor_name,$amount,$payment_mode,$reference,$payment_date,$notes,$user['id']]);
        log_activity('PAYMENT_MADE', "Ref: $ref_no, Amount: $amount");
        $message = 'Payment made recorded.';
    }
}

$customers = $pdo->query('SELECT id, name FROM customers ORDER BY name LIMIT 200')->fetchAll();
$vendors   = $pdo->query('SELECT id, name FROM vendors ORDER BY name LIMIT 200')->fetchAll();

$fromDate = $_GET['from'] ?? date('Y-m-01');
$toDate   = $_GET['to']   ?? date('Y-m-d');

$received = $pdo->prepare(
    'SELECT * FROM payments_received WHERE branch_id=? AND payment_date BETWEEN ? AND ? ORDER BY id DESC LIMIT 100'
);
$received->execute([$branch_id, $fromDate, $toDate]);
$received = $received->fetchAll();

$made = $pdo->prepare(
    'SELECT * FROM payments_made WHERE branch_id=? AND payment_date BETWEEN ? AND ? ORDER BY id DESC LIMIT 100'
);
$made->execute([$branch_id, $fromDate, $toDate]);
$made = $made->fetchAll();

$pageTitle = 'Payments';
?>
<?php include __DIR__ . '/../../includes/header.php'; ?>
<script>window.BASE_URL = '<?= BASE_URL ?>';</script>

<div class="page-header">
  <h1 class="page-title"><i class="bi bi-credit-card me-2"></i>Payments</h1>
</div>

<?php if ($message): ?>
  <div class="alert alert-success"><?= clean($message) ?></div>
<?php endif; ?>
<?php if ($error): ?>
  <div class="alert alert-danger"><?= clean($error) ?></div>
<?php endif; ?>

<ul class="nav nav-tabs mb-3">
  <li class="nav-item">
    <a class="nav-link <?= $tab==='received'?'active':'' ?>" href="?tab=received&from=<?= $fromDate ?>&to=<?= $toDate ?>">
      <i class="bi bi-arrow-down-circle me-1 text-success"></i>Received
    </a>
  </li>
  <li class="nav-item">
    <a class="nav-link <?= $tab==='made'?'active':'' ?>" href="?tab=made&from=<?= $fromDate ?>&to=<?= $toDate ?>">
      <i class="bi bi-arrow-up-circle me-1 text-danger"></i>Made
    </a>
  </li>
</ul>

<!-- Add form -->
<div class="erp-form-card mb-3">
  <?php if ($tab === 'received'): ?>
    <div class="card-heading"><i class="bi bi-arrow-down-circle text-success me-2"></i>Record Payment Received</div>
    <form method="POST" class="row g-2 align-items-end">
      <input type="hidden" name="save_received" value="1">
      <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
      <div class="col-md-3">
        <label class="form-label">Customer</label>
        <select name="customer_id" class="form-select">
          <option value="">Walk-in / Manual</option>
          <?php foreach ($customers as $c): ?>
            <option value="<?= $c['id'] ?>"><?= clean($c['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-2">
        <label class="form-label">Amount (₹)*</label>
        <input type="number" name="amount" class="form-control" required min="0.01" step="0.01">
      </div>
      <div class="col-md-2">
        <label class="form-label">Mode</label>
        <select name="payment_mode" class="form-select">
          <option>cash</option><option>upi</option><option>card</option><option>bank</option>
        </select>
      </div>
      <div class="col-md-2">
        <label class="form-label">Reference</label>
        <input type="text" name="reference" class="form-control" placeholder="UPI/TXN ref">
      </div>
      <div class="col-md-2">
        <label class="form-label">Date</label>
        <input type="date" name="payment_date" class="form-control" value="<?= date('Y-m-d') ?>">
      </div>
      <div class="col-md-1">
        <button type="submit" class="btn btn-success w-100">Save</button>
      </div>
    </form>
  <?php else: ?>
    <div class="card-heading"><i class="bi bi-arrow-up-circle text-danger me-2"></i>Record Payment Made</div>
    <form method="POST" class="row g-2 align-items-end">
      <input type="hidden" name="save_made" value="1">
      <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
      <div class="col-md-3">
        <label class="form-label">Vendor</label>
        <select name="vendor_id" class="form-select">
          <option value="">Manual</option>
          <?php foreach ($vendors as $v): ?>
            <option value="<?= $v['id'] ?>"><?= clean($v['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-2">
        <label class="form-label">Amount (₹)*</label>
        <input type="number" name="amount" class="form-control" required min="0.01" step="0.01">
      </div>
      <div class="col-md-2">
        <label class="form-label">Mode</label>
        <select name="payment_mode" class="form-select">
          <option>cash</option><option>upi</option><option>card</option><option>bank</option>
        </select>
      </div>
      <div class="col-md-2">
        <label class="form-label">Reference</label>
        <input type="text" name="reference" class="form-control" placeholder="UPI/TXN ref">
      </div>
      <div class="col-md-2">
        <label class="form-label">Date</label>
        <input type="date" name="payment_date" class="form-control" value="<?= date('Y-m-d') ?>">
      </div>
      <div class="col-md-1">
        <button type="submit" class="btn btn-danger w-100">Save</button>
      </div>
    </form>
  <?php endif; ?>
</div>

<!-- Date filter + Table -->
<div class="erp-form-card mb-3">
  <form method="GET" class="row g-2 align-items-end">
    <input type="hidden" name="tab" value="<?= $tab ?>">
    <div class="col-md-2"><input type="date" name="from" class="form-control form-control-sm" value="<?= $fromDate ?>"></div>
    <div class="col-md-2"><input type="date" name="to" class="form-control form-control-sm" value="<?= $toDate ?>"></div>
    <div class="col-auto"><button type="submit" class="btn btn-primary btn-sm">Filter</button></div>
  </form>
</div>

<div class="erp-table">
  <div class="table-responsive">
    <table class="table table-hover mb-0">
      <thead>
        <tr>
          <th>#</th><th>Ref</th><th><?= $tab==='received'?'Customer':'Vendor' ?></th>
          <th>Date</th><th class="text-end">Amount</th><th>Mode</th><th>Reference</th>
        </tr>
      </thead>
      <tbody>
        <?php $rows = $tab==='received' ? $received : $made; ?>
        <?php if (empty($rows)): ?>
          <tr><td colspan="7" class="text-center text-muted py-4">No payments found</td></tr>
        <?php else: ?>
          <?php foreach ($rows as $i => $r): ?>
            <tr>
              <td><?= $i+1 ?></td>
              <td><code><?= clean($r['ref_no'] ?? '-') ?></code></td>
              <td><?= clean($tab==='received' ? ($r['customer_name']??'-') : ($r['vendor_name']??'-')) ?></td>
              <td><?= fmt_date($r['payment_date']) ?></td>
              <td class="text-end fw-semibold <?= $tab==='received'?'text-success':'text-danger' ?>">
                <?= money((float)$r['amount']) ?>
              </td>
              <td><span class="badge bg-secondary"><?= clean($r['payment_mode']) ?></span></td>
              <td><?= clean($r['reference'] ?? '-') ?></td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
