# Rani Mobiles ERP — HTTP 500 Debugging Guide

This document explains every common cause of an **HTTP 500 Internal Server
Error** in this PHP project and how to diagnose and fix each one.

---

## 1. Enable PHP Error Reporting (first thing to do)

An HTTP 500 from PHP usually means an unhandled exception or syntax error.
PHP's default configuration hides these — you need to enable reporting
temporarily to see what went wrong.

### Option A — via `.env` file (recommended)

```ini
APP_ENV=development
APP_DEBUG=true
```

The `src/config/config.php` file reads these values and sets:

```php
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);
```

### Option B — add to the very top of `public/index.php` (temporary)

```php
<?php
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);
```

> **Remove this before pushing to production.**

### Option C — in `php.ini`

Find your `php.ini` with `php --ini` or `phpinfo()`, then set:

```ini
display_errors = On
display_startup_errors = On
error_reporting = E_ALL
log_errors = On
error_log = /var/log/php/error.log
```

Restart Apache/Nginx after changes: `sudo systemctl restart apache2`.

---

## 2. Check Server Error Logs

Even with display_errors off, PHP writes errors to the log file.

| Server       | Default log location                         |
|--------------|----------------------------------------------|
| Apache       | `/var/log/apache2/error.log`                 |
| Nginx + PHP-FPM | `/var/log/nginx/error.log` + `/var/log/php8.x-fpm.log` |
| cPanel       | `~/logs/error_log`                           |
| This project | `logs/app.log` (path set in `.env` → `LOG_PATH`) |

```bash
# Watch logs in real time
tail -f /var/log/apache2/error.log
tail -f logs/app.log
```

---

## 3. Verify the Document Root

**The document root MUST point to the `public/` subdirectory**, not the
project root.

### Apache Virtual Host (`/etc/apache2/sites-available/ranimobile.conf`)

```apache
<VirtualHost *:80>
    ServerName ranimobile.com
    ServerAlias www.ranimobile.com
    DocumentRoot /var/www/Rani-mobile-erp/public

    <Directory /var/www/Rani-mobile-erp/public>
        AllowOverride All
        Require all granted
    </Directory>

    ErrorLog  ${APACHE_LOG_DIR}/ranimobile_error.log
    CustomLog ${APACHE_LOG_DIR}/ranimobile_access.log combined
</VirtualHost>
```

Enable the site and reload:

```bash
sudo a2ensite ranimobile.conf
sudo systemctl reload apache2
```

### cPanel / Shared Hosting

1. Point the domain's document root to `public_html/public/`.  
   OR  
2. Move the project root **above** `public_html/` and symlink:
   ```bash
   # e.g. home directory layout
   ~/Rani-mobile-erp/   ← project root (not web-accessible)
   ~/public_html/       ← symlink → ~/Rani-mobile-erp/public/
   ```

---

## 4. Enable mod_rewrite (Apache)

The `public/.htaccess` uses `mod_rewrite` for URL routing. If it is not
enabled you will get a 500 or 404.

```bash
sudo a2enmod rewrite
sudo systemctl restart apache2
```

Verify `AllowOverride All` is set in the `<Directory>` block (see above).

---

## 5. Check File Permissions

Wrong permissions are a common cause of 500 errors.

```bash
# Recommended permissions
find /var/www/Rani-mobile-erp -type d -exec chmod 755 {} \;
find /var/www/Rani-mobile-erp -type f -exec chmod 644 {} \;

# The logs directory needs to be writable by the web server user
chmod 775 /var/www/Rani-mobile-erp/logs
chown -R www-data:www-data /var/www/Rani-mobile-erp
```

| Path type       | Permission |
|-----------------|------------|
| Directories     | `755`      |
| PHP/HTML files  | `644`      |
| `logs/`         | `775` (web-server writable) |
| `.env`          | `640` (not world-readable) |

---

## 6. Verify Database Connection

Use the built-in health-check endpoint:

```
GET https://ranimobile.com/health
```

A successful response looks like:

```json
{ "app": "ok", "php": "8.2.x", "env": "production", "database": "ok" }
```

If `"database": "error"` appears, the problem is in the DB connection.
Enable `APP_DEBUG=true` temporarily to see the exact PDO error message.

Common fixes:
- Confirm the MySQL service is running: `sudo systemctl status mysql`
- Check credentials in `.env` (`DB_HOST`, `DB_USER`, `DB_PASS`, `DB_NAME`)
- Ensure the database exists: `SHOW DATABASES;` in the MySQL CLI
- Check that the MySQL user has privileges: `SHOW GRANTS FOR 'rani_user'@'localhost';`
- If on a remote host, check firewall rules: `sudo ufw allow 3306`

---

## 7. PHP Version Compatibility

This project requires **PHP 8.0 or later** (uses `match`, `str_starts_with`,
named arguments, `never` return type, etc.).

```bash
php --version
# Should report PHP 8.0.x or higher

# If your server has multiple PHP versions (cPanel, etc.)
php8.1 --version
```

Switch Apache to PHP 8:

```bash
sudo a2dismod php7.4
sudo a2enmod php8.1
sudo systemctl restart apache2
```

---

## 8. Check `.htaccess` Syntax

A syntax error in `.htaccess` causes a 500 immediately.

```bash
# Test Apache configuration (catches .htaccess errors too)
sudo apache2ctl configtest
# Should print: Syntax OK
```

---

## 9. Check `include` / `require` Paths

This project uses `APP_ROOT` (defined in `public/index.php`) combined with
`__DIR__`-relative paths so paths are always correct regardless of the
working directory. Never use relative paths like `require '../config.php'`
— use:

```php
require APP_ROOT . '/src/config/config.php';
```

---

## 10. Step-by-Step Debugging Checklist

```
[ ] 1. Set APP_DEBUG=true in .env → reload the page to see the real error
[ ] 2. Check logs/app.log and /var/log/apache2/error.log
[ ] 3. Confirm document root points to public/
[ ] 4. Verify mod_rewrite is enabled and AllowOverride All is set
[ ] 5. Visit /health endpoint to verify app boots and DB connects
[ ] 6. Check file permissions (755 dirs / 644 files / 775 logs/)
[ ] 7. Confirm PHP 8.0+ is active (php --version)
[ ] 8. Run: sudo apache2ctl configtest
[ ] 9. Ensure .env exists and credentials are correct
[ ] 10. Set APP_DEBUG=false once the issue is resolved
```
