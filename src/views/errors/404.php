<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>404 — Page Not Found | <?= htmlspecialchars(APP_NAME, ENT_QUOTES, 'UTF-8') ?></title>
  <style>
    body { font-family: 'Segoe UI', sans-serif; background: #f5f6fa; display: flex;
           justify-content: center; align-items: center; min-height: 100vh; margin: 0; }
    .box { text-align: center; background: #fff; padding: 3rem 2.5rem;
           border-radius: 10px; box-shadow: 0 4px 20px rgba(0,0,0,.1); max-width: 460px; }
    h1   { font-size: 5rem; color: #1a237e; margin: 0; }
    h2   { color: #333; margin: .5rem 0 1rem; }
    p    { color: #666; margin-bottom: 1.5rem; }
    a    { display: inline-block; padding: .7rem 1.8rem; background: #1a237e;
           color: #fff; text-decoration: none; border-radius: 5px; }
    a:hover { background: #283593; }
  </style>
</head>
<body>
  <div class="box">
    <h1>404</h1>
    <h2>Page Not Found</h2>
    <p>The page you are looking for does not exist or has been moved.</p>
    <a href="/">&#8592; Back to Dashboard</a>
  </div>
</body>
</html>
