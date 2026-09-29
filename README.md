# Carstio

Free and open-source workshop management software for car services and auto repair shops.

Carstio is a server-rendered Symfony application built for the day-to-day operations of small and medium independent garages. It is designed to be self-hostable, practical, and friendly to contributors.

## Why Carstio

- **Free and open source.** No paywalls, no limited "free tier", no upsell - every feature is in the same MIT-licensed codebase.
- **Self-hostable.** Run it on a small VPS, in your shop's office machine, or anywhere PHP and MariaDB/MySQL run. No SaaS account required.
- **Your data stays yours.** No vendor lock-in. The database, the uploads, the PDFs - everything lives where you put it.
- **Built for real workshop work.** Clients, cars, offers, work orders, parts, payments and PDFs out of the box; no abstract "modules" you have to wire together.
- **Boring, predictable stack.** Symfony, Twig, Bootstrap, server-rendered HTML enhanced with Stimulus and Turbo. Easy to read, easy to extend, easy to deploy.

## Features

### Implemented

- **Authentication and roles** - login form with brute-force throttling (5 attempts per minute), POST + CSRF-protected logout, two roles (`ROLE_ADMIN`, `ROLE_MECHANIC`), self-service guard against deactivating yourself or removing the last admin.
- **User management** (admin only) - create, edit, deactivate / reactivate accounts; per-user preferred locale.
- **Clients** - name, phone, email, notes; deleting a client removes its cars and companies, but is soft-blocked while any work order or offer still references the client or one of its cars.
- **Cars** - VIN, license plate, brand and model (catalog of brands/models seeded by fixtures), one client per car.
- **Companies** - invoice-style customer entities tied to a client.
- **Offers / estimates** - line items (labor, parts, misc), discounts and per-line tax, status workflow (draft → sent → approved → rejected / expired / converted), validity dates.
- **Convert offers to work orders** - one-click conversion that copies items and parts cost, snapshots the currency and links the offer to the new work order.
- **Work orders** - complaint, diagnosis, source, status (draft, open, in progress, waiting parts, waiting approval, completed, cancelled, closed), labor / parts / misc line items, scheduling.
- **Payments** - partial payments, payment methods (cash, card, bank transfer), payment status (pending, paid, partially paid, refunded, void), automatic recalculation of paid amount and remaining balance.
- **Parts catalog (resale model)** - name, optional brand / SKU / part number, optional cost and sell price, free-form line items on documents, per-line cost capture, **internal-only** parts margin/profit summary on offers, work orders and the dashboard.
- **PDF generation** - server-rendered offer and work order PDFs (Dompdf) using the company profile (name, address, IDs, optional logo) and an optional document footer.
- **Email sending** - SMTP-backed sender for offer and work order PDFs to the client's email; uses the SMTP credentials configured in Settings, with a recording transport in tests.
- **Settings** (admin only) -
  - Company identity (name, IDs, address, contact, logo upload)
  - Regional defaults (locale, timezone, date and time formats)
  - Currency and default tax rate
  - Document numbering prefixes, default offer validity, document footer text
  - SMTP host, port, encryption, credentials (password is encrypted with `APP_SECRET`)
- **Calendar** - month-view dashboard calendar of scheduled work orders (locally bundled FullCalendar).
- **Statistics** (admin only) - revenue and parts-margin KPIs, revenue chart over time, work orders / offers by status, payments by method, top clients, mechanic workload, top profitable parts. All charts render with Chart.js and a server-built data payload.
- **Attachments** - upload PDFs, images and MP4 to a work order. Files are stored **outside** the public web root and only served through authenticated routes; allow-list of MIME types.
- **Localization** - five UI locales: English, Bulgarian, German, Spanish, French. Locale is resolved from the user profile, the session, then the Settings default.
- **Global search** - header search across clients, cars and companies with a debounced JSON autocomplete.
- **Audit-friendly deletes** - foreign-key violations are converted to a friendly "this record cannot be deleted" flash instead of a 500.

### Out of scope (intentionally) for now

- Warehouse / stock movements
- Invoicing as a separate document (work orders already act as the billable record)
- Multi-location / multi-tenant
- Mobile app

## Tech stack

- **Backend:** PHP 8.4+, Symfony 8.1, Doctrine ORM 3
- **Templating:** Twig 3
- **UI:** Bootstrap 5 (locally bundled), tom-select, flatpickr, FullCalendar, Chart.js, Symfony UX 3 (Turbo, Stimulus, Autocomplete)
- **Frontend pipeline:** Symfony AssetMapper + Stimulus + Turbo (no Webpack, no npm at runtime)
- **PDF:** Dompdf
- **Mailer:** Symfony Mailer with SMTP transport
- **Database:** MariaDB 11 (default; MySQL 8 also works - set the matching `serverVersion` in `DATABASE_URL`)
- **Tests:** PHPUnit 13, PHPStan (level 6) for static analysis

All third-party CSS and JavaScript ship from the local asset map - no CDN or remote font is required to run the app.

## Requirements

The fastest path is **Docker** (everything is set up for you). For a native install you'll need:

- **PHP 8.4 or newer**
- PHP extensions: `ctype`, `fileinfo`, `gd`, `iconv`, `intl`, `mbstring`, `pdo_mysql`, `sodium`, `zip` (Composer checks the ones the app relies on at install time)
- **Composer 2**
- **MariaDB 11** (or MySQL 8 - adjust `serverVersion` in `DATABASE_URL`)
- *(Optional)* **Symfony CLI** for `symfony server:start` and `symfony console`

No Node.js or `npm install` is needed at runtime - frontend dependencies are resolved by Symfony AssetMapper / `importmap`.

## Quick start (Docker)

The bundled `compose.yaml` brings up four services: **MariaDB 11**, the PHP-FPM `app` container, **Caddy** as the web server, and **Mailpit** as a local SMTP catcher. The default ports are:

| Service       | URL                       | Notes                                        |
| ------------- | ------------------------- | -------------------------------------------- |
| App (web)     | <http://localhost:8080>   | Caddy → PHP-FPM, served from `public/`       |
| Mailpit UI    | <http://localhost:8025>   | Outgoing mail viewer for the offer/WO emails |
| MariaDB       | `127.0.0.1:3307`          | For host tools; the app reaches it as `database:3306` |

```bash
# 1. Clone the project
git clone https://github.com/carstio/carstio.git
cd carstio

# 2. Build images and start the stack
docker compose up -d --build

# 3. Install PHP dependencies inside the app container
docker compose exec app composer install

# 4. Run the database migrations and load demo data
docker compose exec app php bin/console doctrine:migrations:migrate -n
docker compose exec app php bin/console doctrine:fixtures:load -n
```

Open <http://localhost:8080> and log in with the demo credentials below.

### Demo credentials (loaded by `doctrine:fixtures:load`)

| Email                   | Password    | Role     |
| ----------------------- | ----------- | -------- |
| `admin@carstio.local`   | `admin`     | Admin    |
| `owner@carstio.local`   | `owner123`  | Admin    |
| `mechanic@carstio.local`| `mechanic`  | Mechanic |

> Change these immediately for any real deployment - they exist only so the demo loads cleanly.

### Stop and reset

```bash
# Stop containers (keeps the database volume)
docker compose down

# Wipe the database too - useful when iterating on migrations
docker compose down -v
docker compose up -d
docker compose exec app php bin/console doctrine:migrations:migrate -n
docker compose exec app php bin/console doctrine:fixtures:load -n
```

### Running tests in Docker

```bash
docker compose exec app php bin/console --env=test doctrine:database:create --if-not-exists
docker compose exec app php bin/console --env=test doctrine:migrations:migrate -n
docker compose exec app php bin/console --env=test doctrine:fixtures:load -n
docker compose exec app ./bin/phpunit
```

The MariaDB container is preloaded (via `docker/mariadb/init-test-db.sql`) so the application user can manage `carstio_test` without root.

## Quick start (native install, no Docker)

If you'd rather run PHP and MariaDB directly on your host:

```bash
git clone https://github.com/carstio/carstio.git
cd carstio

# 1. PHP dependencies
composer install

# 2. Local environment
cp .env.example .env.local
# Edit .env.local and set DATABASE_URL to your local MariaDB. The default
# .env value points at the Docker service "database"; overriding it is the
# main reason .env.local exists.

# 3. Database
php bin/console doctrine:database:create
php bin/console doctrine:migrations:migrate -n
php bin/console doctrine:fixtures:load -n

# 4. Frontend assets (downloaded into assets/vendor/ on install)
php bin/console importmap:install
php bin/console asset-map:compile        # only needed in production

# 5. Run the app
symfony server:start                      # or: php -S 127.0.0.1:8000 -t public
```

Open <http://127.0.0.1:8000> and log in.

## Configuration

After a fresh install, sign in as an admin and visit **Settings**. Each section reads and writes through one central `SettingsRegistry`, so adding a new setting is a one-file change.

| Section          | What it controls                                                              |
| ---------------- | ----------------------------------------------------------------------------- |
| Company          | Workshop name, IDs, address, contact, logo (used in PDF headers and emails)   |
| Regional         | Default locale, timezone, date and time formats                               |
| Currency & Tax   | Default currency for new documents (existing documents keep their snapshot)   |
| Documents        | Numbering prefixes (`OF`, `WO`, `INV`), default offer validity, PDF footer    |
| Email / SMTP     | Outgoing mail credentials (SMTP password is encrypted with `APP_SECRET`)      |

The application currency on every offer and work order is **snapshotted at creation**, so a later change in Settings never rewrites old totals.

### Production checklist

- Set `APP_ENV=prod` and a strong, unique `APP_SECRET` (`php -r "echo bin2hex(random_bytes(16));"`) in `.env.local` or the real environment. `.env` ships with an empty secret on purpose and the app refuses to serve requests without one; only `APP_ENV=dev` gets a throwaway value from `.env.dev`.
- Run `composer install --no-dev --optimize-autoloader`, `php bin/console asset-map:compile` and `php bin/console doctrine:migrations:migrate -n` on every deploy.
- Serve over HTTPS behind a web server or reverse proxy and configure `framework.trusted_proxies` so login throttling sees real client IPs.
- Keep `var/storage/attachments` and `public/uploads` on persistent storage and in your backups, together with the database.

## Architecture overview

Carstio is a modular monolith organised by Symfony convention:

```
src/
├── Controller/           thin HTTP layer; one action per route/use case
├── Entity/               Doctrine entities grouped by feature (Car, Client, Sales, Workshop, Part, …)
├── Enum/                 Type-safe enums (statuses, roles, currencies, …)
├── EventSubscriber/      kernel events (locale resolution)
├── Form/                 Symfony FormType classes
├── Repository/           query logic; no business workflows
├── Security/             UserChecker, role hierarchy
├── Service/              business workflows (calculators, factories, mailers, search, …)
├── Settings/             central SettingsRegistry / SettingDefinition
├── Twig/                 view-only extensions (money, dates, discount, locale, car label)
templates/                Twig templates grouped by feature
translations/             messages.{en,bg,de,es,fr}.yaml
migrations/               Doctrine migrations
tests/                    PHPUnit (Unit + Functional)
assets/                   AssetMapper sources (controllers, vendor, styles)
```

Conventions worth knowing:

- **Controllers stay thin.** Business logic lives in services (`*Calculator`, `*Factory`, `*Manager`, `*Updater`).
- **Money is decimal strings** in the database, formatted by the Twig `money` filter using the document's currency snapshot.
- **PDFs and client emails never expose internal cost or profit.** The parts-margin card and the statistics dashboard are admin-only views.
- **Attachments live outside the web root** (`var/storage/attachments`) and are streamed by `AttachmentController` after auth checks.

## Tests

The test suite (390+ tests, 3k+ assertions) hits a real MariaDB database - there are no DB mocks. Each test runs inside a transaction that is rolled back afterwards (via `dama/doctrine-test-bundle`), so repeated runs are isolated and deterministic. You only need to rebuild the test database when fixtures or the schema change:

```bash
# Inside Docker (recommended)
docker compose exec app php bin/console --env=test doctrine:database:drop --force --if-exists
docker compose exec app php bin/console --env=test doctrine:database:create
docker compose exec app php bin/console --env=test doctrine:migrations:migrate -n
docker compose exec app php bin/console --env=test doctrine:fixtures:load -n
docker compose exec app ./bin/phpunit

# Native
php bin/console --env=test doctrine:database:drop --force --if-exists
php bin/console --env=test doctrine:database:create
php bin/console --env=test doctrine:migrations:migrate -n
php bin/console --env=test doctrine:fixtures:load -n
./bin/phpunit
```

Run a single suite with `./bin/phpunit --testsuite Unit` or `./bin/phpunit --testsuite Functional`. Tests that send email use a recording in-memory transport, so no real SMTP server is contacted.

## Static analysis

PHPStan runs at level 6 over `src/` with the Symfony and Doctrine extensions (configuration in `phpstan.dist.neon`). The Symfony extension reads the compiled dev container, so warm it up first:

```bash
php bin/console cache:warmup
vendor/bin/phpstan analyse
```

CI runs the same command, together with `composer audit`, the container/Twig/YAML linters and the test suite.

## Contributing

Pull requests, bug reports and translations are welcome.

A few principles to keep in mind before opening a PR:

- Keep changes focused. A bug fix doesn't need a surrounding cleanup PR; a new feature shouldn't sneak in a refactor of unrelated code.
- Prefer Symfony / Twig / Bootstrap idioms over custom abstractions. This project deliberately stays close to the framework so contributors don't need to learn project-specific patterns.
- Add or update tests for behaviour you change. Functional tests can boot the kernel; unit tests should not.
- Keep `vendor/bin/phpstan analyse` green; fix the cause instead of adding ignores.
- Never put untrusted values inside inline JavaScript (`onsubmit="confirm('…')"`) without the `|e('js')` escaper.
- Don't introduce CDN or remote-hosted assets. Everything ships from `assets/` via AssetMapper.
- Keep client-facing PDFs free of any internal cost / profit data.

## License

Carstio is released under the [MIT License](LICENSE). You are free to use, modify and self-host it for personal or commercial purposes.
