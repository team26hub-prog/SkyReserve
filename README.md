# Airplane Ticketing System — Modules 1–6

Plain PHP MVC application for a single airline. Module 1 provides the foundation and database; Module 2 adds authentication; Module 3 adds admin airport, aircraft, and aircraft-seat management; Module 4 adds admin flight management; Module 5 adds customer flight search; Module 6 adds customer bookings and passenger details. Customer seat selection, payment submission/verification, tickets, and cancellation workflows are not implemented.

## Requirements

- PHP 8.1+ with `pdo_mysql` and `mbstring` (`curl` is needed only for HTTP integration tests).
- MySQL 8.0.16+ (enforced CHECK constraints), using InnoDB.
- Apache 2.4 with `mod_rewrite` for Laragon hosting, or PHP's built-in server for development.
- No Composer, framework, or JavaScript build tools are required.

## Structure

```text
app/
  Controllers/                         Home, auth, profile, admin, airport/aircraft/seat/flight CRUD
  Controllers/FlightSearchController.php Public customer search and details
  Core/Controller.php                   Rendering, redirects, role/CSRF guards
  Core/Auth.php                         Current-user lookup and sign-in/out
  Core/Session.php                      Session cookies, CSRF, flash messages
  Core/Database.php                     Shared, lazy PDO connection
  Models/Model.php                      Shared model base
  Models/User.php                       Prepared user queries and registration
  Models/Airport.php                    Airport persistence
  Models/Aircraft.php                   Aircraft persistence and capacity locking
  Models/Seat.php                       Aircraft-scoped seat persistence
  Models/Flight.php                     Flight persistence, availability queries, joined details
  Models/Booking.php                    Atomic booking creation and customer ownership
  Models/Passenger.php                  Passenger persistence
  Views/                               Home, auth, customer, admin, errors
  Views/layouts/base.php                Shared minimal layout
config/                                 Database settings read from environment
.env.example                            Safe template for local settings
.env                                    Local secrets (ignored by Git)
database/schema.sql                     Current eleven-table schema (Modules 1–6)
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
scripts/test_search.php                 Customer search and availability integration tests
scripts/migrate_module6.php             Repeat-safe passenger/submission-key upgrade
scripts/test_bookings.php               Booking, validation, ownership, and replay tests
bootstrap.php                           App autoloading and UTC setup
```

The shared layout adapts its navigation and logout form to the signed-in role.

## Local database setup

Start MySQL in Laragon. This workspace's ignored `.env` contains its current local database settings. Credentials are no longer hardcoded in the database config.

On a new clone, copy `.env.example` to `.env` and supply your own `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, and `DB_PASSWORD`. Bootstrap loads `.env` for both web requests and CLI scripts without a dependency. Existing process/server variables take precedence. Empty passwords are supported for local development. The old `database.local.php` override is no longer loaded.

The loader accepts one `KEY=value` per line and standalone `#` comments. Single/double quotes wrap literal values; `#`, dollar signs, and backslashes inside values are preserved. Variable expansion, escape processing, and trailing comments are not supported. Restart the development server after editing configuration.

Commit `.env.example` with placeholders. `.gitignore` excludes `.env` and `.env.*` variants while allowing `.env.example`. Do not put real passwords or API keys in the template. Continue serving only `public/`; environment files remain outside the document root.

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

## Module 5: Customer flight search

Open `/flights` using the Search flights link in the header or home page. Guests and signed-in customers can browse without logging in; admins may also preview these public pages. Existing admin management routes remain protected. This module adds only GET routes, with no customer mutation endpoints:

| Method | Route | Purpose |
| --- | --- | --- |
| GET | `/flights` | Search form |
| GET | `/flights?from_airport_id=…&to_airport_id=…&travel_date=YYYY-MM-DD` | Search results |
| GET | `/flights/show?id=…` | Available flight details |

Search requires two different existing active airports and a valid date today or later. Invalid, partial, or array-valued input returns 422 with useful errors and preserved form values. A valid search with no matching flights returns 200 with a no-results message. The form and all flight dates/times explicitly use UTC, consistent with Module 4. Airport-local date conversion is not introduced.

The query selects the requested route and UTC departure calendar day using an indexed date range from `00:00:00` through `23:59:59`, ordered by departure time. Flights must be Scheduled, have a departure later than the current UTC time, use active airports and an active aircraft, and have at least one available seat. Delayed, Cancelled, and Completed flights are excluded.

Available seats are actual configured active aircraft seats without a reserved/confirmed `booking_seats` allocation for that flight. Released allocations do not occupy seats; allocations on other flights do not affect the count. Capacity alone does not create available seats: an aircraft with no active configured seats has no customer-search availability. This reads existing tables only; no booking or passenger workflows are implemented.

Results show flight number, route and airport names, departure/arrival UTC times, aircraft model, exact fare/currency, available seats, status, and a View flight link. Customer details repeat these fields without admin edit/delete controls or a booking button. The detail query repeats availability checks, so sold-out, inactive, unavailable, past-departure, missing, or malformed flight IDs return a customer-friendly 404. The return link restores the corresponding route/date search.

All input values are bound parameters, displayed data is escaped, and POST requests to search/details return 405. GET browsing never updates flights, reserves seats, or creates bookings. Availability is a current snapshot and can change before a future booking module. No schema or migration changes are required for Module 5.

### Test Module 5

With the PHP development server and MySQL running, use a second Laragon terminal:

```powershell
php scripts/test_search.php http://127.0.0.1:8000
```

The test creates uniquely named reference/flight fixtures and temporary existing allocation-table fixtures to check availability; it adds no booking endpoints. It verifies valid/no-result/invalid searches, UTC midnight boundaries, date filtering, status exclusions, inactive/unconfigured aircraft, available-seat counts, sold-out transitions, details, escaped output, POST rejection, admin protection, and unchanged flight data/timestamps after browsing. Its cleanup block removes only its fixtures, including allocation/passenger/booking records. Use a local development database with the same configuration as the server. Forced termination may leave fixtures behind.

Local verification: 43 search checks, 75 flight checks, 103 inventory checks, 37 authentication checks, and 12 schema checks passed. All 57 PHP files passed lint.

## Module 6: Booking and passenger details

Logged-in customers can choose Start booking on a customer flight details page. Guests are redirected to customer login and admins cannot use customer booking routes. After logging in, return to the selected flight and open its booking form.

| Method | Route | Purpose |
| --- | --- | --- |
| GET | `/bookings/create?flight_id=…` | Passenger form for an available flight |
| POST | `/bookings?flight_id=…` | Create booking and passenger |
| GET | `/bookings/show?id=…` | Owner-only booking summary |

Each submission creates one booking for one flight and one passenger. Required passenger fields are full name, CNIC/passport, date of birth, gender, and phone. Names accept letters, spaces, apostrophes, periods, and hyphens, up to 160 characters. CNICs require 13 digits, with optional standard hyphens; hyphens are removed for storage. Passports accept 6–20 alphanumeric characters including a letter. Date of birth must be a valid date from 1900 through today in UTC. Phone numbers require 7–15 digits; leading plus, spaces, parentheses, and hyphens are supported.

Before creating records, the server locks the flight and rechecks the existing Module 5 Scheduled/upcoming/active/seat-availability conditions. A single transaction inserts the booking and passenger; failures roll back both. Customer ID comes from authentication; fare/currency come from the current flight, ignoring submitted price, status, or customer IDs. A random 14-character `SR` reference/PNR is protected by the existing unique index, with collision retries. Database status remains `pending`, displayed as **Pending Payment**.

A session-bound random booking token is scoped to its selected flight and expires after 30 minutes. Up to 20 recent forms are retained per session. Its SHA-256 hash is stored in a unique nullable `bookings.submission_key`; retries redirect to the same booking instead of inserting another passenger/booking. Successful retries remain recoverable even after the original form expires. A new form represents a new booking. POST requires CSRF protection. The summary is accessible only to the owning active customer; another customer's ID returns 404. Validation failures preserve entered values without storing them in the session.

The summary includes PNR, all passenger fields, flight/route/times, the fare captured at booking, and Pending Payment status. Flight schedule information is read from the current flight record. This module does **not** reserve a seat or guarantee future seat availability; it creates no `booking_seats`, payment, ticket, or cancellation records. Seat allocation and later confirmation remain future modules.

### Database upgrade

Stop application writes and run:

```powershell
php scripts/migrate_module6.php
```

The local database has already been upgraded. No tables are added. The migration adds nullable `bookings.submission_key` plus a unique index, and nullable passenger `full_name`, `gender`, and `phone`. Existing passenger names are backfilled from first/last names; legacy first/last columns are still populated for compatibility. Nullable columns preserve existing Module 1–5 records and fixtures. The script is repeat-safe; MySQL DDL is not transactional. New installations can import the current schema directly.

### Test Module 6

With the development server and MySQL running, use another Laragon terminal:

```powershell
php scripts/test_bookings.php http://127.0.0.1:8000
```

Tests cover successful bookings, required/invalid passenger fields, guest/admin protection, CSRF, invalid/unavailable flights, changes after opening the form, unique PNRs, customer/passenger relationships, ownership, duplicate submissions, transactional rollback, and absence of seat/payment creation. Uniquely named fixtures are removed in cleanup; use the same local development database for the server and runner.

Local verification: 42 booking checks and all 270 previous-module checks passed. All 65 PHP files passed lint, and the migration passed two invocations.

Module 7 requires explicit approval before implementation.
