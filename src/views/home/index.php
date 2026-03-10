<?php
/**
 * src/views/home/index.php
 * Dashboard / home view.
 */
?>

<style>
  /* ── Page title ─────────────────────────────────────────────────────────── */
  .page-title { margin-bottom: 1.75rem; }
  .page-title h1 { font-size: 1.5rem; font-weight: 700; color: #1a237e; }
  .page-title p  { color: #78909c; font-size: .9rem; margin-top: .2rem; }

  /* ── Stats row ──────────────────────────────────────────────────────────── */
  .stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
    gap: 1.25rem;
    margin-bottom: 2rem;
  }
  .stat-card {
    background: #fff;
    border-radius: 8px;
    padding: 1.25rem 1.5rem;
    box-shadow: 0 2px 10px rgba(26,35,126,.08);
    display: flex;
    align-items: center;
    gap: 1rem;
  }
  .stat-card .stat-icon {
    width: 48px;
    height: 48px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
    flex-shrink: 0;
  }
  .stat-card .stat-icon.blue   { background: #e8eaf6; }
  .stat-card .stat-icon.green  { background: #e8f5e9; }
  .stat-card .stat-icon.orange { background: #fff3e0; }
  .stat-card .stat-icon.purple { background: #f3e5f5; }
  .stat-card .stat-body { min-width: 0; }
  .stat-card .stat-value {
    font-size: 1.6rem;
    font-weight: 700;
    color: #1a237e;
    line-height: 1.1;
  }
  .stat-card .stat-label {
    font-size: .78rem;
    color: #78909c;
    text-transform: uppercase;
    letter-spacing: .05em;
    margin-top: .2rem;
  }

  /* ── Two-column section ─────────────────────────────────────────────────── */
  .two-col {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1.5rem;
    margin-bottom: 2rem;
  }
  @media (max-width: 900px) { .two-col { grid-template-columns: 1fr; } }

  /* ── Card ───────────────────────────────────────────────────────────────── */
  .card {
    background: #fff;
    border-radius: 8px;
    box-shadow: 0 2px 10px rgba(26,35,126,.08);
    overflow: hidden;
  }
  .card-header {
    padding: 1rem 1.5rem;
    border-bottom: 1px solid #e8eaf6;
    display: flex;
    align-items: center;
    justify-content: space-between;
  }
  .card-header h2 {
    font-size: .95rem;
    font-weight: 700;
    color: #1a237e;
  }
  .card-header a {
    font-size: .8rem;
    color: #5c6bc0;
    text-decoration: none;
  }
  .card-header a:hover { text-decoration: underline; }
  .card-body { padding: 1.25rem 1.5rem; }

  /* ── Quick actions ──────────────────────────────────────────────────────── */
  .actions-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: .75rem;
  }
  .action-btn {
    display: flex;
    align-items: center;
    gap: .6rem;
    padding: .75rem 1rem;
    border-radius: 6px;
    background: #f5f6ff;
    text-decoration: none;
    color: #1a237e;
    font-size: .88rem;
    font-weight: 600;
    border: 1px solid #e8eaf6;
    transition: background .15s, border-color .15s;
  }
  .action-btn:hover { background: #e8eaf6; border-color: #9fa8da; }
  .action-btn .icon { font-size: 1.1rem; }

  /* ── Recent activity list ───────────────────────────────────────────────── */
  .activity-list { list-style: none; }
  .activity-list li {
    display: flex;
    align-items: flex-start;
    gap: .75rem;
    padding: .65rem 0;
    border-bottom: 1px solid #f0f2f8;
    font-size: .88rem;
  }
  .activity-list li:last-child { border-bottom: none; }
  .activity-list .dot {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    margin-top: .3rem;
    flex-shrink: 0;
  }
  .dot-blue   { background: #3f51b5; }
  .dot-green  { background: #4caf50; }
  .dot-orange { background: #ff9800; }
  .activity-list .time { color: #9e9e9e; font-size: .77rem; margin-left: auto; white-space: nowrap; }

  /* ── Module cards row ───────────────────────────────────────────────────── */
  .modules-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
    gap: 1.25rem;
    margin-bottom: 2rem;
  }
  .module-card {
    background: #fff;
    border-radius: 8px;
    padding: 1.5rem;
    box-shadow: 0 2px 10px rgba(26,35,126,.08);
    text-align: center;
    text-decoration: none;
    color: inherit;
    border: 2px solid transparent;
    transition: border-color .15s, transform .15s, box-shadow .15s;
    display: block;
  }
  .module-card:hover {
    border-color: #9fa8da;
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(26,35,126,.12);
  }
  .module-card .icon  { font-size: 2.5rem; margin-bottom: .6rem; line-height: 1; }
  .module-card h3     { font-size: .95rem; color: #1a237e; margin-bottom: .3rem; font-weight: 700; }
  .module-card p      { font-size: .8rem; color: #78909c; }
</style>

<!-- Page title -->
<div class="page-title">
  <h1>&#127968; Dashboard</h1>
  <p>Welcome back! Here's what's happening at <?= htmlspecialchars($appName ?? APP_NAME, ENT_QUOTES, 'UTF-8') ?>.</p>
</div>

<!-- Stats row -->
<div class="stats-grid">
  <div class="stat-card">
    <div class="stat-icon blue">&#128196;</div>
    <div class="stat-body">
      <div class="stat-value">—</div>
      <div class="stat-label">Invoices Today</div>
    </div>
  </div>
  <div class="stat-card">
    <div class="stat-icon green">&#128230;</div>
    <div class="stat-body">
      <div class="stat-value">—</div>
      <div class="stat-label">Items in Stock</div>
    </div>
  </div>
  <div class="stat-card">
    <div class="stat-icon orange">&#128101;</div>
    <div class="stat-body">
      <div class="stat-value">—</div>
      <div class="stat-label">Total Customers</div>
    </div>
  </div>
  <div class="stat-card">
    <div class="stat-icon purple">&#128200;</div>
    <div class="stat-body">
      <div class="stat-value">—</div>
      <div class="stat-label">Revenue (Month)</div>
    </div>
  </div>
</div>

<!-- Quick actions + Recent activity -->
<div class="two-col">
  <div class="card">
    <div class="card-header">
      <h2>&#9889; Quick Actions</h2>
    </div>
    <div class="card-body">
      <div class="actions-grid">
        <a class="action-btn" href="/billing">
          <span class="icon">&#10133;</span> New Invoice
        </a>
        <a class="action-btn" href="/inventory">
          <span class="icon">&#128230;</span> Add Stock
        </a>
        <a class="action-btn" href="/customers">
          <span class="icon">&#128101;</span> New Customer
        </a>
        <a class="action-btn" href="/reports">
          <span class="icon">&#128200;</span> View Reports
        </a>
      </div>
    </div>
  </div>

  <div class="card">
    <div class="card-header">
      <h2>&#128338; Recent Activity</h2>
    </div>
    <div class="card-body">
      <ul class="activity-list">
        <li>
          <span class="dot dot-blue"></span>
          <span>System started successfully</span>
          <span class="time">Just now</span>
        </li>
        <li>
          <span class="dot dot-green"></span>
          <span>Database connection established</span>
          <span class="time">Just now</span>
        </li>
        <li>
          <span class="dot dot-orange"></span>
          <span>Ready for configuration</span>
          <span class="time">Just now</span>
        </li>
      </ul>
    </div>
  </div>
</div>

<!-- ERP module cards -->
<div class="card" style="margin-bottom:2rem;">
  <div class="card-header">
    <h2>&#127775; ERP Modules</h2>
  </div>
  <div class="card-body" style="padding: 1.25rem 1.25rem;">
    <div class="modules-grid" style="margin-bottom:0;">
      <a class="module-card" href="/billing">
        <div class="icon">&#128196;</div>
        <h3>Billing</h3>
        <p>Create and manage invoices quickly.</p>
      </a>
      <a class="module-card" href="/inventory">
        <div class="icon">&#128230;</div>
        <h3>Inventory</h3>
        <p>Track stock levels in real time.</p>
      </a>
      <a class="module-card" href="/customers">
        <div class="icon">&#128101;</div>
        <h3>Customers</h3>
        <p>Maintain a complete customer database.</p>
      </a>
      <a class="module-card" href="/reports">
        <div class="icon">&#128200;</div>
        <h3>Reports</h3>
        <p>Generate sales and profit reports.</p>
      </a>
    </div>
  </div>
</div>
