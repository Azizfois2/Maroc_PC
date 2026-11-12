<p align="center">
  <img src="logo.png" alt="Maroc PC" width="160">
</p>

<h1 align="center">Maroc PC</h1>

<p align="center">
  <strong>E-commerce platform for PC hardware and laptops in Morocco.</strong><br>
  A multilingual PHP/MySQL storefront, a component configurator, online payments,<br>
  and the back office used to run the catalogue, orders, stock and marketing.
</p>

<p align="center">
  <img alt="PHP 8.1+" src="https://img.shields.io/badge/PHP-8.1%2B-777BB4?logo=php&logoColor=white">
  <img alt="MySQL" src="https://img.shields.io/badge/MySQL-5.7%2B-4479A1?logo=mysql&logoColor=white">
  <img alt="Stripe" src="https://img.shields.io/badge/Stripe-Payments-635BFF?logo=stripe&logoColor=white">
  <img alt="PWA" src="https://img.shields.io/badge/PWA-enabled-5A0FC8?logo=pwa&logoColor=white">
  <img alt="Locales" src="https://img.shields.io/badge/locales-EN%20%C2%B7%20FR%20%C2%B7%20AR%20%C2%B7%20ES-2ea44f">
</p>

> **Status.** This is the source of a working shop, published as-is. It is not a
> packaged product: there is no automated test suite, no CI, no `LICENSE`, and some
> integration points are deliberately left at "configure it in `.env`". Read
> [Status and known gaps](#status-and-known-gaps) before deploying it or handling real
> money.

## What this is

Maroc PC is a server-rendered PHP application with no framework. Pages are PHP files
that compose shared layout partials; the browser gets HTML, a large amount of vanilla
JavaScript, and JSON from `api/`. MySQL holds the catalogue, customers, orders, stock,
loyalty, support tickets and telemetry.

The interesting part is not that it is an e-commerce site — it is how much shop
behaviour is modelled without a framework: a compatibility-aware PC builder, a laptop
recommender with an explicit fallback ranking, loyalty tiers, RMA and warranty flows,
abandoned-cart recovery, scheduled campaigns, visitor blocking, and an admin surface
that spans catalogue, procurement, support and analytics.

## At a glance

| Dimension | In this repository |
| --- | --- |
| Runtime | PHP 8.1+, MySQL 5.7+ / MariaDB 10.2+, Apache with `mod_rewrite` |
| Code | ~72 000 lines of PHP in 210 files; 100 root-level PHP pages/handlers/partials; 39 JS and 27 CSS files |
| Catalogue | 92 products across 13 categories in `assets/js/data.js`; 5 sample laptops |
| Storefront | Home, products, builder, laptop finder, laptop/PC comparison, deals, tools, assembly guide, account |
| Back office | 18 screens, 5 POST/CSV handlers, 3 shared helpers, plus 9 JSON actions under `api/` |
| JSON API | 58 endpoint scripts plus one shared rate-limiter helper |
| Schema | 81 tables, created across setup scripts, migrations and lazy `ensure*` helpers |
| Locales | English (default), French, Arabic (RTL), Spanish |
| Payments | Stripe Payment Intents, optional PayPal server-side verification, cash on delivery |
| Identity | Email/password, email verification, 2FA, Google / Facebook / Discord / Steam sign-in |
| Operations | PWA + service worker, cron workers, SQL-backed rate limits, audit logs |
| Repo size | ~70 MB, mostly product imagery |

## Feature map

### Storefront and discovery

- Home page with a themed hero video (`gpu.mp4` / `gpu-light.mp4`), flash-sale rail,
  featured products, gamer testimonials and structured `Store` JSON-LD.
- Catalogue with category filters, search, sorting, comparison, product detail pages
  and related-product alternatives (`api/alternatives.php`).
- Dark/light themes persisted in `localStorage`; theme-aware hero media swaps on a
  `data-theme` mutation.
- Wishlist, price-drop alerts, restock notifications and price-history charts.
- Product reviews with votes and admin moderation; homepage testimonials are moderated
  separately.
- Community build showcase with publish/view/interact actions
  (`api/community-builds.php`).
- Deals page driven by active `flash_sales` rows, with computed discount percentages.
- Static trust pages: FAQ, shipping info, returns/refunds (which also covers warranty),
  terms of service, privacy policy and cookie policy. Most are rendered through
  `support-page-template.php`.

### Configurator, laptop finder and tools

The builder is a ten-step wizard: CPU, motherboard, GPU, RAM, storage, PSU, case,
cooling, monitor and accessories. Three starting points (balanced, advanced, power)
prefill a build that can then be edited part by part.

Compatibility is checked in two places:

- **Client-side, per selection** (`assets/js/builder.js`): stock, CPU socket vs.
  motherboard socket, motherboard memory type vs. RAM type, the AM5 ⇒ DDR5 rule, and
  platform-specific RAM constraints. Incompatible products are visibly disabled rather
  than silently accepted.
- **Server-side, on validation** (`api/builder-logic.php`): sums `watts_tdp` and
  `peak_watts` from `parts_power_specifications`, returns the recommended minimum PSU,
  and compares GPU length / cooler height / radiator size against
  `chassis_clearance_specs`.

The builder also embeds a PSU calculator, a memory finder and a gaming finder, and can
save builds (`saved_builds`, `user_build_snapshots`) for comparison
(`builds-compare.php`).

The laptop finder filters by usage, portability tier, screen quality, GPU tier, AI/NPU
tier, budget and stock. Exact matches are sorted differently per usage: gaming and
creative rank GPU then screen; business and student rank weight then battery. When
nothing matches, it falls back to a scored "closest alternatives" list (usage +5,
portability +2, screen +2, GPU +2, price distance up to +3).

`tools.php` is a separate cockpit with nine utilities: compatibility checker, CPU/GPU
pairing, future-proofing score, upgrade planner, "is this a good deal", benchmark
database, stock radar, student bundle and a request console.

### Cart, checkout and payments

- Server-side cart resolution: every checkout re-reads product names and prices from
  the database with `FOR UPDATE`, ignores client-supplied prices, merges duplicate
  lines and applies the active flash-sale price when eligible.
- Coupons support percentage, fixed-amount and free-shipping types, with activation
  window, minimum cart value and usage limits (`api/coupon-validate.php`).
- Loyalty points can be redeemed at checkout (100 points = 10 MAD); the balance row is
  locked while the order is created.
- Stock is reserved at order creation (`inventoryReserveQuantities`) and released when
  an order is cancelled or moved out of a reserved status. Status changes are recorded
  in `order_status_history`.
- Stripe Payment Intents (`api/create-payment-intent.php`) and optional PayPal Orders
  v2 verification (`src/Services/paypal-verify.php`).
- Cash on delivery creates a pending order. The checkout UI also lists Bitcoin, Apple
  Pay, Google Pay and NFC/biometric, but these are labels only — no processor is wired
  behind them yet.
- Order confirmation mail, printable pickup ticket with a `PICKUP-XXXX-XXXX`
  verification code, and store-pickup selection on an SVG map of Morocco.
- Customer order detail and cancellation (`api/order-detail.php`, `api/cancel-order.php`),
  plus CSV/R exports for admins.

### Accounts, identity and 2FA

The account area has nine tabs: overview, profile, orders, wishlist, builds, rewards,
warranties/RMAs, security and support. It shows order count, lifetime spend, loyalty
tier, saved builds, open tickets and a profile-completion score.

Authentication includes:

- Email/password with `password_hash()` (bcrypt), email verification and password reset.
- Login lockout: **3 failed attempts → 15-minute lock**, applied per account+IP and
  stored in `Client` (`failed_login_attempts`, `locked_until`) plus the
  `client_login_attempts` table created on demand by `login.php`.
- OAuth 2.0 sign-in with Google, Facebook and Discord, plus Steam OpenID, on the login
  and signup screens. An Xbox/Microsoft callback and login file exist, but the provider
  is not linked from the UI.
- Two-factor authentication by email, WhatsApp (Evolution API), SMS (Textbee) or TOTP
  authenticator. Backup codes are hashed in `two_factor_backup_codes`.
- Device fingerprints (`login_device_fingerprints`) and OAuth account linking/unlinking.
- Soft account deletion with a five-day grace period; open orders are cancelled and
  reserved stock returned before the account is hidden.

### Loyalty and retention

- Tiers: bronze, silver (2 000 points), gold (5 000), platinum (10 000).
- Multipliers: 1×, 1.2×, 1.5×, 2×. Tier benefits include shipping perks and birthday
  bonuses.
- Points ledger (`loyalty_points`), rewards catalogue (`loyalty_rewards_catalog`) and
  redemptions (`loyalty_redemptions`).
- Abandoned carts lock a price and can trigger a 4-hour reminder; the worker only
  considers carts between 4 and 48 hours old and logs each stage in
  `abandoned_cart_communication_logs`.
- Newsletter subscribers, scheduled campaigns and reusable campaign templates.
- Price alerts, restock alerts and wishlist alerts, each with their own table.
- Referral codes, price-match requests, trade-in requests and bank-transfer receipts
  exist in the schema and migration path.

### Back office

`dashboard.php` is the landing page; `adminlogin.php` is the only entry point.

| Area | Screens and handlers (repo root) | JSON actions (`api/admin-*`) |
| --- | --- | --- |
| Catalogue | `admin-products.php`, `admin-product-form.php`, `admin-product-delete.php`, `admin-laptops.php`, `admin-laptop-form.php`, `admin-laptop-delete.php`, `admin-component-csv-template.php`, `admin-laptop-csv-template.php` | — |
| Stock and procurement | `admin-stock.php`, `admin-procurement.php` | `admin-procurement.php` |
| Orders | `admin-orders.php` (CSV/R exports live in `api/`) | `admin-update-order.php`, `admin-bulk-orders.php` |
| Customers | `admin-customers.php`, `admin-customer-detail.php`, `admin-customer-delete.php` | — |
| Marketing | `admin-marketing.php`, `admin-coupons.php` | `admin-flash-sales.php` |
| Content and feedback | `admin-reviews.php`, `admin-feedback.php`, `admin-chatbot-feedback.php`, `admin-requests.php` | `admin-reviews.php` |
| Operations | `admin-analytics.php`, `admin-visitors.php`, `admin-diagnostics.php` | `admin-stats.php`, `admin-visitor-management.php`, `admin-maintenance.php`, `admin-diagnostics.php` |
| Access | `adminlogin.php`, admin users, audit log, login attempts | — |

The three `*-delete.php` files are POST-only handlers; the two CSV templates generate
download examples. All JSON actions except `api/admin-reviews.php` call
`adminRequireJsonAuth()` (HTTP 401); `api/admin-reviews.php` uses the HTML guard
`adminRequireAuth()`, which redirects instead of returning JSON.

Saving a product writes to both MySQL **and** `assets/js/data.js`, so the database and
the storefront's client-side catalogue stay in sync. Price changes go to
`product_price_history`, stock deltas to `inventory_adjustments`, and both are visible
in analytics.

### Support, RMA and after-sales

- After-sales request endpoint with a ticket code, request type (return, refund,
  exchange, warranty, repair, missing, damaged), preferred resolution, product
  condition, serial number and photo (`api/after-sales-request.php`).
- Warranty extensions (`warranty_extensions`), RMA shipping labels, maintenance
  requests and ticket replies.
- Preventative maintenance schedules and hardware diagnostic reports per order.
- Support pages are rendered through a shared template with their own i18n layer.

### Assistant (rule-based, not an LLM)

The "chatbot" is a deterministic pipeline in `src/Chatbot/`:
`RequestParser` normalises accents and tokenises, `IntentClassifier` scores intents,
`ProductRepository` queries products and laptops, and `ResponseGenerator` composes a
reply in English, French or Arabic (Darija is mapped to Arabic).

Intents include greeting, help, stock check, build, compare, budget, order status, RMA
and laptop finder, plus small-talk intents (thanks, laughter, farewell, brb) and a
prank guard for "free product" requests. Conversations and ratings are stored for
review. There is **no external model call and no API key** — the `ai_*` table names and
"AI recommendation" endpoints are naming, not inference.

### Analytics and visitor controls

- Server-side dashboard metrics: revenue, orders, average order value, customers,
  stock value, low-stock and out-of-stock counts, week/month trends.
- `admin-analytics.php` adds sales, payment-method and product breakdowns.
- The shared store shell (`includes/store-head.php`, used by the home, products and cart
  pages) boots `includes/visitor-tracker.php` on every request: it parses the user agent,
  resolves the client IP behind trusted proxies, performs an optional GeoIP lookup,
  records a visitor row and can block by IP, CIDR, country, Tor, VPN, proxy or hosting
  network.
- Visitor logs are archived and pruned on a configurable schedule, and the admin can
  manage blocks from `admin-visitors.php`.

## Architecture

### Request lifecycle

The home, products and cart pages share one shell and are thin compositions of
partials:

```
index.php
  ├─ includes/store-head.php      <head>, CSS, SEO/OG/hreflang, visitor tracking
  ├─ includes/store-header.php    navigation, language switcher, auth state
  ├─ includes/store-sidebar.php   mobile/off-canvas navigation
  ├─ pages content                index-content.php, products-content.php, cart-content.php…
  ├─ includes/store-footer.php    footer and trust links
  └─ includes/store-end.php       toasts, scroll-to-top, deferred scripts
```

`bootstrap.php` loads `.env` (then `.env.local`, which may override), defines constants,
opens the PDO connection lazily through `db()`, and registers a small PSR-4 autoloader
for `MarocPC\Chatbot\`. `config.php` is a back-compatibility shim that requires
`bootstrap.php`.

Admin pages `require_once 'admin-helpers.php'` — the service layer plus the
auto-optimizer — then call `adminRequireAuth()` before rendering. JSON admin actions
call `adminRequireJsonAuth()`; customer endpoints check `$_SESSION['client_id']`
directly.

### Two translation strategies

This is the least obvious design decision in the codebase. Both are live:

1. **Key-based** (`i18n_t()` / `i18n_e()`) for pages written with translation keys.
   Catalogues live in `lang/{en,fr,ar,es}.php`; lookup falls back to English and then to
   an inline fallback string. Locale is chosen from `?lang=`, a cookie, or
   `Accept-Language`, and non-default URLs carry `?lang=xx`.
2. **Phrase-map rewriting** (`i18n_start_page_translation()`) for large legacy pages
   that were written in English HTML. It output-buffers the page and replaces exact
   text nodes and selected attributes (`placeholder`, `title`, `aria-label`, `alt`)
   using per-page maps. Maps shorter than four characters are discarded to avoid
   substring damage, and `<script>`/`<style>` bodies are skipped.

That is why `includes/i18n.php` is ~555 KB: it carries the catalogues, the page maps,
the admin maps and the tag/attribute translation helpers.

### Where product data actually lives

`assets/js/data.js` is the storefront's original single source of truth: 92 products
with specs, prices, badges and image paths. Importing it (`scripts/data.php`) creates
the `products` table. After that, the admin becomes the writer: product saves update
MySQL and re-export the catalogue back to `data.js` via
`adminExportProductsToDataJs()`. Laptops have a parallel file,
`assets/js/laptop_data.js`, written by `admin-laptop-form.php`.

If you change product data by hand, change both sides or re-run the export. If you
change it in the admin, do not be surprised that a tracked JS file changes too — that
is the sync mechanism, not a bug.

### Repository map

| Path | Contents |
| --- | --- |
| `index.php`, `products.php`, `cart.php`, `checkout.php`, `builder.php`, `laptop-finder.php` | Storefront entry points |
| `includes/` | Layout partials, i18n engine, visitor tracker |
| `src/bootstrap/` | Env loading, constants, `db()`, CSRF, session helpers |
| `src/Services/` | Checkout, inventory, mail, PayPal, 2FA, price alerts, admin helpers |
| `src/Chatbot/` | Intent classifier, request parser, product repository, response generator |
| `api/` | JSON endpoints, exports and cron workers |
| `admin-*.php`, `dashboard.php`, `adminlogin.php`, `tools.php` | Back office |
| `lang/` | Four translation catalogues |
| `scripts/` | Schema, migrations, seeders, admin CLI, i18n audits |
| `assets/css/`, `assets/js/` | 27 stylesheets and 39 scripts, including `data.js` |
| `Images/products/`, `Images/profile/`, `Images/icons/` | Product, avatar and PWA icons |
| `lib/PHPMailer/` | Vendored PHPMailer |
| `components/` | Two home-page blocks |

## Data model

The schema is created by `scripts/setup-tables.php` (52 tables), then extended by
`setup-laptops-table.php`, `scripts/migrate-new-md-features.php` and idempotent
`ensure*` helpers in the admin and after-sales code. Counting every `CREATE TABLE`
across the repository gives **81 distinct tables**.

| Group | Representative tables |
| --- | --- |
| Identity | `Client`, `email_verifications`, `password_resets`, `login_device_fingerprints`, `two_factor_backup_codes`, `admin_users`, `admin_login_attempts` |
| Catalogue | `products`, `laptops`, `product_compatibility`, `parts_power_specifications`, `chassis_clearance_specs`, `component_compatibility_rules`, `product_price_history`, `price_history`, `laptop_price_history` |
| Commerce | `orders`, `order_items`, `order_status_history`, `abandoned_carts`, `abandoned_cart_communication_logs`, `coupon_codes`, `flash_sales` |
| Builds | `saved_builds`, `user_build_snapshots`, `custom_build_presets`, `community_builds`, `community_build_showcases`, `community_build_interactions`, `build_progress_events` |
| Loyalty | `loyalty_points`, `loyalty_rewards_catalog`, `loyalty_redemptions`, `referral_codes` |
| Support | `maintenance_requests`, `maintenance_ticket_replies`, `rma_shipping_labels`, `warranty_extensions`, `preventative_maintenance_schedules`, `hardware_diagnostic_reports`, `after_sales_requests` |
| Marketing | `newsletter_subscribers`, `newsletter_campaign_templates`, `scheduled_emails`, `marketing_seasonal_campaigns`, `homepage_gamer_reviews` |
| Engagement | `product_reviews`, `review_votes`, `wishlist`, `product_wishlist_alerts`, `restock_notifications`, `price_match_requests`, `trade_in_requests` |
| Procurement | `vendor_suppliers`, `purchase_orders`, `inventory_adjustments`, `stock_restock_plans` |
| Telemetry | `visitor_logs`, `visitor_log_archive`, `visitor_network_blocks`, `ip_blocks`, `country_blocks`, `geoip_cache`, `user_search_intent_logs` |
| Security | `security_audit_logs`, `admin_activity`, `api_rate_limits`, `admin_settings` |

Conventions worth knowing:

- `Client.id_client` is the customer primary key; `products.id` and `laptops.id` are
  independent sequences. `orders.client_id` references `Client`.
- Money is `DECIMAL(10,2)`; statuses are `ENUM`s; product specs are JSON columns or a
  JSON `specs` column, depending on the table.
- `order_items` snapshots `name_at_time` and `price_at_time` and uses `ON DELETE SET NULL`
  for `product_id`, so deleting a product does not erase historical order lines. Most
  other product references (reviews, price history, wishlist, compatibility specs)
  cascade.
- Several "ensure column/table" helpers run on page load. They are safe to run
  repeatedly, but they mean the live schema can drift from the setup script. Use the
  setup script on a fresh database and treat the helpers as compatibility shims.

## HTTP API

All endpoints live in `api/`, return JSON unless they are exports, and use PHP sessions
for authentication. There is no token API and no versioning.

**Auth levels**

| Level | How it is enforced |
| --- | --- |
| Public | No session check (catalogue, coupons, reviews, subscription) |
| Customer | `$_SESSION['client_id']`, otherwise HTTP 401 |
| Admin (HTML) | `adminRequireAuth()` redirects to `adminlogin.php` |
| Admin (JSON) | `adminRequireJsonAuth()` returns HTTP 401 JSON |
| Cron | CLI, or admin session for `process-email-queue.php` |

Grouped summary:

| Group | Endpoints |
| --- | --- |
| Catalogue and discovery | `products-stock`, `alternatives`, `recommendations`, `price-history`, `search-log`, `flash-sales`, `reviews`, `community-builds`, `custom-builds`, `builder-logic`, `builder-save`, `wishlist`, `restock-notify`, `price-alerts`, `laptop-price-alerts`, `coupon-validate` |
| Cart and orders | `cart-sync`, `lock-cart-price`, `create-payment-intent`, `place-order`, `orders`, `order-detail`, `cancel-order`, `loyalty` |
| Account and identity | `user`, `update-profile`, `upload-profile-picture`, `delete-account`, `two-factor`, `unlink-oauth`, `end-session`, `visitor-fingerprint` |
| Support and after-sales | `submit-feedback`, `feature-requests`, `after-sales-request`, `warranty`, `maintenance`, `maintenance-schedules` |
| Assistant | `chatbot`, `save-chatbot-feedback` |
| Marketing | `subscribe`, `marketing` |
| Admin | `admin-bulk-orders`, `admin-diagnostics`, `admin-flash-sales`, `admin-maintenance`, `admin-procurement`, `admin-reviews`, `admin-stats`, `admin-update-order`, `admin-visitor-management` |
| Exports | `export-orders`, `export-customers`, `export-products` (CSV or R) |
| Operations | `process-email-queue`, `process-abandoned-carts`, `db-diagnostic`, `i18n-diagnostic`, plus the `rate-limiter.php` helper |

<details>
<summary>Full endpoint list (58 scripts)</summary>

```
admin-bulk-orders.php          after-sales-request.php        alternatives.php
builder-logic.php              builder-save.php               cancel-order.php
cart-sync.php                  chatbot.php                    community-builds.php
coupon-validate.php            create-payment-intent.php      custom-builds.php
db-diagnostic.php              delete-account.php             end-session.php
export-customers.php           export-orders.php              export-products.php
feature-requests.php           flash-sales.php                i18n-diagnostic.php
laptop-price-alerts.php        lock-cart-price.php            loyalty.php
maintenance.php                maintenance-schedules.php      marketing.php
order-detail.php               orders.php                     place-order.php
price-alerts.php               price-history.php              process-abandoned-carts.php
process-email-queue.php        products-stock.php             recommendations.php
restock-notify.php             reviews.php                    save-chatbot-feedback.php
search-log.php                 submit-feedback.php            subscribe.php
two-factor.php                 unlink-oauth.php               update-profile.php
upload-profile-picture.php     user.php                       visitor-fingerprint.php
warranty.php                   wishlist.php                   admin-diagnostics.php
admin-flash-sales.php          admin-maintenance.php          admin-procurement.php
admin-reviews.php              admin-stats.php                admin-update-order.php
admin-visitor-management.php
```

</details>

## Getting started

### Requirements

- PHP **8.1+** with `pdo_mysql`, `mbstring`, `openssl`, `curl`, `fileinfo` and `json`.
- MySQL 5.7+ or MariaDB 10.2+ (`utf8mb4`).
- Composer.
- Apache 2.4 with `mod_rewrite` for the included `.htaccess`, or PHP's built-in server
  for local work.
- Writable paths: `assets/js/`, `Images/products/`, `Images/profile/`, and the repo
  root if you use `webhook.php`.

### 1. Clone and install

```bash
git clone https://github.com/Azizfois2/Maroc_PC.git
cd Maroc_PC
composer install
```

`vendor/` is gitignored, so `composer install` is required even though the project does
not use a framework.

### 2. Create the environment file

```bash
cp env.example .env        # Windows: copy env.example .env
```

Set `APP_URL` and `DB_*` first. Everything else is optional for local browsing; a
feature stays disabled until its keys are present. `APP_URL` must include the
subdirectory and end with `/` (the code adds the slash if you forget).

`env.example` only ships the common keys. Stripe, Facebook, Discord, Xbox, the proxy
list and the CSRF key are read from `.env` too — copy the names from the table in
[Configuration](#configuration) if you need them.

### 3. Create the database — order matters

```sql
CREATE DATABASE maroc_pc CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

Run these in this order:

```bash
php scripts/data.php                  # creates `products` and imports 92 rows from assets/js/data.js
php scripts/setup-tables.php          # creates the 52 core tables (FKs reference `products`)
php setup-laptops-table.php           # creates `laptops` and seeds 5 sample machines
php scripts/migrate-new-md-features.php   # price match, community builds, trade-ins, bank transfers, referrals
```

`setup-tables.php` creates foreign keys to `products`, so it fails on an empty schema if
you run it before `scripts/data.php`. This is the single most common setup mistake.

Optional demo data:

```bash
php scripts/seed-data.php             # loyalty rewards and build presets
php scripts/seed-motherboards.php     # motherboard rows derived from data.js
php scripts/seed-flash-sales.php      # active flash sales
php scripts/seed-community-builds.php # showcase entries
php scripts/seed-price-history.php    # 90 days of price history
```

### 4. Create an administrator

```bash
php scripts/create-admin.php admin@example.com "a-password-of-10-chars-or-more" "Administrator"
```

Passwords are bcrypt-hashed and must be at least 10 characters. The admin sign-in page
is **`/adminlogin.php`**; `dashboard.php` requires an admin session.

### 5. Run it

With Apache/XAMPP, put the project in the web root (for example `htdocs/Test`) and set:

```dotenv
APP_URL=http://localhost/Test/
```

Then open <http://localhost/Test/>.

Or use the built-in server:

```bash
php -S localhost:8000
```

with `APP_URL=http://localhost:8000/`. The built-in server ignores `.htaccess`, so the
legacy `.html` redirects and the 403/404 documents will not apply — every page is still
reachable through its `.php` URL.

### 6. Schedule the workers

Two scripts are written to run from cron:

```cron
# every 5 minutes: send due campaigns from scheduled_emails
*/5 * * * * php /var/www/marocpc/api/process-email-queue.php >> /var/log/marocpc-mail.log 2>&1

# every 15 minutes: abandoned-cart reminders
*/15 * * * * php /var/www/marocpc/api/process-abandoned-carts.php >> /var/log/marocpc-carts.log 2>&1
```

`process-email-queue.php` allows CLI unconditionally and requires an admin session over
the web. `process-abandoned-carts.php` has **no auth guard** — keep it off the public
web root or protect it at the server level if you run it over HTTP.

## Configuration

Configuration is read from `.env` by `bootstrap.php`, then overridden by `.env.local`
when present. Constants are defined in `src/bootstrap/application.php`.

| Variable | Purpose |
| --- | --- |
| `APP_URL` | Public base URL, including subdirectory and trailing slash |
| `DEV_MODE` | `true` shows PHP errors and enables local fallbacks; `false` in production |
| `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS` | MySQL connection |
| `DB_CHARSET` | Connection charset, default `utf8mb4` |
| `CSRF_TOKEN_NAME` | Session key for the CSRF token, default `marocpc_csrf` |
| `TRUSTED_PROXY_IPS` | Comma-separated proxies allowed to supply `X-Forwarded-For` / `CF-Connecting-IP` |
| `MAIL_FROM`, `SMTP_HOST`, `SMTP_PORT`, `SMTP_USER`, `SMTP_PASS`, `SMTP_ENCRYPTION`, `SMTP_FROM`, `SMTP_FROM_NAME` | Outgoing mail and templates |
| `EVOLUTION_API_URL`, `EVOLUTION_API_KEY`, `EVOLUTION_INSTANCE_NAME` | WhatsApp delivery for 2FA |
| `TEXTBEE_API_KEY`, `TEXTBEE_DEVICE_ID` | SMS delivery for 2FA |
| `GOOGLE_CLIENT_ID`, `GOOGLE_CLIENT_SECRET` | Google OAuth |
| `FB_APP_ID`, `FB_APP_SECRET` | Facebook OAuth |
| `FB_WEBHOOK_VERIFY_TOKEN` | Facebook webhook challenge token (defaults to `my_oauth_123` when unset) |
| `DISCORD_CLIENT_ID`, `DISCORD_CLIENT_SECRET` | Discord OAuth |
| `XBOX_CLIENT_ID`, `XBOX_CLIENT_SECRET` | Xbox/Microsoft OAuth (callback exists; not linked in the UI) |
| `STRIPE_PUBLISHABLE_KEY`, `STRIPE_SECRET_KEY` | Stripe card payments |
| `PAYPAL_ENV`, `PAYPAL_CLIENT_ID`, `PAYPAL_CLIENT_SECRET` | Optional PayPal verification (`sandbox` or `live`) |
| `TURNSTILE_SITE_KEY`, `TURNSTILE_SECRET_KEY` | Cloudflare Turnstile on signup/login |

Steam sign-in uses OpenID and works without a key. `STEAM_API_KEY` is referenced by
`steam-callback.php` behind `defined()`, but it is not declared anywhere, so Steam
profile enrichment is skipped and only the Steam ID is stored.

In `DEV_MODE` without WhatsApp/SMS credentials, 2FA codes are written to the PHP error
log instead of being sent.

## Localization

- Four catalogues: `lang/fr.php` (2 070 keys), `lang/ar.php` (1 993), `lang/es.php`
  (1 735), `lang/en.php` (1 626).
- English is the default and the fallback for missing keys. Arabic sets `dir="rtl"`.
- Money formatting is locale-aware (`i18n_format_money`); prices are in Moroccan dirham
  (MAD, displayed as `DH`).
- Language switchers rewrite the current URL with `?lang=xx`; the choice is stored in a
  one-year cookie (`marocpc_lang`).
- `hreflang` alternates and `x-default` are emitted in `store-head.php`.
- Keys go through `i18n_t()` / `i18n_e()`; the legacy phrase-map path is described in
  [Architecture](#architecture).

Two audits help keep catalogues honest:

```bash
php scripts/i18n-audit.php             # admin phrase coverage
php scripts/i18n-catalog-audit.php     # storefront catalogue coverage
```

Both can write a report file and accept `--quiet`. Missing keys are also reported at
runtime (without breaking the page) by `i18n_report_missing_catalog_key()`.

## Security model

### What is implemented

- **SQL:** PDO with `ATTR_EMULATE_PREPARES => false`; query parameters go through
  prepared statements, and the few interpolated ids are cast with `(int)` first.
- **Passwords:** `password_hash()` / `password_verify()` (bcrypt). Admin and customer
  lockouts are separate and both use 3 attempts / 15 minutes.
- **Sessions:** `HttpOnly`, `SameSite=Lax`, `Secure` when HTTPS is detected;
  `session_regenerate_id(true)` on login; separate admin session lifetime.
- **CSRF:** per-session token, `hash_equals()` verification, hidden fields in forms and
  an `X-CSRF-Token` header for AJAX (profile upload, account actions).
- **XSS:** output escaped through `h()` / `adminH()` / `htmlspecialchars()`; i18n output
  is escaped by `i18n_e()`.
- **Uploads:** MIME sniffing plus extension allow-lists and size caps (4 MB products,
  3 MB avatars); filenames are generated server-side.
- **Abuse controls:** optional Turnstile, per-IP/per-endpoint SQL rate limiting
  (60 requests / 60 seconds), login and admin lockouts, visitor block lists.
- **Money:** cart lines and prices are resolved server-side; client-supplied totals are
  ignored; coupon and points rules are re-evaluated inside the order transaction.
- **Auditing:** `admin_activity` and `security_audit_logs` (admin id, action, target
  table/id, IP, user agent), order status history and inventory adjustments.
- **Deletion:** soft delete with a five-day grace period and a restore path; order
  cancellation releases reserved stock.
- **HTTP surface:** `.htaccess` denies `.env`, all dotfiles, `composer.*`, `env.example`
  and `*.sql`; custom 403/404 pages.

### Before exposing an installation

1. Set `DEV_MODE=false` and serve over HTTPS.
2. Replace every example credential in `.env`; never commit `.env`.
3. Keep the `.htaccess` deny rules; add HSTS, CSP, `X-Frame-Options` and
   `X-Content-Type-Options` at the server or in `store-head.php` — the application does
   not emit them itself.
4. Remove or protect diagnostic and test entry points (`api/db-diagnostic.php`,
   `api/i18n-diagnostic.php`, `turnstile-debug.php`, `test_cookie.php`, `test.htm`).
5. Give `webhook.php` a real verify token and move its log out of the web root.
6. Confirm the Stripe account supports MAD and remove
   `\Stripe\Stripe::setVerifySslCerts(false)` from `api/create-payment-intent.php`.
7. Reconcile the two checkout totals before taking money (see below).
8. Rotate OAuth, Stripe, SMTP, Evolution and Textbee keys if they were ever shared.

## Deployment notes

- **Apache path assumption:** `.htaccess` hardcodes `/Test/` in the legacy `.html`
  redirects and in the 403/404 `ErrorDocument` paths. Either deploy under `/Test/` or
  edit those rules. On a domain root, the redirects will point at the wrong place.
- **Document root:** point it at the repository (or a subdirectory) and keep `.env`
  outside version control. `composer install` must run on the server, or `vendor/` must
  be deployed.
- **Writable paths:** product saves rewrite `assets/js/data.js` and
  `assets/js/laptop_data.js`; uploads go to `Images/products/` and `Images/profile/`.
  Grant the web user write access to those, not to the whole tree.
- **Backups:** back up the database, `Images/`, `assets/js/data.js` and
  `assets/js/laptop_data.js` together. Restoring only the database can leave the
  exported `data.js` catalogue out of sync with it; restoring only the files loses
  orders and stock.
- **PWA cache:** `sw.js` uses a versioned cache (`maroc-pc-v4`) with network-first
  documents and stale-while-revalidate assets. Bump the version when shipping CSS/JS
  that must invalidate immediately.
- **OPcache:** recommended. `admin-auto-optimizer.php` adds query caching and
  pagination helpers, and `admin-turbo-boost.php` precompiles files and enables gzip
  buffering, but neither replaces a properly configured opcache.
- **Cron:** see [step 6](#6-schedule-the-workers). Log rotation is your responsibility.

## Status and known gaps

This section is intentionally blunt; it is cheaper to read it here than to discover it
in production.

**Repository hygiene**

- No `LICENSE` file. The source is published for viewing and portfolio purposes; all
  rights are reserved by the authors. Add a licence before accepting contributions or
  reuse.
- No automated tests and no CI. The closest things to health checks are
  `scripts/i18n-audit.php`, `scripts/i18n-catalog-audit.php`,
  `api/db-diagnostic.php` and `api/i18n-diagnostic.php`.
- Legacy and scratch files are committed: `index.html` (a 226 KB pre-PHP homepage),
  `index2.html`, `login.html`, `signup.html`, `products.html`, `cart.html`, `test.htm`,
  `test-pdf-ticket.html`, `test_cookie.php`, `app.js` (a `hello world` alert),
  `style.css`, and `__brave_tmp/` (a Brave extension cache).
- Uploaded test avatars from account-15 (`Images/profile/profile-15-*.png` and `.jpg`)
  are committed next to the default avatar.
- The repo is ~70 MB, mostly images, including saved web pages with spaces and accents
  in their filenames. Expect awkward clone times and path-length issues on Windows.
- `scripts/migrate-new-md-features.php` and the `ensure*` helpers are compatibility
  shims, not a migration framework. There is no migration ledger.

**Behaviour to verify before selling**

- **Checkout totals disagree.** `api/create-payment-intent.php` charges subtotal -
  discounts + shipping + 8.25% "tax", while `api/place-order.php` stores subtotal -
  discounts with no shipping or tax. The Stripe charge and the order record can
  therefore differ. Pick one calculation and use it in both places.
- **Payment status is not verified server-side.** Card orders are marked `paid` from a
  client-supplied `transaction_id` without retrieving the PaymentIntent. PayPal
  verification only checks the order status (`APPROVED` / `COMPLETED`), not the amount,
  and is skipped in `DEV_MODE` when PayPal credentials are missing. Both can produce an
  order that says "paid" with no matching capture.
- Shipping rules are duplicated: the browser prices 100 MAD standard / 200 MAD express /
  400 MAD overnight / free pickup, with a 1 000 MAD free-shipping threshold;
  `place-order.php` only uses the method to estimate delivery, while
  `create-payment-intent.php` adds a fee again. Confirm the intended rule.
- `component_compatibility_rules` is loaded by the builder endpoint but rule matching is
  a stub. Real compatibility comes from the client-side checks plus wattage/clearance.
- The checkout lists Bitcoin, Apple Pay, Google Pay and NFC/biometric, but only card,
  PayPal and COD have defined flows. The others create a pending order.
- The "pickup ticket PDF" is a server-rendered printable HTML document returned as JSON
  and opened in a print window; the class probes for TCPDF, which is not installed.
- The `ai_*` tables, `api/recommendations.php` and the "AI curve" label are heuristic or
  logging only. No model, no inference, no external API.

**Operational risks**

- `api/process-abandoned-carts.php` is unauthenticated over HTTP.
- `webhook.php` contains a hardcoded verify token and appends to `webhook_log.txt` in the
  web root. `facebook-webhook.php` is better but still falls back to the example token
  `my_oauth_123` when `FB_WEBHOOK_VERIFY_TOKEN` is unset.
- `api/create-payment-intent.php` disables Stripe TLS verification.
- No security headers are emitted by the application.
- `DEV_MODE` defaults to `true` in `env.example`; a missed `.env` will show errors.
- Admin "auto-optimizer" and "turbo-boost" are experimental performance helpers; they
  are loaded or available, but they are not required and their assumptions (for example
  MySQL query-cache settings) do not hold on every server.

## Authors

Built by **Abdelaziz Al Harbi** ([@Azizfois2](https://github.com/Azizfois2)) and
**Youssef Aziz** ([@azizyoussef1907-max](https://github.com/azizyoussef1907-max)).

## License

No license file is included. The source is published for viewing and portfolio
purposes; all rights are reserved by the authors. Add a `LICENSE` file if you intend to
allow reuse, modification or contribution.
