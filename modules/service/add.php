<?php
/**
 * Rani Mobiles ERP — Service Job Card (New)
 */
require_once __DIR__ . '/../../config/auth.php';
require_auth();

$user      = current_user();
$branch_id = (int)$user['branch_id'];
$pdo       = db();

$message = $error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_job'])) {
    verify_csrf();

    $cust_name    = clean($_POST['customer_name'] ?? '');
    $cust_phone   = clean($_POST['customer_phone'] ?? '');
    $imei         = clean($_POST['imei'] ?? '');
    $brand_id     = (int)$_POST['brand_id'];
    $model        = clean($_POST['model'] ?? '');
    $complaint    = clean($_POST['complaint'] ?? '');
    $tech_id      = (int)($_POST['technician_id'] ?? 0) ?: null;
    $parts        = clean($_POST['parts_used'] ?? '');
    $repair_cost  = (float)$_POST['repair_cost'];
    $advance_paid = (float)$_POST['advance_paid'];

    if (!$cust_name || !$complaint) {
        $error = 'Customer name and complaint are required.';
    } else {
        try {
            // Upsert customer
            $cust_id = null;
            if ($cust_phone) {
                $cs = $pdo->prepare('SELECT id FROM customers WHERE phone=?');
                $cs->execute([$cust_phone]);
                $cust_id = $cs->fetchColumn();
                if (!$cust_id) {
                    $pdo->prepare('INSERT INTO customers (name,phone) VALUES (?,?)')->execute([$cust_name, $cust_phone]);
                    $cust_id = $pdo->lastInsertId();
                }
            }

            $job_no = next_serial($branch_id, 'JOB');
            $pdo->prepare(
                'INSERT INTO service_jobs
                 (job_no,branch_id,customer_id,customer_name,customer_phone,imei,brand_id,model,
                  complaint,technician_id,parts_used,repair_cost,advance_paid,created_by)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)'
            )->execute([
                $job_no, $branch_id, $cust_id ?: null, $cust_name, $cust_phone,
                $imei ?: null, $brand_id ?: null, $model,
                $complaint, $tech_id, $parts, $repair_cost, $advance_paid, $user['id']
            ]);

            log_activity('CREATE_JOB', "Job: $job_no");
            $message = $job_no;

        } catch (Throwable $e) {
            $error = 'Error: ' . $e->getMessage();
        }
    }
}

$brands = $pdo->query('SELECT id, name FROM brands ORDER BY name')->fetchAll();
$techs  = $pdo->prepare(
    'SELECT u.id, u.full_name FROM users u JOIN roles r ON r.id=u.role_id
     WHERE r.name=? AND u.branch_id=? AND u.is_active=1'
);
$techs->execute(['Technician', $branch_id]);
$techs = $techs->fetchAll();

$pageTitle = 'New Job Card';
?>
<?php include __DIR__ . '/../../includes/header.php'; ?>
<script>window.BASE_URL = '<?= BASE_URL ?>';</script>

<?php if ($message): ?>
<div class="alert alert-success">
  <i class="bi bi-check-circle me-2"></i>Job Card created — Ref: <strong><?= clean($message) ?></strong>
  <a href="<?= BASE_URL ?>/modules/service/list.php" class="ms-2 btn btn-sm btn-outline-success">View List</a>
</div>
<?php endif; ?>
<?php if ($error): ?>
  <div class="alert alert-danger"><?= clean($error) ?></div>
<?php endif; ?>

<div class="page-header">
  <h1 class="page-title"><i class="bi bi-tools me-2"></i>New Service Job Card</h1>
  <a href="<?= BASE_URL ?>/modules/service/list.php" class="btn btn-outline-secondary btn-sm">Job Cards List</a>
</div>

<form method="POST">
  <input type="hidden" name="save_job"   value="1">
  <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">

  <div class="row g-3">
    <div class="col-md-6">
      <div class="erp-form-card">
        <div class="card-heading"><i class="bi bi-person"></i>Customer Details</div>
        <div class="mb-2">
          <label class="form-label">Customer Name *</label>
          <input type="text" name="customer_name" class="form-control" required
                 placeholder="Full name" oninput="lookupCustomer(this)">
        </div>
        <div class="mb-2">
          <label class="form-label">Phone *</label>
          <input type="text" name="customer_phone" id="custPhone" class="form-control"
                 placeholder="10-digit phone" maxlength="10"
                 oninput="lookupCustomerByPhone(this.value)">
        </div>
      </div>
    </div>

    <div class="col-md-6">
      <div class="erp-form-card">
        <div class="card-heading"><i class="bi bi-phone"></i>Device Details</div>
        <div class="row g-2">
          <div class="col-md-6">
            <label class="form-label">Brand</label>
            <select name="brand_id" class="form-select">
              <option value="">Select brand…</option>
              <?php foreach ($brands as $b): ?>
                <option value="<?= $b['id'] ?>"><?= clean($b['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-6">
            <label class="form-label">Model</label>
            <input type="text" name="model" class="form-control" placeholder="Phone model">
          </div>
          <div class="col-12">
            <label class="form-label">IMEI Number</label>
            <div class="input-group">
              <input type="text" name="imei" id="imeiInput" class="form-control" placeholder="15-digit IMEI">
              <button type="button" class="btn btn-outline-secondary" onclick="openScanner()">
                <i class="bi bi-upc-scan"></i>
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div class="col-md-8">
      <div class="erp-form-card">
        <div class="card-heading"><i class="bi bi-chat-text"></i>Complaint & Repair</div>
        <div class="mb-2">
          <label class="form-label">Complaint / Issue *</label>
          <textarea name="complaint" class="form-control" rows="3" required
                    placeholder="Describe the issue…"></textarea>
        </div>
        <div class="mb-2">
          <label class="form-label">Parts Used</label>
          <textarea name="parts_used" class="form-control" rows="2"
                    placeholder="List parts used…"></textarea>
        </div>
        <div class="row g-2">
          <div class="col-md-6">
            <label class="form-label">Repair Cost (₹)</label>
            <input type="number" name="repair_cost" class="form-control" value="0" min="0" step="0.01">
          </div>
          <div class="col-md-6">
            <label class="form-label">Advance Paid (₹)</label>
            <input type="number" name="advance_paid" class="form-control" value="0" min="0" step="0.01">
          </div>
        </div>
      </div>
    </div>

    <div class="col-md-4">
      <div class="erp-form-card">
        <div class="card-heading"><i class="bi bi-person-badge"></i>Assignment</div>
        <div class="mb-3">
          <label class="form-label">Technician</label>
          <select name="technician_id" class="form-select">
            <option value="">Not assigned</option>
            <?php foreach ($techs as $t): ?>
              <option value="<?= $t['id'] ?>"><?= clean($t['full_name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
      <button type="submit" class="btn btn-success btn-lg w-100">
        <i class="bi bi-check-circle me-2"></i>Create Job Card
      </button>
    </div>
  </div>
</form>

<!-- Scanner Modal -->
<div class="modal fade" id="scannerModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header bg-dark text-white">
        <h5 class="modal-title"><i class="bi bi-upc-scan me-2"></i>Scan IMEI</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" onclick="Scanner.stop()"></button>
      </div>
      <div class="modal-body p-0">
        <video id="scannerVideo" style="width:100%;max-height:300px;background:#000" autoplay muted playsinline></video>
      </div>
    </div>
  </div>
</div>

<script src="https://unpkg.com/@zxing/library@latest/umd/index.min.js"></script>
<script>
function openScanner() {
  Scanner.open(code => {
    document.getElementById('imeiInput').value = code;
  });
}

async function lookupCustomerByPhone(phone) {
  if (phone.length !== 10) return;
  try {
    const data = await erpFetch(`${BASE_URL}/api/lookup_customer.php?phone=${encodeURIComponent(phone)}`);
    if (data.customer) {
      document.querySelector('[name=customer_name]').value = data.customer.name;
    }
  } catch(e) {}
}
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
