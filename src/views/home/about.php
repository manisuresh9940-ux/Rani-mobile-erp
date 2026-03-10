<?php
/**
 * src/views/home/about.php
 * About page view.
 */
?>

<style>
  /* ── Page title ─────────────────────────────────────────────────────────── */
  .page-title { margin-bottom: 1.75rem; }
  .page-title h1 { font-size: 1.5rem; font-weight: 700; color: #1a237e; }
  .page-title p  { color: #78909c; font-size: .9rem; margin-top: .2rem; }

  /* ── Card ───────────────────────────────────────────────────────────────── */
  .card {
    background: #fff;
    border-radius: 8px;
    box-shadow: 0 2px 10px rgba(26,35,126,.08);
    overflow: hidden;
    margin-bottom: 1.5rem;
  }
  .card-header {
    padding: 1rem 1.5rem;
    border-bottom: 1px solid #e8eaf6;
  }
  .card-header h2 { font-size: .95rem; font-weight: 700; color: #1a237e; }
  .card-body { padding: 1.5rem; }

  /* ── App info ───────────────────────────────────────────────────────────── */
  .app-banner {
    background: linear-gradient(135deg, #1a237e, #283593);
    color: #fff;
    border-radius: 8px;
    padding: 2rem 2rem;
    margin-bottom: 1.5rem;
    display: flex;
    align-items: center;
    gap: 1.25rem;
  }
  .app-banner .app-logo { font-size: 3.5rem; line-height: 1; }
  .app-banner h1 { font-size: 1.6rem; font-weight: 700; }
  .app-banner p  { opacity: .8; font-size: .95rem; margin-top: .35rem; }

  /* ── Feature list ───────────────────────────────────────────────────────── */
  .feature-list { list-style: none; }
  .feature-list li {
    display: flex;
    align-items: flex-start;
    gap: .75rem;
    padding: .6rem 0;
    border-bottom: 1px solid #f0f2f8;
    font-size: .9rem;
    color: #546e7a;
  }
  .feature-list li:last-child { border-bottom: none; }
  .feature-list .fi { font-size: 1.1rem; flex-shrink: 0; margin-top: .05rem; }

  /* ── Tech stack table ───────────────────────────────────────────────────── */
  .tech-table { width: 100%; border-collapse: collapse; font-size: .88rem; }
  .tech-table th, .tech-table td {
    padding: .55rem .9rem;
    text-align: left;
    border-bottom: 1px solid #f0f2f8;
  }
  .tech-table th { background: #f5f6ff; color: #1a237e; font-weight: 700; font-size: .78rem; text-transform: uppercase; letter-spacing: .05em; }
  .tech-table td { color: #546e7a; }
  .tech-table tr:last-child td { border-bottom: none; }
</style>

<!-- App banner -->
<div class="app-banner">
  <div class="app-logo">&#128241;</div>
  <div>
    <h1><?= htmlspecialchars($appName ?? APP_NAME, ENT_QUOTES, 'UTF-8') ?></h1>
    <p>Mobile Billing &amp; Enterprise Resource Planning System</p>
  </div>
</div>

<!-- Features -->
<div class="card">
  <div class="card-header"><h2>&#127775; Features</h2></div>
  <div class="card-body">
    <ul class="feature-list">
      <li><span class="fi">&#128196;</span> <span><strong>Billing</strong> — Create and manage invoices, receipts and payment records.</span></li>
      <li><span class="fi">&#128230;</span> <span><strong>Inventory</strong> — Track mobile handsets and accessory stock levels in real time.</span></li>
      <li><span class="fi">&#128101;</span> <span><strong>Customers</strong> — Maintain a complete customer database with purchase history.</span></li>
      <li><span class="fi">&#128200;</span> <span><strong>Reports</strong> — Generate daily, monthly and custom sales and profit reports.</span></li>
      <li><span class="fi">&#9989;</span> <span><strong>Health Check</strong> — Monitor application and database status at <a href="/health">/health</a>.</span></li>
    </ul>
  </div>
</div>

<!-- Tech stack -->
<div class="card">
  <div class="card-header"><h2>&#128295; Technology Stack</h2></div>
  <div class="card-body" style="padding-top:.5rem;padding-bottom:.5rem;">
    <table class="tech-table">
      <thead>
        <tr><th>Component</th><th>Technology</th></tr>
      </thead>
      <tbody>
        <tr><td>Language</td><td>PHP <?= PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION ?>+</td></tr>
        <tr><td>Architecture</td><td>MVC (Model-View-Controller)</td></tr>
        <tr><td>Database</td><td>MySQL / MariaDB via PDO</td></tr>
        <tr><td>Web Server</td><td>Apache with mod_rewrite</td></tr>
        <tr><td>Environment</td><td><?= htmlspecialchars(APP_ENV, ENT_QUOTES, 'UTF-8') ?></td></tr>
      </tbody>
    </table>
  </div>
</div>
