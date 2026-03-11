<?php
/**
 * Rani Mobiles ERP — GST / Non-GST Invoice Print
 */
require_once __DIR__ . '/../../config/auth.php';
require_auth();

$pdo = db();

// Resolve by invoice_no or id
if (isset($_GET['no'])) {
    $stmt = $pdo->prepare('SELECT * FROM sales WHERE invoice_no=?');
    $stmt->execute([trim($_GET['no'])]);
} else {
    $stmt = $pdo->prepare('SELECT * FROM sales WHERE id=?');
    $stmt->execute([(int)$_GET['id']]);
}
$sale = $stmt->fetch();
if (!$sale) { echo '<p class="p-4 text-danger">Invoice not found.</p>'; exit; }

// Items
$items = $pdo->prepare(
    'SELECT si.*, p.name, p.model, p.hsn_code, br.name AS brand
     FROM sale_items si JOIN products p ON p.id=si.product_id JOIN brands br ON br.id=p.brand_id
     WHERE si.sale_id=?'
);
$items->execute([$sale['id']]);
$items = $items->fetchAll();

// Branch info
$branch = $pdo->prepare('SELECT * FROM branches WHERE id=?');
$branch->execute([$sale['branch_id']]);
$branch = $branch->fetch();

// Settings
$settings = [];
foreach ($pdo->query("SELECT key_name, key_value FROM system_settings") as $row) {
    $settings[$row['key_name']] = $row['key_value'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Invoice <?= clean($sale['invoice_no']) ?></title>
<style>
  * { margin:0; padding:0; box-sizing:border-box; }
  body { font-family: 'Segoe UI', sans-serif; font-size: 12px; color: #222; padding: 20px; }
  .invoice-header { text-align:center; border-bottom: 2px solid #0f3460; padding-bottom:12px; margin-bottom:12px; }
  .invoice-header h2 { color:#0f3460; font-size:18px; }
  .invoice-header p  { font-size:11px; color:#555; }
  .invoice-meta { display:flex; justify-content:space-between; margin-bottom:12px; }
  .invoice-meta div { font-size:11px; }
  table { width:100%; border-collapse:collapse; margin-bottom:10px; }
  th { background:#0f3460; color:#fff; padding:6px 8px; font-size:11px; }
  td { padding:5px 8px; border-bottom:1px solid #eee; font-size:11px; }
  .text-right { text-align:right; }
  .totals-table td { border:none; padding:4px 8px; }
  .grand-total { font-size:14px; font-weight:700; color:#0f3460; }
  .footer-note { text-align:center; margin-top:20px; font-size:10px; color:#777; border-top:1px dashed #ccc; padding-top:8px; }
  @media print { .no-print { display:none !important; } }
</style>
</head>
<body>

<div class="no-print" style="margin-bottom:15px">
  <button onclick="window.print()" style="padding:8px 20px;background:#0f3460;color:#fff;border:none;border-radius:6px;cursor:pointer">
    🖨 Print Invoice
  </button>
  <button onclick="window.close()" style="padding:8px 20px;background:#ccc;border:none;border-radius:6px;cursor:pointer;margin-left:8px">
    ✖ Close
  </button>
</div>

<div class="invoice-header">
  <h2><?= clean($settings['business_name'] ?? 'Rani Mobiles Sales & Service') ?></h2>
  <p><?= clean($branch['address'] ?? $settings['address'] ?? '') ?> | Ph: <?= clean($branch['phone'] ?? $settings['phone'] ?? '') ?></p>
  <?php if ($sale['is_gst_invoice']): ?>
    <p><strong>GSTIN:</strong> <?= clean($branch['gstin'] ?? $settings['gstin'] ?? '') ?></p>
  <?php endif; ?>
  <h3 style="margin-top:8px;font-size:14px;color:#e94560">
    <?= $sale['is_gst_invoice'] ? 'TAX INVOICE' : 'SALE INVOICE' ?>
  </h3>
</div>

<div class="invoice-meta">
  <div>
    <strong>Invoice No:</strong> <?= clean($sale['invoice_no']) ?><br>
    <strong>Date:</strong> <?= fmt_date($sale['sale_date']) ?><br>
    <strong>Branch:</strong> <?= clean($branch['code'] ?? '') ?>
  </div>
  <div style="text-align:right">
    <strong>Customer:</strong> <?= clean($sale['customer_name'] ?: 'Walk-in Customer') ?><br>
    <?php if ($sale['customer_phone']): ?>
      <strong>Phone:</strong> <?= clean($sale['customer_phone']) ?>
    <?php endif; ?>
  </div>
</div>

<table>
  <thead>
    <tr>
      <th>#</th>
      <th>Product</th>
      <?php if ($sale['is_gst_invoice']): ?><th>HSN</th><?php endif; ?>
      <th>IMEI</th>
      <th class="text-right">Price</th>
      <th class="text-right">Qty</th>
      <th class="text-right">Discount</th>
      <?php if ($sale['is_gst_invoice']): ?><th class="text-right">GST</th><?php endif; ?>
      <th class="text-right">Total</th>
    </tr>
  </thead>
  <tbody>
    <?php foreach ($items as $i => $item): ?>
      <tr>
        <td><?= $i+1 ?></td>
        <td><?= clean($item['brand']) ?> <?= clean($item['name']) ?> <?= clean($item['model'] ?? '') ?></td>
        <?php if ($sale['is_gst_invoice']): ?><td><?= clean($item['hsn_code'] ?? '') ?></td><?php endif; ?>
        <td><?= clean($item['imei'] ?? '-') ?></td>
        <td class="text-right"><?= money((float)$item['unit_price']) ?></td>
        <td class="text-right"><?= $item['qty'] ?></td>
        <td class="text-right"><?= money((float)$item['discount']) ?></td>
        <?php if ($sale['is_gst_invoice']): ?>
          <td class="text-right"><?= money((float)$item['gst_amount']) ?> (<?= $item['gst_percent'] ?>%)</td>
        <?php endif; ?>
        <td class="text-right"><?= money((float)$item['total']) ?></td>
      </tr>
    <?php endforeach; ?>
  </tbody>
</table>

<table class="totals-table" style="width:50%;margin-left:auto">
  <tr><td>Subtotal</td><td class="text-right"><?= money((float)$sale['subtotal']) ?></td></tr>
  <?php if ($sale['is_gst_invoice'] && $sale['gst_amount'] > 0): ?>
    <tr><td>CGST (<?= number_format((float)$sale['gst_amount']/2, 2) ?>)</td>
        <td class="text-right"><?= money((float)$sale['gst_amount']/2) ?></td></tr>
    <tr><td>SGST (<?= number_format((float)$sale['gst_amount']/2, 2) ?>)</td>
        <td class="text-right"><?= money((float)$sale['gst_amount']/2) ?></td></tr>
  <?php endif; ?>
  <?php if ($sale['discount'] > 0): ?>
    <tr><td>Discount</td><td class="text-right">-<?= money((float)$sale['discount']) ?></td></tr>
  <?php endif; ?>
  <tr><td colspan="2"><hr></td></tr>
  <tr class="grand-total"><td>GRAND TOTAL</td><td class="text-right"><?= money((float)$sale['total']) ?></td></tr>
</table>

<div style="margin-top:12px; font-size:11px">
  <strong>Payment:</strong>
  <?php
    $parts = [];
    if ($sale['paid_cash']   > 0) $parts[] = 'Cash: ' . money((float)$sale['paid_cash']);
    if ($sale['paid_upi']    > 0) $parts[] = 'UPI: '  . money((float)$sale['paid_upi']);
    if ($sale['paid_card']   > 0) $parts[] = 'Card: ' . money((float)$sale['paid_card']);
    if ($sale['paid_credit'] > 0) $parts[] = 'Credit: ' . money((float)$sale['paid_credit']);
    echo implode(' | ', $parts);
  ?>
</div>

<div class="footer-note">
  Thank you for shopping at <?= clean($settings['business_name'] ?? 'Rani Mobiles') ?>!<br>
  No exchange / return after 7 days. Warranty as per manufacturer terms.
</div>

</body>
</html>
