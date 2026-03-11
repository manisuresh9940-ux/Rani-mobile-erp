<?php
/**
 * Rani Mobiles ERP — Customer Management
 */
require_once __DIR__ . '/../../config/auth.php';
require_auth();

$user      = current_user();
$branch_id = (int)$user['branch_id'];
$isAdmin   = is_admin();
$pdo       = db();

$search = trim($_GET['s'] ?? '');
$page   = max(1, (int)($_GET['p'] ?? 1));
$limit  = 30;
$offset = ($page - 1) * $limit;

$where  = [];
$params = [];

if ($search) {
    $where[]  = '(c.name LIKE ? OR c.phone LIKE ? OR c.email LIKE ?)';
    $like     = "%$search%";
    $params[] = $like; $params[] = $like; $params[] = $like;
}

$whereSQL = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$cntStmt = $pdo->prepare("SELECT COUNT(*) FROM customers c $whereSQL");
$cntStmt->execute($params);
$totalRows = (int)$cntStmt->fetchColumn();
$totalPages = max(1, (int)ceil($totalRows / $limit));

$stmt = $pdo->prepare(
    "SELECT c.*,
            COALESCE(COUNT(s.id),0)      AS invoice_count,
            COALESCE(SUM(s.total),0)     AS total_spent
     FROM customers c
     LEFT JOIN sales s ON s.customer_id=c.id
     $whereSQL
     GROUP BY c.id
     ORDER BY c.total_purchases DESC
     LIMIT $limit OFFSET $offset"
);
$stmt->execute($params);
$customers = $stmt->fetchAll();

// View single customer history
$viewId = (int)($_GET['view'] ?? 0);
$custDetail = null;
$custSales  = [];
if ($viewId) {
    $d = $pdo->prepare('SELECT * FROM customers WHERE id=?');
    $d->execute([$viewId]);
    $custDetail = $d->fetch();

    if ($custDetail) {
        $cs = $pdo->prepare(
            'SELECT s.invoice_no, s.sale_date, s.total, s.paid_cash, s.paid_upi,
                    s.paid_card, s.paid_credit, b.code AS branch,
                    GROUP_CONCAT(CONCAT(br.name," ",p.name) SEPARATOR ", ") AS products
             FROM sales s
             JOIN branches b ON b.id=s.branch_id
             LEFT JOIN sale_items si ON si.sale_id=s.id
             LEFT JOIN products p ON p.id=si.product_id
             LEFT JOIN brands br ON br.id=p.brand_id
             WHERE s.customer_id=?
             GROUP BY s.id
             ORDER BY s.id DESC
             LIMIT 100'
        );
        $cs->execute([$viewId]);
        $custSales = $cs->fetchAll();

        // Favorite brand
        $fb = $pdo->prepare(
            'SELECT br.name, COUNT(*) AS cnt
             FROM sale_items si
             JOIN sales s ON s.id=si.sale_id
             JOIN products p ON p.id=si.product_id
             JOIN brands br ON br.id=p.brand_id
             WHERE s.customer_id=?
             GROUP BY br.id ORDER BY cnt DESC LIMIT 1'
        );
        $fb->execute([$viewId]);
        $favBrand = $fb->fetchColumn() ?: 'N/A';
    }
}

$pageTitle = $viewId && $custDetail ? 'Customer: ' . clean($custDetail['name']) : 'Customers';
?>
<?php include __DIR__ . '/../../includes/header.php'; ?>
<script>window.BASE_URL = '<?= BASE_URL ?>';</script>

<div class="page-header">
  <div>
    <h1 class="page-title"><i class="bi bi-people me-2"></i>
      <?= $viewId && $custDetail ? 'Customer Profile' : 'Customers' ?>
    </h1>
    <?php if ($viewId && $custDetail): ?>
      <p class="page-subtitle"><?= clean($custDetail['name']) ?> &middot; <?= clean($custDetail['phone'] ?? '-') ?></p>
    <?php endif; ?>
  </div>
  <?php if ($viewId): ?>
    <a href="<?= BASE_URL ?>/modules/customers/list.php" class="btn btn-outline-secondary btn-sm">
      <i class="bi bi-arrow-left me-1"></i>Back to Customers
    </a>
  <?php endif; ?>
</div>

<?php if ($viewId && $custDetail): ?>
  <!-- ── Customer Detail View ─────────────────────────────── -->
  <div class="row g-3 mb-4">
    <div class="col-md-4">
      <div class="erp-form-card text-center">
        <div style="width:80px;height:80px;background:linear-gradient(135deg,#0f3460,#e94560);
                    border-radius:50%;display:flex;align-items:center;justify-content:center;
                    font-size:2rem;color:#fff;margin:0 auto 1rem">
          <?= strtoupper(substr($custDetail['name'],0,1)) ?>
        </div>
        <h5 class="fw-bold"><?= clean($custDetail['name']) ?></h5>
        <p class="text-muted mb-1"><i class="bi bi-phone me-1"></i><?= clean($custDetail['phone'] ?? '-') ?></p>
        <?php if ($custDetail['email']): ?>
          <p class="text-muted mb-1"><i class="bi bi-envelope me-1"></i><?= clean($custDetail['email']) ?></p>
        <?php endif; ?>
        <?php if ($custDetail['address']): ?>
          <p class="text-muted small"><i class="bi bi-geo-alt me-1"></i><?= clean($custDetail['address']) ?></p>
        <?php endif; ?>
        <hr>
        <div class="row g-2 text-start">
          <div class="col-6">
            <div class="text-muted small">Total Spent</div>
            <div class="fw-bold text-success"><?= money((float)$custDetail['total_purchases']) ?></div>
          </div>
          <div class="col-6">
            <div class="text-muted small">Last Purchase</div>
            <div class="fw-bold"><?= fmt_date($custDetail['last_purchase'] ?? '') ?></div>
          </div>
          <div class="col-6">
            <div class="text-muted small">Invoices</div>
            <div class="fw-bold"><?= count($custSales) ?></div>
          </div>
          <div class="col-6">
            <div class="text-muted small">Fav. Brand</div>
            <div class="fw-bold"><?= clean($favBrand) ?></div>
          </div>
        </div>
        <?php if ($custDetail['gstin']): ?>
          <div class="mt-2 text-muted small">GSTIN: <?= clean($custDetail['gstin']) ?></div>
        <?php endif; ?>
      </div>
    </div>

    <div class="col-md-8">
      <div class="erp-table">
        <div class="d-flex align-items-center justify-content-between p-3 border-bottom">
          <strong><i class="bi bi-receipt me-2"></i>Purchase History (<?= count($custSales) ?>)</strong>
          <a href="<?= BASE_URL ?>/api/export_report.php?type=customer_history&id=<?= $viewId ?>"
             class="btn btn-outline-success btn-sm">
            <i class="bi bi-download me-1"></i>Export CSV
          </a>
        </div>
        <div class="table-responsive">
          <table class="table table-hover mb-0">
            <thead>
              <tr>
                <th>Invoice</th>
                <th>Date</th>
                <th>Products</th>
                <th>Branch</th>
                <th class="text-end">Amount</th>
                <th>Payment</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($custSales)): ?>
                <tr><td colspan="6" class="text-center text-muted py-4">No purchases found</td></tr>
              <?php else: ?>
                <?php foreach ($custSales as $cs): ?>
                  <tr>
                    <td><code><?= clean($cs['invoice_no']) ?></code></td>
                    <td><?= fmt_date($cs['sale_date']) ?></td>
                    <td class="text-muted small" style="max-width:200px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">
                      <?= clean($cs['products'] ?? '-') ?>
                    </td>
                    <td><span class="badge bg-primary"><?= clean($cs['branch']) ?></span></td>
                    <td class="text-end fw-semibold"><?= money((float)$cs['total']) ?></td>
                    <td class="small">
                      <?php if ($cs['paid_cash'] > 0): ?>
                        <span class="badge bg-success">Cash ₹<?= number_format($cs['paid_cash']) ?></span>
                      <?php endif; ?>
                      <?php if ($cs['paid_upi'] > 0): ?>
                        <span class="badge bg-info">UPI ₹<?= number_format($cs['paid_upi']) ?></span>
                      <?php endif; ?>
                      <?php if ($cs['paid_card'] > 0): ?>
                        <span class="badge bg-secondary">Card ₹<?= number_format($cs['paid_card']) ?></span>
                      <?php endif; ?>
                      <?php if ($cs['paid_credit'] > 0): ?>
                        <span class="badge bg-danger">Credit ₹<?= number_format($cs['paid_credit']) ?></span>
                      <?php endif; ?>
                    </td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>

<?php else: ?>
  <!-- ── Customer List ─────────────────────────────────────── -->
  <div class="erp-form-card mb-3">
    <form method="GET" class="row g-2 align-items-end">
      <div class="col-md-6">
        <label class="form-label">Search Customer</label>
        <input type="text" name="s" class="form-control"
               placeholder="Name, phone, email…" value="<?= clean($search) ?>">
      </div>
      <div class="col-auto">
        <button type="submit" class="btn btn-primary">
          <i class="bi bi-search me-1"></i>Search
        </button>
        <?php if ($search): ?>
          <a href="<?= BASE_URL ?>/modules/customers/list.php" class="btn btn-outline-secondary ms-1">Clear</a>
        <?php endif; ?>
      </div>
      <div class="col-auto ms-auto">
        <a href="<?= BASE_URL ?>/api/export_report.php?type=customers" class="btn btn-outline-success">
          <i class="bi bi-download me-1"></i>Export CSV
        </a>
      </div>
    </form>
  </div>

  <div class="erp-table mb-3">
    <div class="d-flex align-items-center justify-content-between p-3 border-bottom">
      <strong><i class="bi bi-people me-2"></i>Customers (<?= $totalRows ?>)</strong>
    </div>
    <div class="table-responsive">
      <table class="table table-hover mb-0">
        <thead>
          <tr>
            <th>#</th>
            <th>Customer</th>
            <th>Phone</th>
            <th>Email</th>
            <th>Total Purchases</th>
            <th>Last Purchase</th>
            <th>Invoices</th>
            <th>Action</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($customers)): ?>
            <tr><td colspan="8" class="text-center text-muted py-4">
              <i class="bi bi-people fs-2"></i><br>No customers found
            </td></tr>
          <?php else: ?>
            <?php foreach ($customers as $i => $c): ?>
              <tr>
                <td><?= $offset + $i + 1 ?></td>
                <td>
                  <div class="fw-semibold"><?= clean($c['name']) ?></div>
                  <?php if ($c['address']): ?>
                    <small class="text-muted"><?= clean(substr($c['address'], 0, 40)) ?></small>
                  <?php endif; ?>
                </td>
                <td><?= clean($c['phone'] ?? '-') ?></td>
                <td><?= clean($c['email'] ?? '-') ?></td>
                <td class="fw-semibold text-success"><?= money((float)$c['total_purchases']) ?></td>
                <td><?= fmt_date($c['last_purchase'] ?? '') ?></td>
                <td><span class="badge bg-secondary"><?= (int)$c['invoice_count'] ?></span></td>
                <td>
                  <a href="?view=<?= $c['id'] ?>" class="btn btn-sm btn-outline-primary">
                    <i class="bi bi-eye me-1"></i>History
                  </a>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- Pagination -->
  <?php if ($totalPages > 1): ?>
    <nav>
      <ul class="pagination pagination-sm justify-content-center">
        <?php for ($pg = 1; $pg <= $totalPages; $pg++): ?>
          <li class="page-item <?= $pg==$page?'active':'' ?>">
            <a class="page-link" href="?p=<?= $pg ?><?= $search?"&s=".urlencode($search):'' ?>">
              <?= $pg ?>
            </a>
          </li>
        <?php endfor; ?>
      </ul>
    </nav>
  <?php endif; ?>

<?php endif; ?>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
