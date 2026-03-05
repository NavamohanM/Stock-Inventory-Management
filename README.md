# StockIMS — Stock Inventory Management System

A production-ready PHP inventory management system with dashboard, charts, sales, purchases, reports, and user management. Deployable on Railway with MySQL.

---

## Features

- **Dashboard** — Revenue bar+line chart, stock donut chart, units sold trend, low stock alerts, top selling products, clickable stat cards
- **Products** — Add, edit, soft-delete with categories, suppliers, SKU, cost price, selling price, per-product low stock threshold, search, filter, pagination
- **Purchase** — Add stock to existing products, track supplier, auto-updates stock level with transaction safety
- **Sales** — Sell products, auto-deducts stock, customer name, real-time total calculation
- **Reports** — Sales, purchase, stock reports with date filters and CSV export + print
- **User Management** — Admin and staff roles, bcrypt password hashing, session timeout, password reset
- **Categories & Suppliers** — Full CRUD
- **Security** — Prepared statements (SQL injection safe), CSRF protection, secure session handling

---

## Tech Stack

| Layer | Technology |
|---|---|
| Backend | PHP 8.3 |
| Database | MySQL / MariaDB |
| Frontend | Bootstrap 5.3, Chart.js 4, Bootstrap Icons |
| Deployment | Railway (Nixpacks) |

---

## Local Setup

### Requirements
- PHP 8.3+ with `mysqli` extension
- MySQL or MariaDB

### 1. Install PHP mysqli extension
```bash
sudo apt install php8.3-mysql -y
```

### 2. Create database user and import schema
```bash
sudo mysql
```
```sql
CREATE DATABASE IF NOT EXISTS ims480;
CREATE USER 'imsuser'@'localhost' IDENTIFIED BY 'ims@pass123';
GRANT ALL PRIVILEGES ON ims480.* TO 'imsuser'@'localhost';
FLUSH PRIVILEGES;
EXIT;
```
```bash
mysql -u imsuser -p'ims@pass123' ims480 < database.sql
```

### 3. Configure environment
```bash
cp .env.example .env
```
Edit `.env`:
```env
MYSQLHOST=localhost
MYSQLPORT=3306
MYSQLUSER=imsuser
MYSQLPASSWORD=ims@pass123
MYSQLDATABASE=ims480
CURRENCY=₹
LOW_STOCK_THRESHOLD=10
```

### 4. Start the server
```bash
php -S localhost:8080
```

Open **http://localhost:8080**

---

## Default Login

| Username | Password | Role  |
|----------|----------|-------|
| `admin`  | `admin123` | Admin |

> **Change the password immediately after first login** — go to Profile page.

---

## Railway Deployment

1. Push this repo to GitHub
2. Go to [railway.app](https://railway.app) → **New Project** → **Deploy from GitHub repo**
3. Add MySQL: click **+ New** → **Database** → **MySQL**
4. Railway auto-injects `MYSQLHOST`, `MYSQLUSER`, `MYSQLPASSWORD`, `MYSQLDATABASE` — the app reads these automatically
5. Import the schema — in Railway → MySQL service → **Connect** button → **Public Network** tab → copy the credentials, then run:
   ```bash
   mysql -h HOST -u root -pPASSWORD --port PORT railway < database.sql
   ```
6. Your PHP app service → **Settings** → **Generate Domain** to get a public URL
7. App is live — login with `admin` / `admin123`

---

## Project Structure

```
├── dashboard.php              # Main dashboard with 3 charts
├── login.php                  # Login page
├── logout.php                 # Session destroy + redirect
├── index.php                  # Root redirect to dashboard
├── database.sql               # Full DB schema + seed data
├── railway.json               # Railway deployment config
├── nixpacks.toml              # PHP 8.3 build config for Railway
├── .env.example               # Environment variable template
├── includes/
│   ├── config.php             # DB constants, .env loader
│   ├── db.php                 # Prepared statement query helpers
│   ├── auth.php               # Session, CSRF, flash messages
│   ├── header.php             # Navbar + session guard
│   └── footer.php             # JS includes, footer
├── pages/
│   ├── products.php           # Product list, add, edit, delete
│   ├── purchase.php           # Purchase / restock existing products
│   ├── sales.php              # Record sales, live total calculator
│   ├── sales_report.php       # Sales report + CSV export
│   ├── purchase_report.php    # Purchase report + CSV export
│   ├── stock_report.php       # Stock value report + CSV export
│   ├── categories.php         # Category management (admin)
│   ├── suppliers.php          # Supplier management (admin)
│   ├── users.php              # User management (admin)
│   └── profile.php            # Profile + password change
└── assets/
    ├── css/app.css            # Custom styles
    └── js/app.js              # Client-side interactions
```

---

## Screenshots

| Page | Description |
|---|---|
| Dashboard | Revenue chart, donut chart, low stock alerts |
| Products | Searchable, filterable product table |
| Sales | Sell with live price calculation |
| Reports | Date-filtered reports with CSV export |

---

## License

MIT — free to use and modify.
