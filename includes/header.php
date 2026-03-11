<?php
/**
 * Rani Mobiles ERP — HTML Header Include
 * Usage: include once at top of every page after require_auth()
 * Variable $pageTitle must be set before including this file.
 */
$pageTitle = $pageTitle ?? 'Dashboard';
$user = current_user();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= clean($pageTitle) ?> — Rani Mobiles ERP</title>
<!-- Bootstrap 5 -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
<!-- Bootstrap Icons -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.min.css">
<!-- Custom CSS -->
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
</head>
<body>
<!-- Top Navbar -->
<nav class="navbar navbar-expand-lg erp-navbar px-3 py-0" id="topNavbar">
  <!-- Mobile sidebar toggle -->
  <button class="btn btn-link text-white d-lg-none me-2" id="mobileSidebarToggle">
    <i class="bi bi-list fs-4"></i>
  </button>

  <a class="navbar-brand d-flex align-items-center gap-2" href="<?= BASE_URL ?>/dashboard.php">
    <span class="brand-icon">📱</span>
    <span class="fw-bold">Rani Mobiles ERP</span>
  </a>

  <div class="ms-auto d-flex align-items-center gap-3">
    <!-- Branch badge -->
    <span class="badge branch-badge d-none d-sm-inline-flex">
      <i class="bi bi-shop me-1"></i><?= clean($user['branch']) ?>
    </span>

    <!-- Notification bell -->
    <div class="dropdown">
      <button class="btn btn-link text-white position-relative" data-bs-toggle="dropdown">
        <i class="bi bi-bell fs-5"></i>
        <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger notif-badge" id="notifCount" style="display:none">0</span>
      </button>
      <ul class="dropdown-menu dropdown-menu-end shadow-lg" id="notifDropdown" style="min-width:300px;max-height:400px;overflow-y:auto">
        <li class="dropdown-header fw-bold py-2 px-3">Alerts</li>
        <li><div class="px-3 py-2 text-muted small" id="notifList">Loading…</div></li>
      </ul>
    </div>

    <!-- User menu -->
    <div class="dropdown">
      <button class="btn btn-link text-white d-flex align-items-center gap-2" data-bs-toggle="dropdown">
        <div class="user-avatar"><?= strtoupper(substr($user['name'], 0, 1)) ?></div>
        <span class="d-none d-md-inline"><?= clean($user['name']) ?></span>
        <i class="bi bi-chevron-down small"></i>
      </button>
      <ul class="dropdown-menu dropdown-menu-end shadow-lg">
        <li><h6 class="dropdown-header"><?= clean($user['role']) ?></h6></li>
        <li><a class="dropdown-item" href="<?= BASE_URL ?>/modules/settings/profile.php"><i class="bi bi-person me-2"></i>My Profile</a></li>
        <li><hr class="dropdown-divider"></li>
        <li><a class="dropdown-item text-danger" href="<?= BASE_URL ?>/logout.php"><i class="bi bi-box-arrow-right me-2"></i>Logout</a></li>
      </ul>
    </div>
  </div>
</nav>

<!-- Page wrapper -->
<div class="erp-wrapper">
<?php include __DIR__ . '/sidebar.php'; ?>
<main class="erp-main" id="erpMain">
<div class="container-fluid py-3">
