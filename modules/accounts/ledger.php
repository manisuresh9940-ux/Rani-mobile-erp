<?php
/**
 * Rani Mobiles ERP — Ledger (Customer & Vendor)
 */
require_once __DIR__ . '/../../config/auth.php';
require_auth();

$user      = current_user();
$branch_id = (int)$user['branch_id'];
$isAdmin   = is_admin();
$pdo       = db();

$type   = $_GET['type']   ?? 'customer';   // 'customer' | 'vendor'
$id     = (int)($_GET['id'] ?? 0);
$fromDate = $_GET['from'] ?? date('Y-m-01');
$toDate   = $_GET['to']   ?? date('Y-m-d');

$customers = $pdo->query('SELECT id, name, phone, total_purchases FROM customers ORDER BY name LIMIT 200')->fetchAll();
$vendors   = $pdo->query('SELECT id, name, balance FROM vendors ORDER BY name LIMIT 200')->fetchAll();

$ledgerRows = [];
$entityName = '';
$openBalance = 0;

if ($id) {
    if ($type === 'customer') {
        $entity = $pdo->prepare('SELECT * FROM customers WHERE id=?');
        $entity->execute([$id]);
        $entity = $entity->fetch();
        $entityName = $entity['name'] ?? '';

        // Credit sales as debit, payments received as credit
        $sales = $pdo->prepare(
            'SELECT invoice_no AS ref, sale_date AS date, total AS debit, 0 AS credit, "Sale" AS txn_type
             FROM sales WHERE customer_id=? AND sale_date BETWEEN ? AND ? ORDER BY sale_date'
        );
        $sales->execute([$id, $fromDate, $toDate]);

        $pays = $pdo->prepare(
            'SELECT ref_no AS ref, payment_date AS date, 0 AS debit, amount AS credit, "Payment" AS txn_type
             FROM payments_received WHERE customer_id=? AND payment_date BETWEEN ? AND ? ORDER BY payment_date'
        );
        $pays->execute([$id, $fromDate, $toDate]);

        $ledgerRows = array_merge($sales->fetchAll(), $pays->fetchAll());
        usort($ledgerRows, fn($a,$b) => strtotime($a['date']) <=> strtotime($b['date']));

    } else {
        $entity = $pdo->prepare('SELECT * FROM vendors WHERE id=?');
        $entity->execute([$id]);
        $entity = $entity->fetch();
        $entityName = $entity['name'] ?? '';

        // Purchase as debit, payment made as credit
        $purch = $pdo->prepare(
            'SELECT invoice_no AS ref, purchase_date AS date, total AS debit, 0 AS credit, "Purchase" AS txn_type
             FROM purchases WHERE vendor_id=? AND purchase_date BETWEEN ? AND ? ORDER BY purchase_date'
        );
        $purch->execute([$id, $fromDate, $toDate]);

        $pays = $pdo->prepare(
            'SELECT ref_no AS ref, payment_date AS date, 0 AS debit, amount AS credit, "Payment" AS txn_type
             FROM payments_made WHERE vendor_id=? AND payment_date BETWEEN ? AND ? ORDER BY payment_date'
        );
        $pays->execute([$id, $fromDate, $toDate]);

        $ledgerRows = array_merge($purch->fetchAll(), $pays->fetchAll());
        usort($ledgerRows, fn($a,$b) => strtotime($a['date']) <=> strtotime($b['date']));
    }

    // Compute running balance
    $balance = 0;
    foreach ($ledgerRows as &$row) {
        $balance += $row['debit'] - $row['credit'];
        $row['balance'] = $balance;
    }
    unset($row);
}

$pageTitle = 'Ledger';
?>
<?php include __DIR__ . '/../../includes/header.php'; ?>
<script>window.BASE_URL = '<?= BASE_URL ?>';</script>

<div class="page-header">
  <h1 class="page-title"><i class="bi bi-journal-text me-2"></i>Account Ledger</h1>
</div>

<div class="row g-3">
  <!-- Selector -->
  <div class="col-md-3">
    <div class="erp-form-card">
      <div class="card-heading">Select Account</div>
      <div class="mb-2">
        <div class="btn-group w-100">
          <a href="?type=customer&from=<?= $fromDate ?>&to=<?= $toDate ?>"
             class="btn btn-sm <?= $type==='customer'?'btn-primary':'btn-outline-secondary' ?>">Customers</a>
          <a href="?type=vendor&from=<?= $fromDate ?>&to=<?= $toDate ?>"
             class="btn btn-sm <?= $type==='vendor'?'btn-primary':'btn-outline-secondary' ?>">Vendors</a>
        </div>
      </div>

      <div style="max-height:400px;overflow-y:auto">
        <?php $list = $type === 'customer' ? $customers : $vendors; ?>
        <?php foreach ($list as $item): ?>
          <a href="?type=<?= $type ?>&id=<?= $item['id'] ?>&from=<?= $fromDate ?>&to=<?= $toDate ?>"
             class="d-block p-2 rounded text-decoration-none <?= $id==$item['id']?'bg-primary text-white':'hover-bg' ?> mb-1"
             style="font-size:.85rem">
            <div class="fw-semibold"><?= clean($item['name']) ?></div>
            <div class="small opacity-75">
              <?= $type==='customer'
                ? '₹' . number_format((float)$item['total_purchases'], 0)
                : 'Balance: ₹' . number_format((float)$item['balance'], 0) ?>
            </div>
          </a>
        <?php endforeach; ?>
      </div>
    </div>
  </div>

  <!-- Ledger -->
  <div class="col-md-9">
    <?php if ($id && $entityName): ?>
      <div class="erp-form-card mb-3">
        <form method="GET" class="row g-2 align-items-end">
          <input type="hidden" name="type" value="<?= $type ?>">
          <input type="hidden" name="id" value="<?= $id ?>">
          <div class="col-md-3"><label class="form-label">From</label>
            <input type="date" name="from" class="form-control form-control-sm" value="<?= $fromDate ?>"></div>
          <div class="col-md-3"><label class="form-label">To</label>
            <input type="date" name="to" class="form-control form-control-sm" value="<?= $toDate ?>"></div>
          <div class="col-auto">
            <button type="submit" class="btn btn-primary btn-sm">Filter</button>
            <button type="button" class="btn btn-outline-secondary btn-sm ms-1" onclick="window.print()">
              <i class="bi bi-printer"></i> Print
            </button>
          </div>
        </form>
      </div>

      <div class="erp-table">
        <div class="d-flex align-items-center justify-content-between p-3 border-bottom">
          <div>
            <strong><?= clean($entityName) ?></strong>
            <span class="badge bg-secondary ms-2"><?= ucfirst($type) ?></span>
          </div>
          <?php if (!empty($ledgerRows)): ?>
            <?php $finalBalance = end($ledgerRows)['balance'] ?? 0; ?>
            <div class="<?= $finalBalance > 0 ? 'text-danger' : 'text-success' ?> fw-bold">
              <?= $finalBalance > 0 ? 'Due: ' : 'Overpaid: ' ?> <?= money(abs($finalBalance)) ?>
            </div>
          <?php endif; ?>
        </div>
        <div class="table-responsive">
          <table class="table mb-0">
            <thead>
              <tr><th>Date</th><th>Ref</th><th>Type</th>
                  <th class="text-end">Debit</th><th class="text-end">Credit</th>
                  <th class="text-end">Balance</th></tr>
            </thead>
            <tbody>
              <?php if (empty($ledgerRows)): ?>
                <tr><td colspan="6" class="text-center text-muted py-4">No transactions found</td></tr>
              <?php else: ?>
                <?php foreach ($ledgerRows as $row): ?>
                  <tr>
                    <td><?= fmt_date($row['date']) ?></td>
                    <td><code><?= clean($row['ref'] ?: '-') ?></code></td>
                    <td><?= clean($row['txn_type']) ?></td>
                    <td class="text-end <?= $row['debit']>0?'text-danger':'' ?>">
                      <?= $row['debit']>0 ? money((float)$row['debit']) : '' ?>
                    </td>
                    <td class="text-end <?= $row['credit']>0?'text-success':'' ?>">
                      <?= $row['credit']>0 ? money((float)$row['credit']) : '' ?>
                    </td>
                    <td class="text-end fw-semibold <?= $row['balance']>0?'text-danger':'text-success' ?>">
                      <?= money(abs((float)$row['balance'])) ?> <?= $row['balance']>0?'Dr':'Cr' ?>
                    </td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    <?php else: ?>
      <div class="text-center text-muted py-5">
        <i class="bi bi-journal-text fs-1"></i>
        <p class="mt-2">Select a <?= $type ?> from the list to view ledger</p>
      </div>
    <?php endif; ?>
  </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
