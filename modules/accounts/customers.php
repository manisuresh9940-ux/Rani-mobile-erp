<?php
/**
 * Rani Mobiles ERP — Customer Profiles & Purchase History
 */
require_once __DIR__ . '/../../config/auth.php';
require_auth();

$pdo    = db();
$user   = current_user();
$search = trim($_GET['s'] ?? '');
$custId = (int)($_GET['id'] ?? 0);

// ── CSV Export ─────────────────────────────────────────────────
if (isset($_GET['export']) && $_GET['export'] === 'csv' && $custId) {
    $cust = $pdo->prepare('SELECT * FROM customers WHERE id=?');
    $cust->execute([$custId]);
    $cust = $cust->fetch();
    if ($cust) {
        $hist = $pdo->prepare(
            'SELECT s.invoice_no, s.sale_date, s.total, s.paid_cash, s.paid_upi,
                    s.paid_card, s.paid_credit, b.code AS branch
             FROM sales s JOIN branches b ON b.id=s.branch_id
             WHERE s.customer_id=? ORDER BY s.sale_date DESC'
        );
        $hist->execute([$custId]);
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="customer_' . $custId . '_history.csv"');
        $out = fopen('php://output', 'w');
        fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF));
        fputcsv($out, ['Invoice No', 'Branch', 'Date', 'Total', 'Cash', 'UPI', 'Card', 'Credit']);
        foreach ($hist as $h) {
            fputcsv($out, [$h['invoice_no'], $h['branch'], $h['sale_date'],
                           $h['total'], $h['paid_cash'], $h['paid_upi'], $h['paid_card'], $h['paid_credit']]);
        }
        fclose($out);
        exit;
    }
}

// ── Single customer detail view ────────────────────────────────
if ($custId) {
    $cust = $pdo->prepare('SELECT * FROM customers WHERE id=?');
    $cust->execute([$custId]);
    $cust = $cust->fetch();

    if (!$cust) {
        header('Location: ' . BASE_URL . '/modules/accounts/customers.php');
        exit;
    }

    // Full purchase history
    $history = $pdo->prepare(
        'SELECT s.id, s.invoice_no, s.sale_date, s.subtotal, s.gst_amount, s.total,
                s.paid_cash, s.paid_upi, s.paid_card, s.paid_credit, s.is_gst_invoice,
                b.code AS branch
         FROM sales s JOIN branches b ON b.id=s.branch_id
         WHERE s.customer_id=? ORDER BY s.sale_date DESC LIMIT 200'
    );
    $history->execute([$custId]);
    $history = $history->fetchAll();

    // Favorite brand (brand with most quantity bought)
    $favBrand = $pdo->prepare(
        'SELECT br.name, SUM(si.qty) AS qty
         FROM sale_items si
         JOIN sales s ON s.id=si.sale_id
         JOIN products p ON p.id=si.product_id
         JOIN brands br ON br.id=p.brand_id
         WHERE s.customer_id=?
         GROUP BY br.id ORDER BY qty DESC LIMIT 1'
    );
    $favBrand->execute([$custId]);
    $favBrand = $favBrand->fetch();

    // Top product
    $topProduct = $pdo->prepare(
        'SELECT p.name, br.name AS brand, SUM(si.qty) AS qty
         FROM sale_items si
         JOIN sales s ON s.id=si.sale_id
         JOIN products p ON p.id=si.product_id
         JOIN brands br ON br.id=p.brand_id
         WHERE s.customer_id=?
         GROUP BY p.id ORDER BY qty DESC LIMIT 1'
    );
    $topProduct->execute([$custId]);
    $topProduct = $topProduct->fetch();

    $pageTitle = 'Customer: ' . $cust['name'];
?>
<?php include __DIR__ . '/../../includes/header.php'; ?>
<script>window.BASE_URL = '<?= BASE_URL ?>';</script>

<div class="page-header">
  <div>
    <h1 class="page-title"><i class="bi bi-person-circle me-2"></i><?= clean($cust['name']) ?></h1>
    <p class="page-subtitle">Customer Profile &amp; Purchase History</p>
  </div>
  <div class="d-flex gap-2">
    <a href="?id=<?= $custId ?>&export=csv" class="btn btn-outline-success btn-sm">
      <i class="bi bi-file-earmark-spreadsheet me-1"></i>Export History
    </a>
    <a href="<?= BASE_URL ?>/modules/accounts/customers.php" class="btn btn-outline-secondary btn-sm">
      <i class="bi bi-arrow-left me-1"></i>All Customers
    </a>
  </div>
</div>

<!-- Profile summary cards -->
<div class="row g-3 mb-4">
  <div class="col-sm-6 col-md-3">
    <div class="kpi-card kpi-sales">
      <div class="kpi-icon"><i class="bi bi-bag-check"></i></div>
      <div class="kpi-value"><?= money((float)$cust['total_purchases']) ?></div>
      <div class="kpi-label">Total Purchased</div>
    </div>
  </div>
  <div class="col-sm-6 col-md-3">
    <div class="kpi-card kpi-profit">
      <div class="kpi-icon"><i class="bi bi-receipt"></i></div>
      <div class="kpi-value"><?= count($history) ?></div>
      <div class="kpi-label">Transactions</div>
    </div>
  </div>
  <div class="col-sm-6 col-md-3">
    <div class="kpi-card">
      <div class="kpi-icon"><i class="bi bi-award"></i></div>
      <div class="kpi-value" style="font-size:1.1rem"><?= $favBrand ? clean($favBrand['name']) : '—' ?></div>
      <div class="kpi-label">Favourite Brand</div>
    </div>
  </div>
  <div class="col-sm-6 col-md-3">
    <div class="kpi-card">
      <div class="kpi-icon"><i class="bi bi-calendar3"></i></div>
      <div class="kpi-value" style="font-size:1rem"><?= $cust['last_purchase'] ? fmt_date($cust['last_purchase']) : '—' ?></div>
      <div class="kpi-label">Last Purchase</div>
    </div>
  </div>
</div>

<!-- Contact info -->
<div class="erp-form-card mb-4">
  <div class="card-heading"><i class="bi bi-person"></i>Contact Details</div>
  <div class="row g-2">
    <div class="col-md-3"><span class="text-muted small">Phone</span><br><strong><?= clean($cust['phone'] ?? '—') ?></strong></div>
    <div class="col-md-3"><span class="text-muted small">Email</span><br><strong><?= clean($cust['email'] ?? '—') ?></strong></div>
    <div class="col-md-3"><span class="text-muted small">GSTIN</span><br><strong><?= clean($cust['gstin'] ?? '—') ?></strong></div>
    <div class="col-md-3"><span class="text-muted small">Favourite Product</span><br>
      <strong><?= $topProduct ? clean($topProduct['brand'] . ' ' . $topProduct['name']) : '—' ?></strong>
    </div>
  </div>
  <?php if ($cust['address']): ?>
    <div class="mt-2"><span class="text-muted small">Address</span><br><?= clean($cust['address']) ?></div>
  <?php endif; ?>
</div>

<!-- Purchase history table -->
<div class="erp-table">
  <div class="d-flex align-items-center justify-content-between p-3 border-bottom">
    <strong><i class="bi bi-clock-history me-2"></i>Purchase History</strong>
    <span class="text-muted small"><?= count($history) ?> transaction(s)</span>
  </div>
  <div class="table-responsive">
    <table class="table table-hover mb-0">
      <thead>
        <tr>
          <th>Invoice</th><th>Branch</th><th>Date</th>
          <th class="text-end">Subtotal</th><th class="text-end">GST</th><th class="text-end">Total</th>
          <th class="text-end">Cash</th><th class="text-end">UPI</th><th class="text-end">Credit</th>
          <th>Type</th><th></th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($history)): ?>
          <tr><td colspan="11" class="text-center text-muted py-4">No purchase history</td></tr>
        <?php else: ?>
          <?php foreach ($history as $h): ?>
            <tr>
              <td><code><?= clean($h['invoice_no']) ?></code></td>
              <td><span class="badge bg-primary"><?= clean($h['branch']) ?></span></td>
              <td><?= fmt_date($h['sale_date']) ?></td>
              <td class="text-end"><?= money((float)$h['subtotal']) ?></td>
              <td class="text-end"><?= money((float)$h['gst_amount']) ?></td>
              <td class="text-end fw-semibold"><?= money((float)$h['total']) ?></td>
              <td class="text-end"><?= money((float)$h['paid_cash']) ?></td>
              <td class="text-end"><?= money((float)$h['paid_upi']) ?></td>
              <td class="text-end <?= $h['paid_credit']>0?'text-danger':'' ?>"><?= money((float)$h['paid_credit']) ?></td>
              <td><span class="badge <?= $h['is_gst_invoice']?'bg-info':'bg-secondary' ?>"><?= $h['is_gst_invoice']?'GST':'Non-GST' ?></span></td>
              <td>
                <a href="<?= BASE_URL ?>/modules/sales/invoice.php?id=<?= (int)$h['id'] ?>" target="_blank"
                   class="btn btn-sm btn-outline-secondary" title="Print"><i class="bi bi-printer"></i></a>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
<?php
    exit;
}

// ── Customer list ──────────────────────────────────────────────
$where  = ['1=1'];
$params = [];
if ($search) {
    $where[] = '(c.name LIKE ? OR c.phone LIKE ? OR c.email LIKE ?)';
    $params[] = "%$search%"; $params[] = "%$search%"; $params[] = "%$search%";
}

$customers = $pdo->prepare(
    'SELECT c.*, COUNT(s.id) AS sale_count
     FROM customers c
     LEFT JOIN sales s ON s.customer_id=c.id
     WHERE ' . implode(' AND ', $where) . '
     GROUP BY c.id ORDER BY c.total_purchases DESC LIMIT 200'
);
$customers->execute($params);
$customers = $customers->fetchAll();

$pageTitle = 'Customers';
?>
<?php include __DIR__ . '/../../includes/header.php'; ?>
<script>window.BASE_URL = '<?= BASE_URL ?>';</script>

<div class="page-header">
  <div>
    <h1 class="page-title"><i class="bi bi-people me-2"></i>Customer Profiles</h1>
    <p class="page-subtitle"><?= count($customers) ?> customers found</p>
  </div>
</div>

<!-- Search -->
<div class="erp-form-card mb-3">
  <form method="GET" class="row g-2 align-items-end">
    <div class="col-md-4">
      <input type="text" name="s" class="form-control form-control-sm"
             placeholder="Search by name, phone, email…" value="<?= clean($search) ?>">
    </div>
    <div class="col-auto">
      <button type="submit" class="btn btn-primary btn-sm">Search</button>
      <?php if ($search): ?><a href="?" class="btn btn-outline-secondary btn-sm">Clear</a><?php endif; ?>
    </div>
  </form>
</div>

<div class="erp-table">
  <div class="table-responsive">
    <table class="table table-hover mb-0">
      <thead>
        <tr>
          <th>#</th><th>Name</th><th>Phone</th><th>Email</th>
          <th class="text-end">Total Purchased</th><th class="text-center">Transactions</th>
          <th>Last Purchase</th><th></th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($customers)): ?>
          <tr><td colspan="8" class="text-center text-muted py-4">No customers found</td></tr>
        <?php else: ?>
          <?php foreach ($customers as $i => $c): ?>
            <tr>
              <td><?= $i + 1 ?></td>
              <td class="fw-semibold"><?= clean($c['name']) ?></td>
              <td><?= clean($c['phone'] ?? '—') ?></td>
              <td><?= clean($c['email'] ?? '—') ?></td>
              <td class="text-end fw-semibold"><?= money((float)$c['total_purchases']) ?></td>
              <td class="text-center"><?= (int)$c['sale_count'] ?></td>
              <td><?= $c['last_purchase'] ? fmt_date($c['last_purchase']) : '—' ?></td>
              <td>
                <a href="?id=<?= (int)$c['id'] ?>" class="btn btn-sm btn-outline-primary">
                  <i class="bi bi-eye me-1"></i>Profile
                </a>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
