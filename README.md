# SkyReserve

SkyReserve is a single-airline ticketing and operations application built with **plain PHP, MySQL, HTML, CSS, and vanilla JavaScript**, using MVC. Its 12 core modules are complete. No frontend framework, Composer package, or build step is required.

## Functionality

| Customers | Administrators |
| --- | --- |
| Registration, login, logout, profile | Protected admin login and dashboard |
| Flight search by route and travel date | Airport, aircraft, seat, and flight management |
| Passenger details and booking references | Manual payment verification or rejection |
| Seat selection and manual payment submission | Cancellation approval or rejection |
| Protected receipts and tickets; print/HTML download | Booking, flight, payment, cancellation, and passenger reports |
| My Bookings and cancellation requests | Metrics and verified revenue grouped by currency |

Payments are manual. Tickets require a confirmed booking, verified payment, and valid passenger/seat assignments. Cancellation approval releases seats and voids tickets; it does not automatically refund payments. Automatic refunds, online payment processing, check-in, and boarding passes are outside the implemented scope.

## Requirements

- PHP **8.1+** with `pdo_mysql`, `mbstring`, `fileinfo`, and GD supporting JPEG, PNG, and WEBP.
- MySQL **8.0.16+**, InnoDB, and enforced CHECK constraints.
- Apache **2.4** with `mod_rewrite`, or PHP's development server locally.
- For tests: PHP `curl`; Node **22+** and Google Chrome for browser checks only.

## Project structure

```text
app/Controllers/           HTTP actions and access guards
app/Models/                Prepared queries and transactional workflows
app/Views/                 Customer/admin pages and shared layouts
app/Core/                  Database, environment, auth, session, receipt helpers
config/                    Environment-backed database/payment configuration
database/schema.sql        Current eleven-table initial schema
database/migrations/       SQL upgrades for existing installations
public/                    Web document root, CSS, JavaScript, front controller
routes/web.php             Explicit GET/POST routes
scripts/                   Setup, inventory seeding, migrations, test runners
storage/payment_receipts/  Private receipt images
bootstrap.php              Autoloading, environment loading, UTC configuration
.env.example               Configuration template; .env is ignored by Git
```

The database contains `users`, `airports`, `aircraft`, `seats`, `flights`, `bookings`, `passengers`, `booking_seats`, `payments`, `tickets`, and `cancellations`. A booking belongs to a customer and flight; passengers, payment submissions, and cancellation history belong to that booking. Seat allocations link its passenger to the flight's aircraft seat; tickets reference allocations. Composite foreign keys protect these relationships, and unique constraints prevent duplicate active seat/payment/cancellation records.

## Local setup

Run commands from the project root in Laragon's terminal, where PHP and MySQL are on PATH.

1. Start MySQL and configure the environment:

   ```powershell
   Copy-Item .env.example .env
   ```

   Set `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, and `DB_PASSWORD` in `.env`. Do not overwrite an existing configured `.env`. Payment instruction settings also live there; defaults are clearly labelled coursework samples. Server/process variables take precedence. The loader supports literal `KEY=value`, optional wrapping quotes, and standalone `#` comments; interpolation and trailing comments are unsupported.

2. For a **new empty database**:

   ```powershell
   mysql -u root -p -e "CREATE DATABASE airplane_ticketing CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
   mysql -u root -p airplane_ticketing -e "source database/schema.sql"
   php scripts/check_database.php
   ```

   Match the database name to `.env`; supply the configured MySQL host/port if different. Enter the database password when prompted. Skip creation/import for an existing installation: the initial schema is not a migration and intentionally fails on existing tables.

3. Seed the optional inventory:

   ```powershell
   php scripts/seed_inventory.php
   ```

4. Start the local server:

   ```powershell
   php -d upload_max_filesize=5M -d post_max_size=8M -d memory_limit=128M -S 127.0.0.1:8000 -t public public/router.php
   ```

   Visit **http://127.0.0.1:8000/**. Restart the server after editing configuration. Add flights through the admin area or use the optional sample flight seed below.

### Inventory seed

The CLI-only seed reuses the shared PDO connection and inserts only airports, aircraft, and seats. Airport codes and sample aircraft registrations identify existing records. A transaction, a seed-run lock, aircraft row locks, and unique indexes make repeated/concurrent runs safe. Matching data and existing capacities/statuses are preserved; conflicting models/classes or insufficient capacity abort the entire seed with no partial inserts.

Airports: **LHE, KHI, ISB, DXB, DOH, JED**, with names, cities, countries, and IANA timezones.

| Aircraft | Sample registration | Capacity | Business | Economy |
| --- | --- | ---: | --- | --- |
| Airbus A320 | AP-SRA | 150 | Rows 1–3: A, C, D, F (12) | Rows 4–26: A–F (138) |
| Boeing 737-800 | AP-SRB | 160 | Rows 1–4: A, C, D, F (16) | Rows 5–28: A–F (144) |
| Airbus A321 | AP-SRC | 200 | Rows 1–5: A, C, D, F (20) | Rows 6–35: A–F (180) |

A fresh run inserts **6 airports, 3 aircraft, and 510 seats**. Existing records reduce those counts; a second unchanged run inserts zero. Registrations and layouts are sample airline inventory. No flights, users, bookings, payments, or tickets are seeded.

### Sample Scheduled flights

After inventory seeding, run:

```powershell
php scripts/seed_flights.php
```

This separately inserts 10 Scheduled sample flights, SR901–SR910, starting tomorrow in UTC: return pairs for LHE–KHI, ISB–KHI, LHE–DXB, KHI–DOH, and ISB–JED. Aircraft are AP-SRA/AP-SRB/AP-SRC, with sensible flight durations and turnaround gaps; fares are PKR 18,500–95,000. Only the `flights` table is populated. Existing sample flight numbers, dates, fares, and statuses are preserved, so repeated runs add no duplicates or overwrite operational records. Conflicting references abort the transaction. Rerunning later does not reschedule departed sample flights.

### Administrator account

There is no default administrator password or public admin registration. Provision an account through the CLI:

```powershell
$adminPassword = Read-Host 'Admin password' -AsSecureString
$env:ADMIN_PASSWORD = [System.Net.NetworkCredential]::new('', $adminPassword).Password
try {
    php scripts/create_admin.php "Administrator" "admin@example.com"
} finally {
    Remove-Item Env:\ADMIN_PASSWORD
    $adminPassword = $null
}
```

Passwords require at least eight characters and at most 72 bytes. Existing emails are refused. Sign in at `/admin/login`; customer registration always creates a customer.

### Existing database upgrades

Back up the database, then run only the upgrades applicable to the installation, in order:

```powershell
php scripts/migrate_module3.php
php scripts/migrate_module4.php
php scripts/migrate_module6.php
php scripts/migrate_module8.php
php scripts/migrate_module11.php
```

These repeat-safe scripts check existing structure before applying changes. Module 11 refuses legacy pending cancellation records whose previous booking status is unknown; resolve those manually first. MySQL DDL is not transactional. Fresh installations using the current schema do not need these upgrades.

## Routes and workflows

| Area | Main routes |
| --- | --- |
| Public | `/`, `/register`, `/login`, `/flights`, `/flights/show?id=…` |
| Customer | `/profile`, `/bookings`, `/bookings/create?flight_id=…`, `/bookings/show?id=…` |
| Booking actions | `/bookings/seats`, `/bookings/payment`, `/bookings/cancel` with `booking_id` |
| Customer documents | `/payments/receipt?id=…`, `/tickets/show?id=…`, `/tickets/download?id=…` |
| Admin operations | `/admin`, `/admin/airports`, `/admin/aircraft`, `/admin/seats`, `/admin/flights`, `/admin/payments`, `/admin/cancellations` |
| Admin reports | `/admin/reports` and `/bookings`, `/flights`, `/payments`, `/cancellations`, `/passengers` beneath it |
| Admin documents | `/admin/payments/receipt`, `/admin/tickets/show`, `/admin/tickets/download` with `id` |

See `routes/web.php` for every action. Mutations require POST and CSRF tokens; logout is POST only. HEAD uses GET authorization without a response body.

Booking progression: **Pending Payment → Payment Submitted → Confirmed** after administrator verification. Rejection returns it to Pending Payment. Cancellation Requested holds the existing seat/ticket state until review; approval cancels and releases/voids it, while rejection restores the saved previous status. Confirmed bookings ignore former payment deadlines. Transactions and unique constraints guard concurrent submissions and issuance.

All application dates/times and date filters use **UTC**. Airport timezones are metadata; displayed times are not converted. Reports use inclusive date boundaries and 50-row pagination. Booking/payment/cancellation reports filter creation/submission/request dates; flight/passenger reports filter departure dates. Verified revenue is gross verified payment value grouped by currency and includes cancelled bookings until payments are manually handled. Ticket details use current related records, not immutable snapshots. Downloads are self-contained printable HTML; the browser can print or save as PDF.

## Security and hosting

- Serve **only `public/`**. For Apache, set `DocumentRoot` and its matching `<Directory>` to the public path with `AllowOverride All`, `Require all granted`, and `mod_rewrite`. Root/storage access is denied; hidden public files are blocked.
- Use HTTPS. Ensure PHP receives the correct `HTTPS` setting, including behind a trusted reverse proxy, so session cookies use `Secure`. Cookies also use HttpOnly/SameSite=Lax; login regenerates session IDs and rotates CSRF tokens.
- Keep `.env`, database credentials, logs, and exports private. Use deployment-specific credentials and writable private session/receipt storage. Errors go to server logs; users receive safe messages. The development server is for local use.
- Receipts are optional JPEG/PNG/WEBP images up to **5 MB**, checked with `finfo`, decoded and re-encoded, and stored under random filenames outside the web root. Corrupted/script-bearing uploads are rejected; failed database saves delete new files. Viewing requires ownership or an authorized admin route.
- Role/ownership checks, prepared statements, escaped HTML, transactional status checks, and database constraints protect customer/admin actions. No automatic refunds or automatic ticket issuance occurs.

The UI uses the SkyReserve navy/orange/teal theme, responsive layouts, SVG sidebar icons, sticky navigation, a fixed desktop admin sidebar, native accessible filters, and role-aware footer links. Search/report filters submit valid changes automatically after a short debounce. Required search inputs must be complete before automatic submission. Report fields fit in one desktop row and reflow on smaller screens; clearing fields removes their filters. Reports have no Apply/Reset controls when JavaScript is enabled; an Update report button provides a fallback when it is disabled. Table actions and report shortcuts use consistent buttons without underlines. Enhanced dropdowns open below their field and scroll within the available space; older browsers retain operating-system native picker placement. Short screens can scroll the sidebar internally; mobile navigation reflows into the page. Reduced-motion and print styles are provided.

Shared Back/Cancel buttons link to explicit parent pages. Plain JavaScript provides SweetAlert-style confirmation dialogs for saves, submissions, destructive actions, and leaving edited forms. Dialogs support keyboard focus and Escape; older browsers use native confirmation. Reloading or closing a changed form uses the browser's unsaved-changes prompt. JavaScript confirmations supplement server-side validation, permissions, CSRF, and duplicate protection; they do not replace them.

## Tests

Use a **local development database**, with the server above running and the same `.env` for server and CLI. Test fixtures are removed on completion; forced termination can leave records behind. Do not run tests against production data.

```powershell
$tests = @('schema', 'auth', 'inventory', 'flights', 'search', 'bookings', 'seat_selection', 'payments', 'payment_reviews', 'tickets', 'cancellations', 'reports')
foreach ($test in $tests) {
    php "scripts/test_$test.php" http://127.0.0.1:8000
    if ($LASTEXITCODE -ne 0) { throw "Failed: $test" }
}
node scripts/test_ui.mjs http://127.0.0.1:8000
if ($LASTEXITCODE -ne 0) { throw 'Browser checks failed' }
```

The browser runner accepts a third argument for `php.exe`; set `SKYRESERVE_TEST_CHROME` to override Chrome's executable path. Screenshots are saved in the temporary directory printed by the runner. Integration coverage includes authentication, CRUD, search, booking relationships, concurrent seat/payment/ticket/cancellation operations, uploads and rollback cleanup, authorization/CSRF, reports, responsive layouts, form interactions, and ticket printing.

Before deployment, manually verify Apache/TLS configuration, permissions, real payment instructions, cross-browser printing, and keyboard/screen-reader accessibility. Automated local checks supplement these reviews.
