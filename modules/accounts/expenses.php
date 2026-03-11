<?php
/**
 * Rani Mobiles ERP — Expenses
 */
require_once __DIR__ . '/../../config/auth.php';
require_auth();

$user      = current_user();
$branch_id = (int)$user['branch_id'];
$pdo       = db();

$message = $error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_expense'])) {
    verify_csrf();

    $category     = clean($_POST['category'] ?? '');
    $description  = clean($_POST['description'] ?? '');
    $amount       = (float)$_POST['amount'];
    $expense_date = $_POST['expense_date'] ?? date('Y-m-d');
    $payment_mode = clean($_POST['payment_mode'] ?? 'cash');

    if ($amount <= 0) {
        $error = 'Amount must be greater than zero.';
    } else {
        $ref_no = next_serial($branch_id, 'EXP');
        $pdo->prepare(
            'INSERT INTO expenses (ref_no,branch_id,category,description,amount,expense_date,payment_mode,created_by)
             VALUES (?,?,?,?,?,?,?,?)'
        )->execute([$ref_no, $branch_id, $category, $description, $amount, $expense_date, $payment_mode, $user['id']]);
        log_activity('ADD_EXPENSE', "Ref: $ref_no, Amount: $amount");
        $message = 'Expense saved.';
    }
}

// Fetch expenses
$fromDate = $_GET['from'] ?? date('Y-m-01');
$toDate   = $_GET['to']   ?? date('Y-m-d');

$expenses = $pdo->prepare(
    'SELECT e.*, b.code AS branch_code
     FROM expenses e JOIN branches b ON b.id=e.branch_id
     WHERE e.branch_id=? AND e.expense_date BETWEEN ? AND ?
     ORDER BY e.id DESC LIMIT 200'
);
$expenses->execute([$branch_id, $fromDate, $toDate]);
$expenses = $expenses->fetchAll();

$totalExpenses = array_sum(array_column($expenses, 'amount'));

$expCategories = ['Rent', 'Electricity', 'Internet', 'Salary', 'Commission',
                  'Repair Parts', 'Travel', 'Office Supply', 'Miscellaneous'];

$pageTitle = 'Expenses';
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
  <h1 class="page-title"><i class="bi bi-receipt me-2"></i>Expenses</h1>
  <button class="btn btn-primary btn-sm" data-bs-toggle="collapse" data-bs-target="#expenseForm">
    <i class="bi bi-plus-circle me-1"></i>Add Expense
  </button>
</div>

<!-- Add Expense Form -->
<div class="collapse show" id="expenseForm">
  <div class="erp-form-card mb-3">
    <div class="card-heading"><i class="bi bi-plus"></i>Add New Expense</div>
    <form method="POST" class="row g-2 align-items-end">
      <input type="hidden" name="save_expense" value="1">
      <input type="hidden" name="csrf_token"   value="<?= csrf_token() ?>">
      <div class="col-md-3">
        <label class="form-label">Category</label>
        <select name="category" class="form-select">
          <?php foreach ($expCategories as $c): ?>
            <option value="<?= $c ?>"><?= $c ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-3">
        <label class="form-label">Description</label>
        <input type="text" name="description" class="form-control" placeholder="Optional description…">
      </div>
      <div class="col-md-2">
        <label class="form-label">Amount (₹) *</label>
        <input type="number" name="amount" class="form-control" min="0.01" step="0.01" required>
      </div>
      <div class="col-md-2">
        <label class="form-label">Date</label>
        <input type="date" name="expense_date" class="form-control" value="<?= date('Y-m-d') ?>">
      </div>
      <div class="col-md-2">
        <label class="form-label">Payment</label>
        <select name="payment_mode" class="form-select">
          <option value="cash">Cash</option>
          <option value="upi">UPI</option>
          <option value="card">Card</option>
          <option value="bank">Bank</option>
        </select>
      </div>
      <div class="col-12 col-md-auto">
        <button type="submit" class="btn btn-success">Save</button>
      </div>
    </form>
  </div>
</div>

<!-- Filter -->
<div class="erp-form-card mb-3">
  <form method="GET" class="row g-2 align-items-end">
    <div class="col-md-2"><label class="form-label">From</label>
      <input type="date" name="from" class="form-control form-control-sm" value="<?= $fromDate ?>"></div>
    <div class="col-md-2"><label class="form-label">To</label>
      <input type="date" name="to" class="form-control form-control-sm" value="<?= $toDate ?>"></div>
    <div class="col-auto">
      <button type="submit" class="btn btn-primary btn-sm">Filter</button></div>
  </form>
</div>

<!-- Total -->
<div class="alert alert-warning mb-3">
  <i class="bi bi-cash me-2"></i>Total Expenses: <strong><?= money($totalExpenses) ?></strong>
  (<?= count($expenses) ?> records)
</div>

<div class="erp-table">
  <div class="table-responsive">
    <table class="table table-hover mb-0">
      <thead>
        <tr><th>#</th><th>Ref</th><th>Category</th><th>Description</th>
            <th>Date</th><th class="text-end">Amount</th><th>Mode</th></tr>
      </thead>
      <tbody>
        <?php if (empty($expenses)): ?>
          <tr><td colspan="7" class="text-center text-muted py-4">No expenses found</td></tr>
        <?php else: ?>
          <?php foreach ($expenses as $i => $e): ?>
            <tr>
              <td><?= $i+1 ?></td>
              <td><code><?= clean($e['ref_no'] ?: '-') ?></code></td>
              <td><?= clean($e['category'] ?: '-') ?></td>
              <td><?= clean($e['description'] ?: '-') ?></td>
              <td><?= fmt_date($e['expense_date']) ?></td>
              <td class="text-end fw-semibold"><?= money((float)$e['amount']) ?></td>
              <td><span class="badge bg-secondary"><?= clean($e['payment_mode']) ?></span></td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
