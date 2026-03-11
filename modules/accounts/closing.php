<?php
/**
 * Rani Mobiles ERP — Day Closing
 */
require_once __DIR__ . '/../../config/auth.php';
require_auth();

$user      = current_user();
$branch_id = (int)$user['branch_id'];
$pdo       = db();

$today   = date('Y-m-d');
$message = $error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['close_day'])) {
    verify_csrf();
    $opening = (float)$_POST['opening_cash'];
    $notes   = clean($_POST['notes'] ?? '');

    // Calculate
    $cashSales = (float)$pdo->prepare('SELECT COALESCE(SUM(paid_cash),0) FROM sales WHERE branch_id=? AND sale_date=?')
        ->execute([$branch_id, $today]) ? null : 0;
    $q = $pdo->prepare('SELECT COALESCE(SUM(paid_cash),0) FROM sales WHERE branch_id=? AND sale_date=?');
    $q->execute([$branch_id, $today]);
    $cashSales = (float)$q->fetchColumn();

    $q = $pdo->prepare('SELECT COALESCE(SUM(amount),0) FROM expenses WHERE branch_id=? AND expense_date=? AND payment_mode=?');
    $q->execute([$branch_id, $today, 'cash']);
    $cashExpenses = (float)$q->fetchColumn();

    $closingExpected = $opening + $cashSales - $cashExpenses;
    $closingActual   = (float)($_POST['closing_actual'] ?? $closingExpected);
    $diff            = $closingActual - $closingExpected;

    $pdo->prepare(
        'INSERT INTO cash_register (branch_id,date,opening_cash,cash_in,cash_out,closing_cash,notes,closed_by,closed_at)
         VALUES (?,?,?,?,?,?,?,?,NOW())
         ON DUPLICATE KEY UPDATE closing_cash=?, notes=?, closed_by=?, closed_at=NOW()'
    )->execute([
        $branch_id, $today, $opening, $cashSales, $cashExpenses, $closingActual, $notes,
        $user['id'], $closingActual, $notes, $user['id']
    ]);

    log_activity('DAY_CLOSE', "Date: $today, Diff: $diff");
    $message = sprintf('Day closed. Cash balance: %s | Difference: %s', money($closingActual), money($diff));
}

// Load today's summary
$q = $pdo->prepare('SELECT COALESCE(SUM(total),0) FROM sales WHERE branch_id=? AND sale_date=?');
$q->execute([$branch_id, $today]);
$todaySales = (float)$q->fetchColumn();

$q = $pdo->prepare('SELECT COALESCE(SUM(paid_cash),0) FROM sales WHERE branch_id=? AND sale_date=?');
$q->execute([$branch_id, $today]);
$cashIn = (float)$q->fetchColumn();

$q = $pdo->prepare('SELECT COALESCE(SUM(total),0) FROM purchases WHERE branch_id=? AND purchase_date=?');
$q->execute([$branch_id, $today]);
$todayPurch = (float)$q->fetchColumn();

$q = $pdo->prepare('SELECT COALESCE(SUM(amount),0) FROM expenses WHERE branch_id=? AND expense_date=?');
$q->execute([$branch_id, $today]);
$todayExpenses = (float)$q->fetchColumn();

// Previous closing
$prev = $pdo->prepare('SELECT closing_cash FROM cash_register WHERE branch_id=? AND date=DATE_SUB(?,INTERVAL 1 DAY)');
$prev->execute([$branch_id, $today]);
$openingCash = (float)($prev->fetchColumn() ?: 0);

$pageTitle = 'Day Closing';
?>
<?php include __DIR__ . '/../../includes/header.php'; ?>
<script>window.BASE_URL = '<?= BASE_URL ?>';</script>

<div class="page-header">
  <h1 class="page-title"><i class="bi bi-lock me-2"></i>Day Closing — <?= date('d M Y') ?></h1>
</div>

<?php if ($message): ?>
  <div class="alert alert-success"><i class="bi bi-check-circle me-2"></i><?= clean($message) ?></div>
<?php endif; ?>
<?php if ($error): ?>
  <div class="alert alert-danger"><?= clean($error) ?></div>
<?php endif; ?>

<div class="row g-3">
  <div class="col-md-5">
    <div class="erp-form-card">
      <div class="card-heading"><i class="bi bi-clipboard-check"></i>Today's Summary</div>
      <table class="table table-sm">
        <tr><td>Opening Cash</td><td class="text-end fw-semibold"><?= money($openingCash) ?></td></tr>
        <tr class="text-success"><td>+ Total Sales</td><td class="text-end"><?= money($todaySales) ?></td></tr>
        <tr class="text-success"><td>&nbsp;&nbsp; Cash In</td><td class="text-end"><?= money($cashIn) ?></td></tr>
        <tr class="text-danger"><td>− Purchase</td><td class="text-end"><?= money($todayPurch) ?></td></tr>
        <tr class="text-danger"><td>− Expenses</td><td class="text-end"><?= money($todayExpenses) ?></td></tr>
        <tr class="table-primary fw-bold">
          <td>Expected Closing</td>
          <td class="text-end"><?= money($openingCash + $cashIn - $todayExpenses) ?></td>
        </tr>
      </table>
    </div>
  </div>

  <div class="col-md-7">
    <div class="erp-form-card">
      <div class="card-heading"><i class="bi bi-lock"></i>Close Day</div>
      <form method="POST">
        <input type="hidden" name="close_day"  value="1">
        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
        <div class="mb-3">
          <label class="form-label">Opening Cash (₹)</label>
          <input type="number" name="opening_cash" class="form-control" value="<?= $openingCash ?>" step="0.01">
        </div>
        <div class="mb-3">
          <label class="form-label">Actual Closing Cash (₹)</label>
          <input type="number" name="closing_actual" id="closingActual" class="form-control"
                 value="<?= $openingCash + $cashIn - $todayExpenses ?>" step="0.01"
                 oninput="calcDiff()">
        </div>
        <div id="diffAlert" class="alert mb-3" style="display:none"></div>
        <div class="mb-3">
          <label class="form-label">Notes</label>
          <textarea name="notes" class="form-control" rows="2"></textarea>
        </div>
        <button type="submit" class="btn btn-danger w-100 btn-lg">
          <i class="bi bi-lock me-2"></i>Close Day
        </button>
      </form>
    </div>
  </div>
</div>

<script>
const expected = <?= $openingCash + $cashIn - $todayExpenses ?>;
function calcDiff() {
  const actual = parseFloat(document.getElementById('closingActual').value) || 0;
  const diff   = actual - expected;
  const el     = document.getElementById('diffAlert');
  el.style.display = 'block';
  if (Math.abs(diff) < 0.01) {
    el.className = 'alert alert-success mb-3';
    el.textContent = '✅ Cash balanced perfectly!';
  } else if (diff > 0) {
    el.className = 'alert alert-warning mb-3';
    el.textContent = '⚠️ Excess cash: ₹' + diff.toFixed(2);
  } else {
    el.className = 'alert alert-danger mb-3';
    el.textContent = '❌ Cash shortage: ₹' + Math.abs(diff).toFixed(2);
  }
}
calcDiff();
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
