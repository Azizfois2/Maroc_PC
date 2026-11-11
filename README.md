<p align="center">
  <img src="logo.png" alt="Maroc PC" width="160">
</p>

<h1 align="center">Maroc PC</h1>

<p align="center">
  <strong>E-commerce platform for PC hardware and laptops in Morocco.</strong><br>
  A multilingual PHP/MySQL storefront with a custom PC builder, online payments, and a complete admin back office.
</p>

<p align="center">
  <img alt="PHP 8.1+" src="https://img.shields.io/badge/PHP-8.1%2B-777BB4?logo=php&logoColor=white">
  <img alt="MySQL" src="https://img.shields.io/badge/MySQL-5.7%2B-4479A1?logo=mysql&logoColor=white">
  <img alt="Stripe" src="https://img.shields.io/badge/Stripe-Payments-635BFF?logo=stripe&logoColor=white">
  <img alt="PWA" src="https://img.shields.io/badge/PWA-enabled-5A0FC8?logo=pwa&logoColor=white">
</p>

## Overview

Maroc PC is the full source of a computer-hardware shop: the public storefront, customer accounts, cart and checkout, and the admin tools used to run the catalogue, stock, orders and marketing. It is a server-rendered PHP application (no framework) backed by MySQL, with vanilla JavaScript and CSS on the front end.

The site ships in **Arabic, French, English and Spanish**, works as an installable PWA, and integrates with Stripe, PayPal, five OAuth providers, SMTP mail, WhatsApp/SMS two-factor delivery and Cloudflare Turnstile.

## Features

### Storefront

- Product catalogue with categories, search, filtering, comparison and product detail pages.
- **Custom PC builder** with component compatibility rules (socket, power, chassis clearance) and saved builds.
- **Laptop finder** that narrows laptops by usage, portability, screen quality, battery and GPU tier.
- Community build showcase with likes and comments.
- Flash sales, coupons and price-history graphs.
- Wishlist, price-drop alerts and restock notifications.
- Product reviews with votes and testimonial moderation.
- Loyalty points, rewards catalogue and redemptions.
- Support pages for shipping, returns, warranty, RMA and maintenance requests.
- AI chatbot with a feedback loop and conversation logs.

### Accounts and checkout

- Registration, email verification, password reset and profile management.
- OAuth sign-in with **Google, Facebook, Discord, Xbox and Steam**.
- Two-factor authentication with backup codes; codes can be delivered by email, WhatsApp (Evolution API) or SMS (Textbee).
- Cart, guest checkout, coupon validation and stock locking at checkout.
- **Stripe** Payment Intents and optional **PayPal** server-side verification.
- PDF pickup tickets and order confirmation emails.
- Newsletter subscription, feedback and feature requests.

### Admin back office

- Dashboard with sales, visitor and product analytics.
- Product, laptop and inventory management with CSV import/export.
- Orders, bulk status updates, order history and exports.
- Procurement: vendors, suppliers, purchase orders and stock levels.
- Customers, reviews, feedback, marketing campaigns and newsletter templates.
- Coupons, flash sales, abandoned-cart recovery and scheduled email queue.
- Diagnostics, maintenance schedules, security audit log and admin user management.

## Tech stack

| Layer | Technology |
| --- | --- |
| Language | PHP 8.1+ (strict types, PDO) |
| Database | MySQL 5.7+ / MariaDB 10.2+ (utf8mb4, JSON columns) |
| Front end | Vanilla JavaScript, CSS, service worker + web manifest (PWA) |
| Payments | Stripe PHP SDK, PayPal (optional) |
| Mail | PHPMailer (vendored in `lib/`) and PHP `mail()` fallback |
| Auth | PHP sessions, bcrypt hashes, OAuth 2.0, TOTP/2FA |
| Server | Apache with `mod_rewrite` (`.htaccess` included) |
| Dependencies | Composer (`google/apiclient`, `stripe/stripe-php`) |

## Repository layout

| Path | Contents |
| --- | --- |
| `index.php`, `products.php`, `cart.php`, `checkout.php` | Storefront entry points |
| `api/` | JSON endpoints used by the storefront and admin UI |
| `includes/` | Layout partials (`store-head`, `store-header`, `store-footer`), i18n and visitor tracking |
| `src/` | Application code: `bootstrap/`, `Services/`, `Http/`, `Chatbot/` |
| `lang/` | Translation catalogues: `ar.php`, `fr.php`, `en.php`, `es.php` |
| `scripts/` | Database setup, migrations, seeders and the admin CLI |
| `assets/` | CSS, JavaScript, fonts and images |
| `lib/PHPMailer/` | Vendored PHPMailer |
| `adminlogin.php`, `admin-*.php`, `dashboard.php` | Admin authentication and back-office pages |

## Getting started

### Requirements

- PHP **8.1 or newer** with `pdo_mysql`, `mbstring`, `openssl` and `fileinfo`.
- MySQL 5.7+ or MariaDB 10.2+.
- Composer.
- Apache with `mod_rewrite` for the included `.htaccess`, or the PHP built-in server for local development.

### 1. Clone and install

```bash
git clone https://github.com/Azizfois2/Maroc_PC.git
cd Maroc_PC
composer install
```

### 2. Create the environment file

```bash
cp env.example .env        # Windows: copy env.example .env
```

At minimum, set `APP_URL` and the `DB_*` values. Payment, mail, OAuth and CAPTCHA keys are optional for local work; features that need them simply stay disabled until they are filled in.

### 3. Create the database and tables

```bash
mysql -u root -p -e "CREATE DATABASE maroc_pc CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

php scripts/setup-tables.php       # core schema (~50 tables)
php setup-laptops-table.php        # laptop catalogue + sample rows
```

Optional demo data:

```bash
php scripts/seed-data.php
php scripts/seed-motherboards.php
php scripts/seed-flash-sales.php
php scripts/seed-community-builds.php
php scripts/seed-price-history.php
```

### 4. Create an administrator

```bash
php scripts/create-admin.php admin@example.com "a-password-of-10-chars-or-more" "Administrator"
```

### 5. Run it

With Apache/XAMPP, place the project in the web root (for example `htdocs/Test`) and set:

```dotenv
APP_URL=http://localhost/Test/
```

Then open <http://localhost/Test/>.

Or use the PHP built-in server:

```bash
php -S localhost:8000
```

with `APP_URL=http://localhost:8000/`. The built-in server ignores `.htaccess`, but every page is reachable through its `.php` URL.

The admin sign-in page is **`/adminlogin.php`**.

## Configuration

All configuration lives in `.env` (loaded by `bootstrap.php`; `.env.local` overrides it when present).

| Variable | Purpose |
| --- | --- |
| `APP_URL` | Public base URL, including the subdirectory and trailing slash |
| `DEV_MODE` | `true` shows PHP errors locally; set `false` in production |
| `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS` | MySQL connection |
| `MAIL_FROM`, `SMTP_*` | Outgoing mail (PHPMailer) |
| `GOOGLE_*`, `FB_*`, `DISCORD_*`, `XBOX_*` | OAuth applications |
| `STRIPE_PUBLISHABLE_KEY`, `STRIPE_SECRET_KEY` | Card payments |
| `PAYPAL_*` | Optional PayPal verification |
| `TURNSTILE_SITE_KEY`, `TURNSTILE_SECRET_KEY` | Cloudflare Turnstile CAPTCHA |
| `EVOLUTION_API_*` | WhatsApp delivery for two-factor codes |
| `TEXTBEE_*` | SMS delivery for two-factor codes |

## Localization

UI strings live in `lang/` and are resolved through `i18n_t()` in `includes/i18n.php`. Arabic is rendered right-to-left. Two audit scripts help keep the catalogues complete:

```bash
php scripts/i18n-audit.php
php scripts/i18n-catalog-audit.php
```

## Security notes

The codebase includes prepared statements, CSRF tokens, `password_hash()`, login lockout, API rate limiting, session hardening, Turnstile verification and an admin audit log. Before exposing an installation publicly:

- Set `DEV_MODE=false` and use HTTPS.
- Replace every example credential in `.env`; never commit `.env`.
- Keep the `.htaccess` rules that block dotfiles, `composer.*` and `*.sql`.
- Rotate OAuth, Stripe and SMTP keys if they have ever been shared.

## Project status

This repository mirrors a working storefront and back office. It does not include an automated test suite; the `scripts/i18n-*.php` and `api/db-diagnostic.php` helpers are the closest thing to health checks.

## License

No license file is included. The source is published for viewing and portfolio purposes; all rights are reserved by the author. Add a `LICENSE` file if you intend to allow reuse or contribution.
