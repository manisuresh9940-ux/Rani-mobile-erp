# Rani Mobiles ERP

**Rani Mobiles Sales & Service** — A high-performance multi-branch mobile retail ERP system.

---

## Features

| Module | Features |
|---|---|
| 🏠 **Dashboard** | Live KPI cards (sales, purchase, profit, cash, stock), animated ApexCharts, branch comparison, low-stock alerts, dead-stock detection |
| 🛒 **POS Sales** | Instant search by name/brand/barcode/IMEI/price-range, camera barcode scanning (ZXing), multi-mode payment (Cash/UPI/Card/Credit), GST/Non-GST billing |
| 📦 **Purchase** | New purchase with IMEI entry, vendor management, automatic stock update, GST tracking |
| 📊 **Inventory** | Branch-wise stock view, low/out-of-stock indicators (🟢🟡🔴), dead stock detection |
| 🔄 **Branch Transfer** | Internal stock transfer with transfer price & automatic profit calculation |
| 🔧 **Service / Job Cards** | Create job cards (received → diagnosed → in repair → ready → delivered), technician assignment |
| ♻️ **Second-Hand Mobile** | Buy with seller photo + ID proof upload, sell with profit calculation |
| 💰 **Accounts** | Expenses, day closing with cash mismatch alert, payments received/made, customer & vendor ledger |
| 📈 **Reports** | Sales, Purchase, Stock, Branch Comparison, Profit (with charts) |
| ⚙️ **Settings** | Branches, Users/Staff, General Settings, Activity Log, Database Backup |

---

## Technology Stack

- **PHP 8+** (OOP, PDO, password_hash)
- **MySQL** (InnoDB, foreign keys, transactions)
- **Bootstrap 5.3** + Bootstrap Icons
- **ApexCharts** (animated, modern charts)
- **ZXing JS** (camera barcode scanning)
- Works on **XAMPP localhost** and **Shared Hosting**

---

## Installation

### 1. Import Database

```sql
-- In phpMyAdmin or MySQL CLI:
source database/schema.sql;
```

### 2. Configure Connection

Edit `config/config.php`:

```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'rani_erp');
define('DB_USER', 'root');
define('DB_PASS', '');
define('BASE_URL', '/Rani-mobile-erp');  // adjust to your web path
```

### 3. Create Uploads Directory

Ensure the `uploads/` directory is writable:

```bash
chmod 755 uploads/
chmod 755 uploads/secondhand/
```

### 4. Access the Application

Open: `http://localhost/Rani-mobile-erp/`

**Default Login:**
- Username: `admin`
- Password: `Admin@1234`

> ⚠️ Change the admin password immediately after first login.

---

## Branches

The system is pre-configured with 5 branches: **R1, R2, R3, R4, R5**

Each branch maintains independent: stock, sales, purchases, cash, expenses, staff.

Admin users see combined reports across all branches.

---

## Serial Number Format

All transactions use unique branch-scoped serial numbers:

```
R1-SAL-2026-0001  → Sale invoice
R1-PUR-2026-0001  → Purchase invoice
R1-TRF-2026-0001  → Branch transfer
R1-JOB-2026-0001  → Service job card
R1-EXP-2026-0001  → Expense
R1-SHB-2026-0001  → Second-hand buy
R1-SHS-2026-0001  → Second-hand sell
```

---

## Security

- CSRF protection on all POST forms
- Password hashing with bcrypt (cost 12)
- Session regeneration on login and periodically
- `.htaccess` blocks PHP execution in uploads directory
- Prepared statements throughout (no SQL injection)
- Input sanitization with `htmlspecialchars`

---

## License

Proprietary — Rani Mobiles Sales & Service

