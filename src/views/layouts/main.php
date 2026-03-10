<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($title ?? APP_NAME, ENT_QUOTES, 'UTF-8') ?></title>
  <style>
    /* ── Reset & base ─────────────────────────────────────────────────────── */
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

    body {
      font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
      background: #f5f6fa;
      color: #333;
      min-height: 100vh;
    }

    /* ── Navbar ───────────────────────────────────────────────────────────── */
    nav {
      background: #1a237e;
      color: #fff;
      padding: .9rem 1.5rem;
      display: flex;
      align-items: center;
      gap: 1.2rem;
    }
    nav .brand {
      font-size: 1.25rem;
      font-weight: 700;
      text-decoration: none;
      color: #fff;
    }
    nav a {
      color: #c5cae9;
      text-decoration: none;
      font-size: .95rem;
    }
    nav a:hover { color: #fff; }

    /* ── Main content ─────────────────────────────────────────────────────── */
    main {
      max-width: 1100px;
      margin: 2rem auto;
      padding: 0 1rem;
    }

    /* ── Footer ───────────────────────────────────────────────────────────── */
    footer {
      text-align: center;
      padding: 1.5rem;
      color: #888;
      font-size: .85rem;
      border-top: 1px solid #e0e0e0;
      margin-top: 3rem;
    }
  </style>
</head>
<body>
  <nav>
    <a class="brand" href="/"><?= htmlspecialchars(APP_NAME, ENT_QUOTES, 'UTF-8') ?></a>
    <a href="/">Dashboard</a>
    <a href="/about">About</a>
    <a href="/health">Health</a>
  </nav>

  <main>
    <?= $content ?>
  </main>

  <footer>
    &copy; <?= date('Y') ?> <?= htmlspecialchars(APP_NAME, ENT_QUOTES, 'UTF-8') ?>. All rights reserved.
  </footer>
</body>
</html>
