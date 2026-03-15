<?php
/**
 * Rani Mobiles ERP — Day Close
 */
require_once __DIR__ . '/../../config/auth.php';
require_auth();
$user = current_user();
$pdo  = db();
$today = date('Y-m-d');
$branch_id = $user['branch_id'];

$errors = []; $success = '';

// Reopen (SUP only)
if (isset($_POST['action']) && $_POST['action'] === 'reopen') {
    verify_csrf();
    if (!is_sup()) { $errors[] = 'Permission denied.'; }
    else {
        $pdo->prepare("UPDATE day_close SET status='REOPENED', reopened_by=?, updated_at=NOW() WHERE branch_id=? AND business_date=?")
            ->execute([$user['id'], $branch_id, $today]);
        $success = 'Day reopened.';
    }
}

// Save day close
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'close') {
    verify_csrf();
    $counted_cash = (float)($_POST['counted_cash']  ?? 0);
    $upi_total    = (float)($_POST['upi_total']     ?? 0);
    $card_total   = (float)($_POST['card_total']    ?? 0);
    $credit_total = (float)($_POST['credit_total']  ?? 0);
    $notes        = clean($_POST['notes'] ?? '');

    // Compute sales total for today
    $q = $pdo->prepare("SELECT COALESCE(SUM(total),0) FROM sales WHERE branch_id=? AND sale_date=?");
    $q->execute([$branch_id, $today]);
    $sales_total = (float)$q->fetchColumn();

    // Variance = counted_cash - expected_cash (cash sales minus cash expenses)
    $q2 = $pdo->prepare("SELECT COALESCE(SUM(paid_cash),0) FROM sales WHERE branch_id=? AND sale_date=?");
    $q2->execute([$branch_id, $today]);
    $expected_cash = (float)$q2->fetchColumn();

    $q3 = $pdo->prepare("SELECT COALESCE(SUM(amount),0) FROM expenses WHERE branch_id=? AND expense_date=?");
    $q3->execute([$branch_id, $today]);
    $expected_cash -= (float)$q3->fetchColumn();

    $variance = $counted_cash - $expected_cash;

    $pdo->prepare(
        "INSERT INTO day_close
            (branch_id,business_date,counted_cash,upi_total,card_total,credit_total,sales_total,variance,notes,closed_by,status)
         VALUES (?,?,?,?,?,?,?,?,?,?,'CLOSED')
         ON DUPLICATE KEY UPDATE
            counted_cash=VALUES(counted_cash), upi_total=VALUES(upi_total),
            card_total=VALUES(card_total), credit_total=VALUES(credit_total),
            sales_total=VALUES(sales_total), variance=VALUES(variance),
            notes=VALUES(notes), closed_by=VALUES(closed_by),
            status='CLOSED', updated_at=NOW()"
    )->execute([$branch_id,$today,$counted_cash,$upi_total,$card_total,$credit_total,$sales_total,$variance,$notes,$user['id']]);
    $success = 'Day closed successfully.';
}

// Fetch today's close record
$q = $pdo->prepare("SELECT * FROM day_close WHERE branch_id=? AND business_date=?");
$q->execute([$branch_id, $today]);
$closeRecord = $q->fetch();

// Fetch today's handovers
$q = $pdo->prepare(
    "SELECT ch.*, u.full_name AS handover_user, cu.full_name AS confirmed_user
     FROM cash_handovers ch
     LEFT JOIN users u  ON u.id = ch.handover_by
     LEFT JOIN users cu ON cu.id = ch.confirmed_by
     WHERE ch.branch_id=? AND ch.business_date=?
     ORDER BY ch.created_at DESC"
);
$q->execute([$branch_id, $today]);
$handovers = $q->fetchAll();

$confirmedTotal  = array_sum(array_column(array_filter($handovers, fn($h) => $h['status'] === 'CONFIRMED'), 'amount'));
$pendingHandover = ($closeRecord ? (float)$closeRecord['counted_cash'] : 0) - $confirmedTotal;

$pageTitle = 'Day Close';
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';
?>
<div class="content-wrapper">
<div class="container-fluid py-3">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0"><i class="bi bi-lock me-2"></i>Day Close — <?= clean($user['branch']) ?> (<?= $today ?>)</h4>
  </div>

  <?php foreach ($errors as $e): ?>
    <div class="alert alert-danger"><?= clean($e) ?></div>
  <?php endforeach; ?>
  <?php if ($success): ?>
    <div class="alert alert-success"><?= clean($success) ?></div>
  <?php endif; ?>

  <?php if ($closeRecord && $closeRecord['status'] === 'CLOSED'): ?>
    <div class="alert alert-info">
      <strong>Day is CLOSED.</strong>
      Counted Cash: <?= money((float)$closeRecord['counted_cash']) ?> &nbsp;|&nbsp;
      Variance: <?= money((float)$closeRecord['variance']) ?> &nbsp;|&nbsp;
      Sales Total: <?= money((float)$closeRecord['sales_total']) ?>
      <?php if (is_sup()): ?>
        <form method="post" class="d-inline ms-3">
          <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
          <input type="hidden" name="action" value="reopen">
          <button class="btn btn-sm btn-warning">Reopen</button>
        </form>
      <?php endif; ?>
    </div>
  <?php else: ?>
  <div class="card mb-3">
    <div class="card-header fw-bold">Close Today's Books</div>
    <div class="card-body">
      <form method="post">
        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
        <input type="hidden" name="action" value="close">
        <div class="row g-3">
          <div class="col-md-3">
            <label class="form-label">Counted Cash (₹)</label>
            <input type="number" step="0.01" min="0" name="counted_cash" class="form-control"
                   value="<?= $closeRecord ? $closeRecord['counted_cash'] : '' ?>" required>
          </div>
          <div class="col-md-3">
            <label class="form-label">UPI Total (₹)</label>
            <input type="number" step="0.01" min="0" name="upi_total" class="form-control"
                   value="<?= $closeRecord ? $closeRecord['upi_total'] : '' ?>">
          </div>
          <div class="col-md-3">
            <label class="form-label">Card Total (₹)</label>
            <input type="number" step="0.01" min="0" name="card_total" class="form-control"
                   value="<?= $closeRecord ? $closeRecord['card_total'] : '' ?>">
          </div>
          <div class="col-md-3">
            <label class="form-label">Credit Total (₹)</label>
            <input type="number" step="0.01" min="0" name="credit_total" class="form-control"
                   value="<?= $closeRecord ? $closeRecord['credit_total'] : '' ?>">
          </div>
          <div class="col-12">
            <label class="form-label">Notes</label>
            <textarea name="notes" class="form-control" rows="2"><?= $closeRecord ? clean($closeRecord['notes']) : '' ?></textarea>
          </div>
        </div>
        <button class="btn btn-primary mt-3">Close Day</button>
      </form>
    </div>
  </div>
  <?php endif; ?>

  <!-- Handover summary -->
  <div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
      <span class="fw-bold">Cash Handovers Today</span>
      <a href="<?= BASE_URL ?>/modules/accounts/handover.php" class="btn btn-sm btn-outline-primary">
        <i class="bi bi-plus"></i> New Handover
      </a>
    </div>
    <div class="card-body p-0">
      <table class="table table-sm mb-0">
        <thead>
          <tr><th>Time</th><th>Amount</th><th>By</th><th>Status</th><th>Confirmed By</th></tr>
        </thead>
        <tbody>
        <?php foreach ($handovers as $h): ?>
          <tr>
            <td><?= date('H:i', strtotime($h['created_at'])) ?></td>
            <td><?= money((float)$h['amount']) ?></td>
            <td><?= clean($h['handover_user']) ?></td>
            <td>
              <span class="badge <?= $h['status'] === 'CONFIRMED' ? 'bg-success' : 'bg-warning text-dark' ?>">
                <?= $h['status'] ?>
              </span>
            </td>
            <td><?= $h['confirmed_user'] ? clean($h['confirmed_user']) : '-' ?></td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$handovers): ?>
          <tr><td colspan="5" class="text-center text-muted">No handovers yet today</td></tr>
        <?php endif; ?>
        </tbody>
      </table>
    </div>
    <?php if ($closeRecord): ?>
    <div class="card-footer small">
      <strong>Counted Cash:</strong> <?= money((float)$closeRecord['counted_cash']) ?> &nbsp;|&nbsp;
      <strong>Confirmed Received:</strong> <?= money($confirmedTotal) ?> &nbsp;|&nbsp;
      <strong class="<?= $pendingHandover > 0 ? 'text-danger' : 'text-success' ?>">
        Pending: <?= money($pendingHandover) ?>
      </strong>
    </div>
    <?php endif; ?>
  </div>
</div>
</div>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
