<?php
/**
 * Rani Mobiles ERP — Sidebar Navigation
 */
$currentFile = basename($_SERVER['PHP_SELF']);
$currentDir  = basename(dirname($_SERVER['PHP_SELF']));

function nav_link(string $href, string $icon, string $label, string $currentDir, string $module): string {
    $active = ($currentDir === $module) ? ' active' : '';
    return '<a href="'.BASE_URL.$href.'" class="nav-link'.$active.'"><i class="bi bi-'.$icon.' me-2"></i>'.$label.'</a>';
}
?>
<nav class="erp-sidebar" id="erpSidebar">
  <div class="sidebar-inner">

    <!-- Dashboard -->
    <div class="nav-section">
      <a href="<?= BASE_URL ?>/dashboard.php" class="nav-link <?= $currentFile==='dashboard.php'?'active':'' ?>">
        <i class="bi bi-speedometer2 me-2"></i><?= __('nav_dashboard') ?>
      </a>
      <?php if (is_sup() || is_admin()): ?>
      <a href="<?= BASE_URL ?>/modules/accounts/collection.php" class="nav-link <?= $currentFile==='collection.php'?'active':'' ?>">
        <i class="bi bi-binoculars me-2"></i><?= __('nav_supervisor') ?>
      </a>
      <?php endif; ?>
    </div>

    <!-- Sales -->
    <div class="nav-section">
      <div class="nav-section-header" data-bs-toggle="collapse" data-bs-target="#navSales">
        <i class="bi bi-cart3 me-2"></i>🛒 <?= __('nav_sales') ?>
        <i class="bi bi-chevron-down ms-auto"></i>
      </div>
      <div class="collapse <?= in_array($currentDir,['sales'])?'show':'' ?>" id="navSales">
        <a href="<?= BASE_URL ?>/modules/sales/pos.php" class="nav-sublink <?= $currentFile==='pos.php'?'active':'' ?>">
          <i class="bi bi-bag-plus me-2"></i><?= __('nav_pos') ?>
        </a>
        <a href="<?= BASE_URL ?>/modules/sales/list.php" class="nav-sublink <?= $currentFile==='list.php'&&$currentDir==='sales'?'active':'' ?>">
          <i class="bi bi-list-ul me-2"></i><?= __('nav_sales_list') ?>
        </a>
      </div>
    </div>

    <!-- Purchase -->
    <div class="nav-section">
      <div class="nav-section-header" data-bs-toggle="collapse" data-bs-target="#navPurchase">
        <i class="bi bi-truck me-2"></i><?= __('nav_purchase') ?>
        <i class="bi bi-chevron-down ms-auto"></i>
      </div>
      <div class="collapse <?= $currentDir==='purchase'?'show':'' ?>" id="navPurchase">
        <a href="<?= BASE_URL ?>/modules/purchase/add.php" class="nav-sublink <?= $currentFile==='add.php'&&$currentDir==='purchase'?'active':'' ?>">
          <i class="bi bi-plus-circle me-2"></i><?= __('nav_new_purchase') ?>
        </a>
        <a href="<?= BASE_URL ?>/modules/purchase/list.php" class="nav-sublink <?= $currentFile==='list.php'&&$currentDir==='purchase'?'active':'' ?>">
          <i class="bi bi-list-ul me-2"></i><?= __('nav_purchase_list') ?>
        </a>
        <a href="<?= BASE_URL ?>/modules/purchase/orders.php" class="nav-sublink <?= $currentFile==='orders.php'&&$currentDir==='purchase'?'active':'' ?>">
          <i class="bi bi-file-earmark-text me-2"></i><?= __('nav_purchase_orders') ?>
        </a>
      </div>
    </div>

    <!-- Stock -->
    <div class="nav-section">
      <div class="nav-section-header" data-bs-toggle="collapse" data-bs-target="#navStock">
        <i class="bi bi-boxes me-2"></i>📦 <?= __('nav_stock') ?>
        <i class="bi bi-chevron-down ms-auto"></i>
      </div>
      <div class="collapse <?= $currentDir==='stock'?'show':'' ?>" id="navStock">
        <a href="<?= BASE_URL ?>/modules/stock/products.php" class="nav-sublink <?= $currentFile==='products.php'?'active':'' ?>">
          <i class="bi bi-phone me-2"></i><?= __('nav_products') ?>
        </a>
        <a href="<?= BASE_URL ?>/modules/stock/inventory.php" class="nav-sublink <?= $currentFile==='inventory.php'?'active':'' ?>">
          <i class="bi bi-bar-chart me-2"></i><?= __('nav_inventory') ?>
        </a>
        <a href="<?= BASE_URL ?>/modules/stock/baseline.php" class="nav-sublink <?= $currentFile==='baseline.php'?'active':'' ?>">
          <i class="bi bi-clipboard-check me-2"></i><?= __('nav_expected_stock') ?>
        </a>
        <a href="<?= BASE_URL ?>/modules/stock/item_changes.php" class="nav-sublink <?= $currentFile==='item_changes.php'?'active':'' ?>">
          <i class="bi bi-arrow-repeat me-2"></i><?= __('nav_item_changes') ?>
        </a>
        <a href="<?= BASE_URL ?>/modules/stock/alerts.php" class="nav-sublink <?= $currentFile==='alerts.php'?'active':'' ?>">
          <i class="bi bi-bell-fill me-2"></i><?= __('nav_alerts') ?>
        </a>
      </div>
    </div>

    <!-- Transfer -->
    <div class="nav-section">
      <div class="nav-section-header" data-bs-toggle="collapse" data-bs-target="#navTransfer">
        <i class="bi bi-arrow-left-right me-2"></i><?= __('nav_transfer') ?>
        <i class="bi bi-chevron-down ms-auto"></i>
      </div>
      <div class="collapse <?= $currentDir==='transfer'?'show':'' ?>" id="navTransfer">
        <a href="<?= BASE_URL ?>/modules/transfer/add.php" class="nav-sublink <?= $currentFile==='add.php'&&$currentDir==='transfer'?'active':'' ?>">
          <i class="bi bi-send me-2"></i><?= __('nav_new_transfer') ?>
        </a>
        <a href="<?= BASE_URL ?>/modules/transfer/list.php" class="nav-sublink <?= $currentFile==='list.php'&&$currentDir==='transfer'?'active':'' ?>">
          <i class="bi bi-list-ul me-2"></i><?= __('nav_transfer_list') ?>
        </a>
      </div>
    </div>

    <!-- Service -->
    <div class="nav-section">
      <div class="nav-section-header" data-bs-toggle="collapse" data-bs-target="#navService">
        <i class="bi bi-tools me-2"></i><?= __('nav_service') ?>
        <i class="bi bi-chevron-down ms-auto"></i>
      </div>
      <div class="collapse <?= $currentDir==='service'?'show':'' ?>" id="navService">
        <a href="<?= BASE_URL ?>/modules/service/add.php" class="nav-sublink <?= $currentFile==='add.php'&&$currentDir==='service'?'active':'' ?>">
          <i class="bi bi-plus-circle me-2"></i><?= __('nav_new_job') ?>
        </a>
        <a href="<?= BASE_URL ?>/modules/service/list.php" class="nav-sublink <?= $currentFile==='list.php'&&$currentDir==='service'?'active':'' ?>">
          <i class="bi bi-list-ul me-2"></i><?= __('nav_job_cards') ?>
        </a>
      </div>
    </div>

    <!-- Second Hand -->
    <div class="nav-section">
      <div class="nav-section-header" data-bs-toggle="collapse" data-bs-target="#navSH">
        <i class="bi bi-recycle me-2"></i><?= __('nav_secondhand') ?>
        <i class="bi bi-chevron-down ms-auto"></i>
      </div>
      <div class="collapse <?= $currentDir==='secondhand'?'show':'' ?>" id="navSH">
        <a href="<?= BASE_URL ?>/modules/secondhand/buy.php" class="nav-sublink <?= $currentFile==='buy.php'?'active':'' ?>">
          <i class="bi bi-phone-vibrate me-2"></i><?= __('nav_buy_device') ?>
        </a>
        <a href="<?= BASE_URL ?>/modules/secondhand/sell.php" class="nav-sublink <?= $currentFile==='sell.php'?'active':'' ?>">
          <i class="bi bi-phone-flip me-2"></i><?= __('nav_sell_device') ?>
        </a>
        <a href="<?= BASE_URL ?>/modules/secondhand/list.php" class="nav-sublink <?= $currentFile==='list.php'&&$currentDir==='secondhand'?'active':'' ?>">
          <i class="bi bi-list-ul me-2"></i><?= __('nav_all_devices') ?>
        </a>
      </div>
    </div>

    <!-- Accounts -->
    <div class="nav-section">
      <div class="nav-section-header" data-bs-toggle="collapse" data-bs-target="#navAccounts">
        <i class="bi bi-cash-stack me-2"></i>💰 <?= __('nav_accounts') ?>
        <i class="bi bi-chevron-down ms-auto"></i>
      </div>
      <div class="collapse <?= $currentDir==='accounts'?'show':'' ?>" id="navAccounts">
        <a href="<?= BASE_URL ?>/modules/accounts/expenses.php" class="nav-sublink <?= $currentFile==='expenses.php'?'active':'' ?>">
          <i class="bi bi-receipt me-2"></i><?= __('nav_expenses') ?>
        </a>
        <a href="<?= BASE_URL ?>/modules/accounts/payments.php" class="nav-sublink <?= $currentFile==='payments.php'?'active':'' ?>">
          <i class="bi bi-credit-card me-2"></i><?= __('nav_payments') ?>
        </a>
        <a href="<?= BASE_URL ?>/modules/accounts/ledger.php" class="nav-sublink <?= $currentFile==='ledger.php'?'active':'' ?>">
          <i class="bi bi-journal-text me-2"></i><?= __('nav_ledger') ?>
        </a>
        <a href="<?= BASE_URL ?>/modules/accounts/closing.php" class="nav-sublink <?= $currentFile==='closing.php'?'active':'' ?>">
          <i class="bi bi-lock me-2"></i><?= __('nav_day_closing') ?>
        </a>
        <a href="<?= BASE_URL ?>/modules/accounts/day_close.php" class="nav-sublink <?= $currentFile==='day_close.php'?'active':'' ?>">
          <i class="bi bi-lock-fill me-2"></i><?= __('nav_day_close_new') ?>
        </a>
        <a href="<?= BASE_URL ?>/modules/accounts/handover.php" class="nav-sublink <?= $currentFile==='handover.php'?'active':'' ?>">
          <i class="bi bi-cash me-2"></i><?= __('nav_handover') ?>
        </a>
        <a href="<?= BASE_URL ?>/modules/accounts/collection.php" class="nav-sublink <?= $currentFile==='collection.php'?'active':'' ?>">
          <i class="bi bi-collection me-2"></i><?= __('nav_collection') ?>
        </a>
      </div>
    </div>

    <!-- Reports -->
    <div class="nav-section">
      <div class="nav-section-header" data-bs-toggle="collapse" data-bs-target="#navReports">
        <i class="bi bi-bar-chart-line me-2"></i>📊 <?= __('nav_reports') ?>
        <i class="bi bi-chevron-down ms-auto"></i>
      </div>
      <div class="collapse <?= $currentDir==='reports'?'show':'' ?>" id="navReports">
        <a href="<?= BASE_URL ?>/modules/reports/sales.php" class="nav-sublink <?= $currentFile==='sales.php'&&$currentDir==='reports'?'active':'' ?>">
          <i class="bi bi-graph-up me-2"></i><?= __('nav_sales_report') ?>
        </a>
        <a href="<?= BASE_URL ?>/modules/reports/purchase.php" class="nav-sublink <?= $currentFile==='purchase.php'&&$currentDir==='reports'?'active':'' ?>">
          <i class="bi bi-graph-down me-2"></i><?= __('nav_purchase_report') ?>
        </a>
        <a href="<?= BASE_URL ?>/modules/reports/stock.php" class="nav-sublink <?= $currentFile==='stock.php'&&$currentDir==='reports'?'active':'' ?>">
          <i class="bi bi-boxes me-2"></i><?= __('nav_stock_report') ?>
        </a>
        <a href="<?= BASE_URL ?>/modules/reports/branch.php" class="nav-sublink <?= $currentFile==='branch.php'?'active':'' ?>">
          <i class="bi bi-shop me-2"></i><?= __('nav_branch_report') ?>
        </a>
        <a href="<?= BASE_URL ?>/modules/reports/profit.php" class="nav-sublink <?= $currentFile==='profit.php'?'active':'' ?>">
          <i class="bi bi-currency-rupee me-2"></i><?= __('nav_profit_report') ?>
        </a>
      </div>
    </div>

    <!-- Settings -->
    <div class="nav-section">
      <div class="nav-section-header" data-bs-toggle="collapse" data-bs-target="#navSettings">
        <i class="bi bi-gear me-2"></i>⚙ <?= __('nav_settings') ?>
        <i class="bi bi-chevron-down ms-auto"></i>
      </div>
      <div class="collapse <?= $currentDir==='settings'?'show':'' ?>" id="navSettings">
        <a href="<?= BASE_URL ?>/modules/settings/branches.php" class="nav-sublink <?= $currentFile==='branches.php'?'active':'' ?>">
          <i class="bi bi-shop me-2"></i><?= __('nav_branches') ?>
        </a>
        <a href="<?= BASE_URL ?>/modules/settings/users.php" class="nav-sublink <?= $currentFile==='users.php'?'active':'' ?>">
          <i class="bi bi-people me-2"></i><?= __('nav_users') ?>
        </a>
        <a href="<?= BASE_URL ?>/modules/settings/general.php" class="nav-sublink <?= $currentFile==='general.php'?'active':'' ?>">
          <i class="bi bi-sliders me-2"></i><?= __('nav_general') ?>
        </a>
        <a href="<?= BASE_URL ?>/modules/settings/backup.php" class="nav-sublink <?= $currentFile==='backup.php'?'active':'' ?>">
          <i class="bi bi-cloud-upload me-2"></i><?= __('nav_backup') ?>
        </a>
        <a href="<?= BASE_URL ?>/modules/settings/activity.php" class="nav-sublink <?= $currentFile==='activity.php'?'active':'' ?>">
          <i class="bi bi-activity me-2"></i><?= __('nav_activity') ?>
        </a>
      </div>
    </div>

  </div><!-- /sidebar-inner -->
</nav>

<!-- Mobile overlay -->
<div class="sidebar-overlay" id="sidebarOverlay"></div>
