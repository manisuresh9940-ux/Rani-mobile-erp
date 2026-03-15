<?php
/**
 * Rani Mobiles ERP — Closing & Collection (Owner 2nd Dashboard)
 */
require_once __DIR__ . '/../../config/auth.php';
require_auth();
$user  = current_user();
$pdo   = db();
$today = date('Y-m-d');

// Build collection status per branch
$branches = $pdo->query("SELECT * FROM branches WHERE is_active=1 ORDER BY id")->fetchAll();
$rows = [];
foreach ($branches as $b) {
    $bid = $b['id'];

    // Day close record
    $q = $pdo->prepare("SELECT * FROM day_close WHERE branch_id=? AND business_date=?");
    $q->execute([$bid, $today]);
    $dc = $q->fetch();
    $sales_total  = $dc ? (float)$dc['sales_total']  : 0;
    $counted_cash = $dc ? (float)$dc['counted_cash'] : 0;

    // Confirmed handovers
    $q2 = $pdo->prepare(
        "SELECT COALESCE(SUM(amount),0) FROM cash_handovers WHERE branch_id=? AND business_date=? AND status='CONFIRMED'"
    );
    $q2->execute([$bid, $today]);
    $confirmed = (float)$q2->fetchColumn();
    $pending   = $counted_cash - $confirmed;

    // Status determination
    if ($sales_total == 0 && $counted_cash == 0) {
        $status = 'NO_SALES'; $badge = 'secondary'; $label = 'NO SALES';
    } elseif ($confirmed == 0) {
        $status = 'NOT_GIVEN'; $badge = 'danger'; $label = 'PAYMENT NOT GIVEN TODAY';
    } elseif ($pending > 0) {
        $status = 'PARTIAL'; $badge = 'warning'; $label = 'PARTIAL';
    } else {
        $status = 'OK'; $badge = 'success'; $label = 'OK';
    }

    // Collection target
    $q3 = $pdo->prepare("SELECT daily_target FROM branch_collection_targets WHERE branch_id=?");
    $q3->execute([$bid]);
    $target = (float)($q3->fetchColumn() ?: 0);

    $rows[] = [
        'branch'       => $b,
        'dc'           => $dc,
        'sales_total'  => $sales_total,
        'counted_cash' => $counted_cash,
        'confirmed'    => $confirmed,
        'pending'      => $pending,
        'target'       => $target,
        'status'       => $status,
        'badge'        => $badge,
        'label'        => $label,
        'sort'         => ['NOT_GIVEN' => 0, 'PARTIAL' => 1, 'OK' => 2, 'NO_SALES' => 3][$status],
    ];
}

// Sort: Red → Orange → Green → Grey
usort($rows, fn($a, $b) => $a['sort'] <=> $b['sort']);

$pageTitle = 'Closing & Collection';
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';
?>
<div class="content-wrapper">
<div class="container-fluid py-3">
  <h4 class="mb-3"><i class="bi bi-collection me-2"></i>Closing &amp; Collection — <?= $today ?></h4>

  <div class="row g-3">
  <?php foreach ($rows as $r): ?>
    <div class="col-md-6 col-lg-4">
      <div class="card border-<?= $r['badge'] ?>">
        <div class="card-header bg-<?= $r['badge'] ?> <?= $r['badge'] === 'warning' ? 'text-dark' : 'text-white' ?> d-flex justify-content-between">
          <strong><?= clean($r['branch']['name']) ?></strong>
          <span class="badge bg-light text-dark"><?= match($r['status']) {
            'NO_SALES'  => __('status_no_sales'),
            'NOT_GIVEN' => __('status_not_given'),
            'PARTIAL'   => __('status_partial'),
            default     => __('status_ok'),
          } ?></span>
        </div>
        <div class="card-body small">
          <div class="row text-center">
            <div class="col">
              <div class="fw-bold"><?= money($r['sales_total']) ?></div>
              <div class="text-muted"><?= __('sales_total_label') ?></div>
            </div>
            <div class="col">
              <div class="fw-bold"><?= money($r['counted_cash']) ?></div>
              <div class="text-muted"><?= __('counted_cash_label') ?></div>
            </div>
            <div class="col">
              <div class="fw-bold text-success"><?= money($r['confirmed']) ?></div>
              <div class="text-muted"><?= __('received_label') ?></div>
            </div>
            <div class="col">
              <div class="fw-bold <?= $r['pending'] > 0 ? 'text-danger' : '' ?>"><?= money($r['pending']) ?></div>
              <div class="text-muted"><?= __('pending_label') ?></div>
            </div>
          </div>
          <?php if ($r['target'] > 0): ?>
          <div class="mt-2">
            <small class="text-muted"><?= __('target') ?>: <?= money($r['target']) ?></small>
            <div class="progress mt-1" style="height:6px">
              <?php $pct = $r['target'] > 0 ? min(100, (int)round($r['confirmed'] / $r['target'] * 100)) : 0; ?>
              <div class="progress-bar bg-<?= $pct >= 100 ? 'success' : 'warning' ?>" style="width:<?= $pct ?>%"></div>
            </div>
            <small class="<?= $r['confirmed'] >= $r['target'] ? 'text-success' : 'text-warning' ?>"><?= $pct ?><?= __('of_target') ?></small>
          </div>
          <?php endif; ?>
        </div>
        <?php if ($r['status'] !== 'NO_SALES'): ?>
        <div class="card-footer text-end">
          <a href="<?= BASE_URL ?>/modules/accounts/handover.php?branch=<?= $r['branch']['id'] ?>"
             class="btn btn-sm btn-outline-primary"><?= __('view_handovers') ?></a>
        </div>
        <?php endif; ?>
      </div>
    </div>
  <?php endforeach; ?>
  </div>
</div>
</div>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
