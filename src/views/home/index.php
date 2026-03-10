<?php
/**
 * src/views/home/index.php
 * Dashboard / home view.
 */
?>

<style>
  .hero {
    background: linear-gradient(135deg, #1a237e, #283593);
    color: #fff;
    border-radius: 8px;
    padding: 2.5rem 2rem;
    margin-bottom: 2rem;
  }
  .hero h1 { font-size: 2rem; margin-bottom: .5rem; }
  .hero p  { opacity: .85; font-size: 1.05rem; }

  .cards {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
    gap: 1.25rem;
  }
  .card {
    background: #fff;
    border-radius: 8px;
    padding: 1.5rem;
    box-shadow: 0 2px 8px rgba(0,0,0,.08);
    text-align: center;
  }
  .card .icon  { font-size: 2.5rem; margin-bottom: .5rem; }
  .card h3    { font-size: 1rem; color: #1a237e; margin-bottom: .25rem; }
  .card p     { font-size: .85rem; color: #666; }
</style>

<div class="hero">
  <h1>&#128241; <?= htmlspecialchars($appName ?? APP_NAME, ENT_QUOTES, 'UTF-8') ?></h1>
  <p>Manage your mobile billing, inventory and customer relationships in one place.</p>
</div>

<div class="cards">
  <div class="card">
    <div class="icon">&#128196;</div>
    <h3>Billing</h3>
    <p>Create and manage invoices quickly.</p>
  </div>
  <div class="card">
    <div class="icon">&#128230;</div>
    <h3>Inventory</h3>
    <p>Track stock levels in real time.</p>
  </div>
  <div class="card">
    <div class="icon">&#128101;</div>
    <h3>Customers</h3>
    <p>Maintain a complete customer database.</p>
  </div>
  <div class="card">
    <div class="icon">&#128200;</div>
    <h3>Reports</h3>
    <p>Generate sales and profit reports.</p>
  </div>
</div>
