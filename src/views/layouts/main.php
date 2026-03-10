<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($title ?? APP_NAME, ENT_QUOTES, 'UTF-8') ?></title>
  <style>
    /* ── Reset & base ─────────────────────────────────────────────────────── */
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

    :root {
      --primary:       #1a237e;
      --primary-dark:  #0d1757;
      --primary-light: #283593;
      --accent:        #e8eaf6;
      --sidebar-width: 240px;
      --header-height: 60px;
      --text:          #37474f;
      --text-muted:    #78909c;
      --bg:            #f0f2f8;
      --white:         #ffffff;
      --border:        #e0e3ef;
      --shadow:        0 2px 10px rgba(26,35,126,.08);
      --radius:        8px;
    }

    body {
      font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
      background: var(--bg);
      color: var(--text);
      min-height: 100vh;
      display: flex;
      flex-direction: column;
    }

    /* ── Top header ───────────────────────────────────────────────────────── */
    .header {
      position: fixed;
      top: 0; left: 0; right: 0;
      height: var(--header-height);
      background: var(--primary);
      color: #fff;
      display: flex;
      align-items: center;
      padding: 0 1.25rem;
      z-index: 100;
      box-shadow: 0 2px 8px rgba(0,0,0,.25);
    }
    .header .brand {
      display: flex;
      align-items: center;
      gap: .6rem;
      text-decoration: none;
      color: #fff;
      font-size: 1.1rem;
      font-weight: 700;
      letter-spacing: .02em;
      white-space: nowrap;
      width: var(--sidebar-width);
    }
    .header .brand .logo-icon {
      font-size: 1.5rem;
      line-height: 1;
    }
    .header .header-right {
      flex: 1;
      display: flex;
      align-items: center;
      justify-content: flex-end;
      gap: 1rem;
    }
    .header .header-right .badge {
      background: #e8eaf6;
      color: var(--primary);
      font-size: .72rem;
      font-weight: 700;
      padding: .25rem .6rem;
      border-radius: 20px;
      text-transform: uppercase;
      letter-spacing: .05em;
    }
    .header .header-right .user-info {
      display: flex;
      align-items: center;
      gap: .5rem;
      color: #c5cae9;
      font-size: .9rem;
    }
    .header .header-right .user-info .avatar {
      width: 32px;
      height: 32px;
      border-radius: 50%;
      background: var(--primary-light);
      border: 2px solid rgba(255,255,255,.3);
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: .85rem;
    }

    /* ── Sidebar ──────────────────────────────────────────────────────────── */
    .sidebar {
      position: fixed;
      top: var(--header-height);
      left: 0;
      bottom: 0;
      width: var(--sidebar-width);
      background: var(--primary-dark);
      overflow-y: auto;
      z-index: 90;
      padding: 1rem 0;
    }
    .sidebar .nav-section {
      padding: .5rem 1rem .25rem;
      font-size: .65rem;
      text-transform: uppercase;
      letter-spacing: .1em;
      color: rgba(255,255,255,.35);
      font-weight: 600;
      margin-top: .75rem;
    }
    .sidebar a {
      display: flex;
      align-items: center;
      gap: .75rem;
      padding: .65rem 1.25rem;
      color: rgba(255,255,255,.7);
      text-decoration: none;
      font-size: .9rem;
      transition: background .15s, color .15s;
      border-left: 3px solid transparent;
    }
    .sidebar a .nav-icon { font-size: 1.1rem; width: 20px; text-align: center; }
    .sidebar a:hover {
      background: rgba(255,255,255,.08);
      color: #fff;
    }
    .sidebar a.active {
      background: rgba(255,255,255,.12);
      color: #fff;
      border-left-color: #7986cb;
    }

    /* ── Page body ────────────────────────────────────────────────────────── */
    .page-body {
      margin-top: var(--header-height);
      margin-left: var(--sidebar-width);
      flex: 1;
      display: flex;
      flex-direction: column;
      min-height: calc(100vh - var(--header-height));
    }

    /* ── Page content ─────────────────────────────────────────────────────── */
    .page-content {
      flex: 1;
      padding: 1.75rem 2rem;
    }

    /* ── Page title bar ───────────────────────────────────────────────────── */
    .page-title {
      margin-bottom: 1.5rem;
    }
    .page-title h1 {
      font-size: 1.5rem;
      font-weight: 700;
      color: var(--primary);
    }
    .page-title p {
      color: var(--text-muted);
      font-size: .9rem;
      margin-top: .2rem;
    }

    /* ── Footer ───────────────────────────────────────────────────────────── */
    .footer {
      padding: 1rem 2rem;
      color: var(--text-muted);
      font-size: .8rem;
      border-top: 1px solid var(--border);
      background: var(--white);
      text-align: center;
    }

    /* ── Responsive ───────────────────────────────────────────────────────── */
    @media (max-width: 768px) {
      .sidebar { display: none; }
      .page-body { margin-left: 0; }
      .header .brand { width: auto; }
    }
  </style>
</head>
<body>

  <!-- ── Top header ────────────────────────────────────────────────────────── -->
  <header class="header">
    <a class="brand" href="/">
      <span class="logo-icon">&#128241;</span>
      <?= htmlspecialchars(APP_NAME, ENT_QUOTES, 'UTF-8') ?>
    </a>
    <div class="header-right">
      <span class="badge"><?= htmlspecialchars(APP_ENV, ENT_QUOTES, 'UTF-8') ?></span>
      <div class="user-info">
        <div class="avatar">&#128100;</div>
        <span>Admin</span>
      </div>
    </div>
  </header>

  <!-- ── Sidebar ───────────────────────────────────────────────────────────── -->
  <aside class="sidebar">
    <?php
      $currentUri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
      $currentUri = '/' . trim((string)$currentUri, '/');
      // Exact match OR URI starts with path followed by '/' (avoids /about matching /aboutus)
      $isActive   = fn(string $path): string => (
          $currentUri === $path ||
          ($path !== '/' && str_starts_with($currentUri, rtrim($path, '/') . '/'))
      ) ? 'active' : '';
    ?>
    <div class="nav-section">Main</div>
    <a href="/" class="<?= $isActive('/') ?>">
      <span class="nav-icon">&#127968;</span> Dashboard
    </a>

    <div class="nav-section">Operations</div>
    <a href="/billing" class="<?= $isActive('/billing') ?>">
      <span class="nav-icon">&#128196;</span> Billing
    </a>
    <a href="/inventory" class="<?= $isActive('/inventory') ?>">
      <span class="nav-icon">&#128230;</span> Inventory
    </a>
    <a href="/customers" class="<?= $isActive('/customers') ?>">
      <span class="nav-icon">&#128101;</span> Customers
    </a>
    <a href="/reports" class="<?= $isActive('/reports') ?>">
      <span class="nav-icon">&#128200;</span> Reports
    </a>

    <div class="nav-section">System</div>
    <a href="/about" class="<?= $isActive('/about') ?>">
      <span class="nav-icon">&#8505;&#65039;</span> About
    </a>
    <a href="/health" class="<?= $isActive('/health') ?>">
      <span class="nav-icon">&#9989;</span> Health Check
    </a>
  </aside>

  <!-- ── Page body ─────────────────────────────────────────────────────────── -->
  <div class="page-body">
    <main class="page-content">
      <?= $content ?>
    </main>

    <footer class="footer">
      &copy; <?= date('Y') ?> <?= htmlspecialchars(APP_NAME, ENT_QUOTES, 'UTF-8') ?>.
      All rights reserved.
    </footer>
  </div>

</body>
</html>
