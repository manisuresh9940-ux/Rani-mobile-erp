<?php
/**
 * src/views/errors/404.php
 * 404 Not Found — rendered inside the main layout via ErrorController::notFound().
 */
?>
<style>
  .error-page {
    display: flex;
    justify-content: center;
    align-items: center;
    min-height: calc(100vh - 160px);
    padding: 2rem 1rem;
  }
  .error-card {
    background: #fff;
    border-radius: 12px;
    box-shadow: 0 4px 24px rgba(26,35,126,.12);
    padding: 3rem 2.5rem;
    text-align: center;
    max-width: 480px;
    width: 100%;
  }
  .error-icon  { font-size: 3rem; margin-bottom: .5rem; }
  .error-code  {
    font-size: 6rem;
    font-weight: 900;
    line-height: 1;
    color: #1a237e;
    letter-spacing: -.04em;
  }
  .error-card h2 { font-size: 1.4rem; color: #283593; margin: .75rem 0 .5rem; }
  .error-card p  { color: #78909c; font-size: .95rem; line-height: 1.6; margin-bottom: 1.75rem; }
  .btn-home {
    display: inline-flex;
    align-items: center;
    gap: .5rem;
    padding: .75rem 2rem;
    background: #1a237e;
    color: #fff;
    text-decoration: none;
    border-radius: 6px;
    font-size: .95rem;
    font-weight: 600;
    transition: background .15s;
  }
  .btn-home:hover { background: #283593; }
  .app-name { margin-top: 2rem; color: #b0bec5; font-size: .78rem; }
</style>

<div class="error-page">
  <div class="error-card">
    <div class="error-icon">&#128270;</div>
    <div class="error-code">404</div>
    <h2>Page Not Found</h2>
    <p>The page you are looking for doesn't exist or may have been moved.
       Please check the URL or return to the dashboard.</p>
    <a class="btn-home" href="/">&#8592; Back to Dashboard</a>
    <div class="app-name"><?= htmlspecialchars(APP_NAME, ENT_QUOTES, 'UTF-8') ?></div>
  </div>
</div>
