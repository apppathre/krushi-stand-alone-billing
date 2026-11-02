# Shree Prasad Krushi Seva Kendra – Billing & Inventory

Single-shop agri-input billing system (seeds, fertilizers, insecticides, pesticides, equipment) built on
**Laravel 11 + Breeze (Blade) + Tailwind + Alpine.js**, with English / मराठी UI.

Portal UX: collapsible sidebar, **Ctrl+K menu search**, AJAX side drawers for masters (no full-page create/edit), mobile-friendly shell, invoice print sizes **A4 / half / 1/4**, and a notification-style dashboard.

## Features

| Phase | What is included |
|-------|------------------|
| A – Foundation | Company profile, DB-stored SMTP (forgot-password over SMTP), roles (Owner / Cashier / Storekeeper), menu-based permissions (view/add/edit/delete per role + per-user overrides), users, godowns + godown switcher, ledgers (clients / suppliers / account books), Account Map, Inventory Map, auto-filtering lists, EN/MR switcher, dashboard |
| B – Inventory | Manufacturers, categories, units, products (HSN, GST %, rates, batch tracking), opening stock, purchases with batch lines, stock movements, batches, stock list, low-stock and expiry alerts |
| C – Sales | GST invoices (CGST+SGST or IGST by state), batch pick per line (FIFO fallback), payments, PDF print, credit notes (sales returns), financial years and document series |
| D – Reports | Party ledger, day book, sales register, purchase register, simple Trading / P&L |

Services: `GstCalculator`, `StockService`, `InvoiceNumberGenerator`, `LedgerPoster` (double-entry), `SaleService`, `PurchaseService`, `ReportService`.

## Requirements

- **PHP 8.4** (this project uses `C:\php84\php.exe`; extensions: mbstring, openssl, pdo_mysql, gd, fileinfo, intl/zip recommended)
- **MariaDB / MySQL** (XAMPP's MariaDB works, `C:\xampp\mysql\bin`)
- Composer 2, Node.js 18+ and npm

## Setup (Windows)

```powershell
cd C:\xampp\htdocs\krushi-stand-alone-billing

# make sure PHP 8.4 is used for this shell
$env:Path = "C:\php84;" + $env:Path

# 1. Database (start MariaDB from the XAMPP control panel first)
C:\xampp\mysql\bin\mysql.exe -u root -e "CREATE DATABASE IF NOT EXISTS krushi_seva_kendra CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

# 2. Dependencies and environment
composer install
copy .env.example .env        # skip if .env already exists
C:\php84\php.exe artisan key:generate

# 3. Tables + seed data
C:\php84\php.exe artisan migrate --seed

# 4. Frontend assets
npm install
npm run build                 # or `npm run dev` while developing

# 5. Run
C:\php84\php.exe artisan serve
```

Open <http://127.0.0.1:8000>.

`.env` database values: `DB_CONNECTION=mysql`, `DB_HOST=127.0.0.1`, `DB_PORT=3306`,
`DB_DATABASE=krushi_seva_kendra`, `DB_USERNAME=root`, `DB_PASSWORD=` (empty).

To reset everything: `C:\php84\php.exe artisan migrate:fresh --seed`.

## Demo logins (password: `password`)

| Role | Email |
|------|-------|
| Owner (full access) | `owner@krushi.local` |
| Cashier | `cashier@krushi.local` |
| Storekeeper | `store@krushi.local` |

Change these passwords (Profile) before using the system for real.

## How it works

- **Menus & permissions** – *Roles & Permissions* grants view/add/edit/delete per menu to each role. *Users → Permission overrides* can
  Allow or Deny a menu action for a single user (Inherit = use role). Owners always have full access. *Menus* lets you rename (EN/MR),
  reorder or hide sidebar entries.
- **Godowns** – the switcher in the top bar sets the active godown. Stock in, stock out, and stock lists use it. Users only see godowns
  assigned to them (owners see all).
- **Account Map** (Settings) – default ledgers used for cash, bank, sales, purchases, GST (CGST/SGST/IGST input & output), round-off,
  discount. **Inventory Map** overrides the sales/purchase ledger per category or per product (product → category → Account Map).
- **GST** – prices are tax-exclusive. If the supply state differs from the company state (Maharashtra, 27) IGST is charged, otherwise CGST + SGST.
- **Batches** – every product is stocked in batches. Products with *batch required* need a batch number on purchase and a batch pick on sale;
  other products use batch `NA`. Expiring batches (60 days) appear on the dashboard.
- **Numbering** – `INV/2026-27/0001`, `PUR/…`, `CN/…`, per financial year (April–March), generated from `document_series`.
- **Ledger posting** – each sale / purchase / payment / credit note posts a balanced voucher into `ledger_entries`; cancelling reverses stock and postings.
- **Email** – *Settings → Mail (SMTP)*: enter host/port/credentials, enable, use *Send test*. Forgot-password mails use these DB settings.
- **PDF** – invoices / DC use **mPDF** with bundled Noto Sans Devanagari (`resources/fonts/devanagari`) for Marathi Unicode; English uses DejaVu. Browser print remains available for Ctrl+P.
- **Language** – EN / मराठी toggle in the header; UI strings live in `lang/mr.json` and masters carry a Marathi name column.

## Tests

```powershell
C:\php84\php.exe artisan test
```

Uses an in-memory SQLite database; includes a smoke test that renders every screen in EN and MR and runs a purchase → sale → credit note → payment → reports flow.
