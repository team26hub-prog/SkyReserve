# Airplane Ticketing System — Modules 1–4

Plain PHP MVC application for a single airline. Module 1 provides the foundation and database; Module 2 adds authentication; Module 3 adds admin airport, aircraft, and aircraft-seat management; Module 4 adds admin flight management. Customer flight search, bookings, customer seat selection, payments, tickets, and cancellations are not implemented.

## Requirements

- PHP 8.1+ with `pdo_mysql` and `mbstring` (`curl` is needed only for HTTP integration tests).
- MySQL 8.0.16+ (enforced CHECK constraints), using InnoDB.
- Apache 2.4 with `mod_rewrite` for Laragon hosting, or PHP's built-in server for development.
- No Composer, framework, or JavaScript build tools are required.

## Structure

```text
app/
  Controllers/                         Home, auth, profile, admin, airport/aircraft/seat/flight CRUD
  Core/Controller.php                   Rendering, redirects, role/CSRF guards
  Core/Auth.php                         Current-user lookup and sign-in/out
  Core/Session.php                      Session cookies, CSRF, flash messages
  Core/Database.php                     Shared, lazy PDO connection
  Models/Model.php                      Shared model base
  Models/User.php                       Prepared user queries and registration
  Models/Airport.php                    Airport persistence
  Models/Aircraft.php                   Aircraft persistence and capacity locking
  Models/Seat.php                       Aircraft-scoped seat persistence
  Models/Flight.php                     Flight persistence and joined details
  Views/                               Home, auth, customer, admin, errors
  Views/layouts/base.php                Shared minimal layout
config/                                 Database settings read from environment
.env.example                            Safe template for local settings
.env                                    Local secrets (ignored by Git)
database/schema.sql                     Current eleven-table schema (Modules 1–4)
database/migrations/                    Upgrade SQL for existing installations
public/                                 Web document root and responsive CSS
routes/web.php                          Explicit route definitions
scripts/check_database.php              Read-only connection/table check
scripts/test_schema.php                 Transactional constraint checks
scripts/create_admin.php                CLI-only admin provisioning
scripts/test_auth.php                   HTTP authentication integration tests
scripts/migrate_module3.php             Repeat-safe Module 3 database upgrade
scripts/test_inventory.php              CRUD, access, validation, and capacity tests
scripts/migrate_module4.php             Repeat-safe flight constraint upgrade
scripts/test_flights.php                Flight CRUD, validation, and security tests
bootstrap.php                           App autoloading and UTC setup
```

The shared layout adapts its navigation and logout form to the signed-in role.

## Local database setup

Start MySQL in Laragon. This workspace's ignored `.env` contains its current local database settings. Credentials are no longer hardcoded in the database config.

On a new clone, copy `.env.example` to `.env` and supply your own `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, and `DB_PASSWORD`. Bootstrap loads `.env` for both web requests and CLI scripts without a dependency. Existing process/server variables take precedence. Empty passwords are supported for local development. The old `database.local.php` override is no longer loaded.

The loader accepts one `KEY=value` per line and standalone `#` comments. Single/double quotes wrap literal values; `#`, dollar signs, and backslashes inside values are preserved. Variable expansion, escape processing, and trailing comments are not supported. Restart the development server after editing configuration.

Commit `.env.example` with placeholders. `.gitignore` excludes `.env` and `.env.*` variants while allowing `.env.example`. Do not put real passwords or API keys in the template. Continue serving only `public/`; environment files remain outside the document root. This directory is not yet a Git repository, and no GitHub push has been performed.

From the project root, with PHP and MySQL on PATH:

```powershell
mysql -u root -p -e "CREATE DATABASE airplane_ticketing CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
mysql -u root -p airplane_ticketing -e "source database/schema.sql"
php scripts/check_database.php
php scripts/test_schema.php
```

At the password prompt, enter your MySQL password (press Enter for an empty local password). If the database has already been created and imported, skip the first two commands. The SQL intentionally fails on existing tables: do not reimport it as an update or migration. MySQL DDL is not transactional; resolve a failed initial import in a new empty database. No user accounts or feature data are seeded.

Laragon's terminal provides PATH entries. In a regular PowerShell terminal, use the full paths to `php.exe` and `mysql.exe` under `D:\laragon\bin` when necessary. If using a custom port or host, also pass `--host` and `--port` to MySQL.

## Run the foundation

From the project root:

```powershell
php -S 127.0.0.1:8000 -t public public/router.php
```

Visit <http://127.0.0.1:8000/> and choose Register or Customer login. MySQL must be running to register or log in. An unknown URL returns 404; POST to `/` returns 405. Stop the server with Ctrl+C.

For Laragon/Apache, set the virtual host's `DocumentRoot` and matching `<Directory>` to `D:/laragon/www/AirplaneTicketingSystem/public`, with `AllowOverride All` and `Require all granted`. Enable `mod_rewrite` and reload Apache. The repository-root `.htaccess` denies accidental root hosting; `public/.htaccess` grants access to the public directory. Do not host the repository root or expose config, SQL, or scripts. Apache hosting has not been automatically configured.

## Tables and relationships

All eleven tables have an auto-increment primary key, `created_at`, `updated_at`, and an explicit status field. Foreign keys use RESTRICT to protect related records; status changes are intended for future workflows.

| Table | Main relationships / purpose |
| --- | --- |
| `users` | Customers/admins; unique email, future password hashes, role defaults to customer |
| `airports` | Unique IATA code; origin/destination of many flights; IANA timezone name |
| `aircraft` | Unique registration; has many seats and flights |
| `seats` | Belongs to an aircraft; seat number unique per aircraft |
| `flights` | Belongs to aircraft and two airports; many bookings |
| `bookings` | Belongs to a user and one flight; unique booking reference |
| `passengers` | Belongs to a booking; travelers need not have user accounts |
| `booking_seats` | Connects booking, passenger, flight, aircraft, and seat |
| `payments` | Many payment submissions per booking; optional reviewer references users |
| `tickets` | One ticket per seat allocation; reaches booking/passenger/flight through `booking_seats` |
| `cancellations` | One cancellation record per booking; requester/reviewer reference users |

Composite foreign keys ensure a seat allocation matches the booking's flight, the passenger's booking, and the flight's aircraft. A generated `occupied_seat_id` and unique `(flight_id, occupied_seat_id)` prevent two reserved/confirmed allocations of the same seat on a flight. Released rows have NULL occupancy, so a later booking can reuse the seat while the previous row remains. Each passenger has one allocation row; later seat changes would update that row. Tickets reference that allocation, so future confirmed-ticket changes must preserve ticket consistency.

Amounts use DECIMAL rather than floating point; currency defaults to PKR and is stored explicitly. Application/database sessions use UTC; airport timezone names support later local-time display. Payment proof paths are metadata only; future upload handling should keep files outside `public/`.

The schema does not enforce user roles on reviewer foreign keys, aircraft schedule conflicts, cross-table payment totals/currencies, or status transitions. Future modules must validate those rules and use transactions when booking, releasing seats, reviewing payments, or issuing tickets. No workflow for those operations exists in Module 1.

## Verification

`check_database.php` checks the reusable PDO connection and presence of all eleven tables. `test_schema.php` inserts temporary fixtures, tests airport/time/fare checks, composite foreign keys, duplicate active seats, released-seat reuse, and ticket/cancellation uniqueness, then rolls everything back. Use a development database; tests can consume auto-increment values even when rolled back.

To lint all PHP files in PowerShell:

```powershell
Get-ChildItem -Recurse -Filter *.php | ForEach-Object { php -l $_.FullName }
```

## Module 2 authentication

| Method | Route | Access |
| --- | --- | --- |
| GET / POST | `/register` | Guests; customer registration only |
| GET / POST | `/login` | Guests; customer login |
| POST | `/logout` | Signed-in customers |
| GET | `/profile` | Signed-in customers; read-only profile |
| GET / POST | `/admin/login` | Guests; admin login |
| POST | `/admin/logout` | Signed-in admins |
| GET | `/admin` | Signed-in admins; basic protected area |

Registration validates name, email, optional phone, password length, and password confirmation. Emails are trimmed and lowercased; the existing unique email index prevents duplicates, including concurrent registrations. Passwords use `password_hash()` and are checked using `password_verify()`. Passwords require at least 8 characters and at most 72 bytes to avoid bcrypt truncation. Registration ignores submitted roles and always creates an active customer. Registration redirects to login without automatically signing in.

All POST forms require a session CSRF token, including login and logout. Successful login regenerates the session ID, rotates the CSRF token, and stores only the user ID. Protected requests load the current user from the database and check the role and active status. Guests redirect to the appropriate login page; signed-in users with the wrong role receive 403. Customers cannot access the admin area, and admins cannot access the customer-only profile. Authenticated users visiting guest forms redirect to their own area. HEAD requests run the same access checks as GET.

Session cookies use HttpOnly, SameSite=Lax, and Secure when served through HTTPS. Session storage uses the server's configured `session.save_path`, which must be writable by PHP. Logout destroys the session and expires its cookie. Responses use `Cache-Control: no-store`; displayed user fields are HTML-escaped. No password is repopulated in forms or stored in a session. Use HTTPS when hosting beyond local development.

### Create an administrator

No default admin password or public admin registration exists. Provision an admin from Laragon's PowerShell terminal:

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

Then visit `/admin/login`. The command hashes the supplied password and creates an active admin in the existing `users` table. It refuses duplicate emails and never changes existing accounts. Admin provisioning has no web route and does not change the database schema.

### Test authentication

Keep the development server running, then in a second Laragon terminal:

```powershell
php scripts/test_auth.php http://127.0.0.1:8000
```

The server and test runner must use the same database configuration. Run on a local development database. The test makes real HTTP requests with cookies and CSRF tokens, creates randomly named customer/admin fixtures, and removes only those users in a cleanup block. It covers registration and validation, duplicate emails, both login/logout flows, invalid credentials, role restrictions, guest access, HEAD protection, session regeneration, old-session rejection, CSRF failures, escaped profile output, and inactive accounts. It does not modify flight, booking, or payment data. If the test process is forcibly terminated, its `auth-customer-*` / `auth-admin-*` users may require manual cleanup.

Validated locally with PHP 8.3.33 and MySQL 8.4.3: all PHP files pass lint, and 37 HTTP authentication checks pass. Module 2 did not change the Module 1 database schema.

## Module 3: Airports, aircraft, and seats

Sign in at `/admin/login`, then use the Airports or Aircraft & seats links in the admin navigation. Every Module 3 endpoint requires an active admin account. Guests redirect to admin login; customers receive 403. All mutations require POST and a valid CSRF token. Deletes ask for confirmation in the browser when JavaScript is enabled.

### Upgrade an existing Module 1/2 database

Stop application writes, then run from the project root:

```powershell
php scripts/migrate_module3.php
```

The upgrade adds `aircraft.total_capacity` as an unsigned integer with a positive-value CHECK constraint and narrows `seats.cabin_class` to `economy`/`business`. It preserves the existing eleven tables, users, keys, and relationships. Existing aircraft get a provisional capacity of at least their configured seat count, or 1 if no seats exist; edit them to set the actual capacity. The script preserves larger capacities on reruns and can resume after partially applied DDL. It refuses to run when first-class seats exist, so those records can be reviewed before narrowing the enum. MySQL DDL is not transactional.

The local project database has already been upgraded. Fresh installations should import the current `database/schema.sql`; do not reimport the schema into an existing database. The upgrade command is safe to run after a fresh import as well.

### New routes

The following routes exist for each resource: `airports`, `aircraft`, and `seats`.

| Method | Route | Purpose |
| --- | --- | --- |
| GET | `/admin/{resource}` | List records |
| GET | `/admin/{resource}/create` | Add form |
| GET | `/admin/{resource}/edit?id=…` | Edit form |
| POST | `/admin/{resource}` | Create record |
| POST | `/admin/{resource}/update?id=…` | Update record |
| POST | `/admin/{resource}/delete?id=…` | Delete record |

These are eighteen explicit routes, not a dynamic routing feature. Every seat route also requires `aircraft_id=…`; edit/update/delete require both the aircraft ID and seat ID. Seat records are always scoped to that aircraft, including deletion.

### Data rules

- Airports use the existing `iata_code`, `name`, `city`, and `country` columns. Codes must be three letters and are normalized to uppercase. New airports set the existing required `timezone` column to `UTC`; edits preserve its value. Actual airport timezone management is deferred to a future module.
- Aircraft use `model`, unique `registration_number`, and `total_capacity`. Registration numbers are normalized to uppercase and accept letters, digits, and hyphens. Capacity must be a positive whole number within the unsigned integer range.
- Seats use the existing aircraft foreign key, `seat_number`, and `cabin_class`. Seat numbers accept up to eight letters, digits, or hyphens and are normalized to uppercase. Classes are Economy and Business only. The existing unique `(aircraft_id, seat_number)` index prevents duplicates; the same number may appear on different aircraft.
- Aircraft capacity cannot be reduced below the existing seat count. All seats count toward capacity, regardless of their existing status. Seat mutations and aircraft capacity changes use transactions and lock the aircraft row first, preventing concurrent additions from overfilling an aircraft through this application. Direct SQL writers must follow the same locking rules; cross-table seat counts are not enforced by a database CHECK constraint.
- Deletion respects existing restrictive foreign keys. An aircraft with seats must have those seats explicitly deleted first. Records referenced by existing related data cannot be deleted; the UI displays a conflict message. No automatic cascading deletion is added.

Validation failures return 422 and preserve entered values. Missing, malformed, or mismatched IDs return 404. Delete conflicts return 409. Database queries use prepared statements and displayed values are escaped.

### Test Module 3

With MySQL and the PHP development server running, use another Laragon terminal:

```powershell
php scripts/test_inventory.php http://127.0.0.1:8000
php scripts/test_auth.php http://127.0.0.1:8000
php scripts/test_schema.php
```

The inventory test creates unique local fixtures, tests all CRUD flows, required/invalid fields, duplicates, every Module 3 route's guest/customer protection, CSRF requirements, seat-parent mismatches, capacity limits, and two simultaneous seat insertions using separate PHP processes. A cleanup block removes only its test users, airports, aircraft, and seats. The server and test runner must use the same development database configuration. A forcibly terminated test may leave fixtures behind.

Local verification: 103 Module 3 checks, 37 authentication checks, and 11 schema checks passed; all PHP files passed lint. The migration also passed a second invocation.

## Module 4: Admin flight management

Sign in at `/admin/login` and choose Flights. Admins can list, add, edit, delete, and view flights. The list shows flight number, route codes/cities, aircraft model/registration, departure, arrival, fare/currency, and status. Details also show full airport names, aircraft capacity, and timestamps. The form loads the existing airport and aircraft records; if fewer than two airports or no aircraft exist, it links to their management pages and disables submission.

### Upgrade an existing database

With application writes stopped:

```powershell
php scripts/migrate_module4.php
```

The existing `flights` table is retained. The migration changes `chk_flights_fare` from nonnegative to strictly positive and narrows status to Scheduled, Delayed, Cancelled, and Completed. Existing foreign keys, indexes, and uniqueness rules remain. No tables are added. The script refuses to change a database containing zero/negative fares or `departed` flights; review those records first. It does not silently rewrite flight data. MySQL DDL is not transactional. The command is repeat-safe and works after a fresh current-schema import.

The local database has already been upgraded. Fresh installations should use the current `database/schema.sql` instead of replaying migrations or reimporting tables.

### Routes

| Method | Route | Purpose |
| --- | --- | --- |
| GET | `/admin/flights` | Flight list |
| GET | `/admin/flights/create` | Add form |
| GET | `/admin/flights/edit?id=…` | Edit form |
| GET | `/admin/flights/show?id=…` | Flight details |
| POST | `/admin/flights` | Create flight |
| POST | `/admin/flights/update?id=…` | Update flight |
| POST | `/admin/flights/delete?id=…` | Delete flight |

All seven endpoints reuse the admin role guard. Guests redirect to admin login; customers receive 403. Mutations require valid session CSRF tokens, and GET cannot delete a flight. Missing or malformed flight IDs return 404; validation errors return 422 with form values preserved. Existing restrictive foreign keys block deletion of flights with dependent records, returning 409. No cascading deletion is added.

### Flight rules

- Flight numbers accept 1–12 letters, digits, or hyphens and are normalized to uppercase. The existing unique `(flight_number, departure_at)` index prevents duplicates for the same departure instant, including on edit, while allowing recurring flight numbers at different departures.
- Departure airport, arrival airport, and aircraft must be existing records. Airports must differ. Database foreign keys also protect against a selected record disappearing between validation and saving.
- All form inputs, list dates, detail dates, and stored flight dates use **UTC**. Enter UTC values explicitly; no browser-local or airport-local time conversion is performed. Dates must be valid within MySQL DATETIME's year range, and arrival must be strictly after departure. Minute and second precision are supported; edit forms preserve seconds. Historical flights are allowed.
- Fare is a positive decimal with up to two fractional digits, from `0.01` through `9999999999.99`. Validation and persistence use decimal strings to preserve exact cents. New flights use the existing PKR currency default; editing preserves their stored currency. There is no currency-management feature in this module.
- The only statuses are Scheduled, Delayed, Cancelled, and Completed. No booking workflows or customer search routes are introduced. Aircraft schedule-overlap checks are outside this module's requested validation.

### Test Module 4

With the local PHP development server and MySQL running, use a second Laragon terminal:

```powershell
php scripts/test_flights.php http://127.0.0.1:8000
php scripts/test_inventory.php http://127.0.0.1:8000
php scripts/test_auth.php http://127.0.0.1:8000
php scripts/test_schema.php
```

The flight test makes actual HTTP requests for CRUD, details, required fields, dates, references, positive/exact fares, every supported status, duplicate prevention, admin protection, and CSRF. It verifies that flights protect referenced airports and aircraft from deletion. Unique test users, airports, aircraft, and flights are removed in a cleanup block. Use a development database shared by the server and test runner; forced termination can leave fixtures behind.

Local verification: all 52 PHP files passed lint; 75 flight checks, 103 inventory checks, 37 authentication checks, and 12 schema checks passed. The Module 4 migration also passed a second invocation.

Module 5 requires explicit approval before implementation.
