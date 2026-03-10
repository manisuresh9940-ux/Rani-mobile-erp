<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>500 — Internal Server Error | <?= htmlspecialchars(APP_NAME ?? 'Rani Mobiles ERP', ENT_QUOTES, 'UTF-8') ?></title>
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    body {
      font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
      background: #f0f2f8;
      display: flex;
      flex-direction: column;
      justify-content: center;
      align-items: center;
      min-height: 100vh;
      color: #37474f;
      padding: 1rem;
    }
    .error-card {
      background: #fff;
      border-radius: 12px;
      box-shadow: 0 4px 24px rgba(183,28,28,.10);
      padding: 3rem 2.5rem;
      text-align: center;
      max-width: 480px;
      width: 100%;
    }
    .error-icon {
      font-size: 3rem;
      margin-bottom: .5rem;
    }
    .error-code {
      font-size: 6rem;
      font-weight: 900;
      line-height: 1;
      color: #b71c1c;
      letter-spacing: -.04em;
    }
    h2 { font-size: 1.4rem; color: #c62828; margin: .75rem 0 .5rem; }
    p  { color: #78909c; font-size: .95rem; line-height: 1.6; margin-bottom: 1.75rem; }
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
    .app-name {
      margin-top: 2rem;
      color: #b0bec5;
      font-size: .78rem;
    }
  </style>
</head>
<body>
  <div class="error-card">
    <div class="error-icon">&#9888;&#65039;</div>
    <div class="error-code">500</div>
    <h2>Internal Server Error</h2>
    <p>Something went wrong on our end. The error has been logged and we are looking into it. Please try again later.</p>
    <a class="btn-home" href="/">&#8592; Back to Dashboard</a>
    <div class="app-name"><?= htmlspecialchars(APP_NAME ?? 'Rani Mobiles ERP', ENT_QUOTES, 'UTF-8') ?></div>
  </div>
</body>
</html>
