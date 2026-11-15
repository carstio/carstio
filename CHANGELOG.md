# Changelog

All notable changes to Carstio are described here, one section per released
version (a version is a `vX.Y.Z` git tag). The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/); versions follow
[Semantic Versioning](https://semver.org/). While Carstio is pre-1.0, a
minor version (0.x) may contain changes that need attention when upgrading:
they are listed under "Upgrading" in each section.

## [0.2.0] - 2026-11-15

PostgreSQL support, password reset by email, a round of security hardening,
and fixes found while preparing Carstio for production use.

### Added

- **PostgreSQL support.** Carstio runs on MariaDB, MySQL or PostgreSQL 15+,
  selected by `DATABASE_URL` alone. Migrations, fixtures and the test suite
  run on both engine families, and CI runs on MariaDB 11.4 and
  PostgreSQL 16. Logging in and searching are case-insensitive on every
  engine, including for non-ASCII text such as Cyrillic.
- **Password reset by email.** "Forgot your password?" on the login page
  sends a link that is valid for 60 minutes and works once, written in the
  user's own language. Requests are limited per address and per visitor,
  and the answer is the same whether or not the address has an account.
  It uses the SMTP account from Settings → Email; without one, the page
  says that resetting by email is not available.
- `TRUSTED_HOSTS` setting: the host names the installation answers to
  (links in emails are built from the host name of the request).
- Brute-force protection on the login form (5 attempts per minute).
- Security headers on every response (`X-Content-Type-Options`,
  `X-Frame-Options`, `Referrer-Policy`).
- Database indexes on the status and date columns used by list filters
  and statistics.
- PHPStan (level 6) and an audit of the JavaScript packages in CI.

### Changed

- **Purchase costs are for administrators only.** Cost price, profit and
  margin on parts, offers and work orders are no longer shown to
  mechanics, and mechanics can no longer change them. What an
  administrator entered is kept when a mechanic edits a part or a document.
- **Deactivating a user takes effect at once.** Their open sessions end
  with the next click; so do the sessions of a user whose role or
  password was changed by someone else.
- Logging out is a POST request with a CSRF token; a link or a prefetch
  can no longer end a session.
- `.env` ships with an empty `APP_SECRET`: every installation has to set
  its own, and the application refuses to run without one.
- Upgraded to Symfony 8.1, Symfony UX 3, Turbo 8, DoctrineMigrationsBundle 4,
  PHPUnit 13 and dompdf 3.1.6; removed the unused notifier, web-link and
  expression-language packages.
- The calendar library (FullCalendar) is part of the repository
  (`assets/lib/`) instead of a file that had to be present locally.
- Errors that concern a whole work order or offer, not one field, are
  shown at the top of the edit page.

### Fixed

- **Payments did not update the work order.** Adding or deleting a payment
  left the stored paid amount and payment status unchanged, so lists,
  filters and statistics showed paid work orders as unpaid.
- A work order paid with exactly the amount shown on it could stay
  "partially paid" because of a fraction of a cent.
- The dashboard calendar did not load on a fresh installation.
- Pages were wider than the screen on phones (the top bar on every page,
  the charts on the statistics page).
- A company logo was missing from PDFs when the uploads directory was a
  symbolic link.
- Deleting a work order left its attachments behind.
- Changing a work order's status through the edit form did not record
  when it was started, completed or closed.
- "Converted" could be chosen by hand as the status of an offer.
- Document numbers stopped counting correctly after 9999.

### Security

- Stored cross-site scripting: a client's email address was placed
  unescaped in inline confirmation dialogs.
- Values a browser would not send are now refused by the server instead
  of being stored or crashing the page: tax rates outside 0-100 %,
  negative discounts, discounts larger than their line, amounts too large
  to store, over-long texts and malformed email addresses on all forms.
- Mechanics could read and overwrite purchase costs and margins (see
  "Changed").
- A deactivated user kept working until their session expired (see
  "Changed").
- Updated dependencies with known security advisories, among them dompdf.

### Upgrading from 0.1.0

Verified on MariaDB: a database created by 0.1.0 is brought up to date by
one new migration, after which the schema matches the code.

1. Update the code, then run:
   ```bash
   composer install --no-dev --optimize-autoloader
   php bin/console doctrine:migrations:migrate -n
   php bin/console asset-map:compile
   ```
2. **Set `APP_SECRET`** in `.env.local` (or the real environment) if you
   have not already. 0.1.0 shipped a default value in `.env`; that value is
   public and is gone. Generate your own with
   `php -r "echo bin2hex(random_bytes(16));"`. The SMTP password in
   Settings → Email is encrypted with this secret: after changing it,
   enter the SMTP password again. Everyone is logged out once.
3. Set `TRUSTED_HOSTS` to your host name unless your web server already
   refuses requests for other host names.
4. Composer now checks for the PHP extensions the application uses
   (`fileinfo`, `gd`, `intl`, `mbstring`, `pdo`, `sodium`): if one is
   missing, `composer install` says so instead of the application failing
   later. The database driver (`pdo_mysql` or `pdo_pgsql`) is yours to
   install, because it depends on your `DATABASE_URL`.
5. Tell your mechanics that cost and margin figures are no longer visible
   to them. If someone other than an administrator has to see them, give
   that person the administrator role.

Work orders whose payments were recorded with 0.1.0 keep their stale paid
amount until they are next saved or get another payment; open and save
such a work order to correct it.

## [0.1.0] - 2026-07-16

First public release: workshop management for independent car services,
server-rendered with Symfony, Twig, Bootstrap, Stimulus and Turbo, on
MariaDB or MySQL.

### Added

- Login with two roles (administrator, mechanic) and user management for
  administrators, with a guard against removing the last administrator.
- Clients, their cars (brand and model catalog, VIN, licence plate) and
  companies.
- Offers with labour, parts and other lines, discounts and tax per line, a
  status workflow and validity dates; one-click conversion of an offer
  into a work order.
- Work orders with complaint, diagnosis, status, line items, scheduling
  and mileage.
- Payments: partial payments, payment methods and payment status per work
  order.
- A parts catalog with cost and selling price, and a parts margin summary
  on offers and work orders.
- PDF documents for offers and work orders with the company's details and
  logo, and sending them to the client by email through the SMTP account
  in the settings.
- Attachments (PDF, images, video) on work orders, stored outside the
  public web root.
- Settings for the company, regional formats, currency and default tax
  rate, document numbering and email.
- A dashboard with a calendar of scheduled work orders, and statistics for
  administrators (revenue, margins, work orders and offers by status,
  payments by method, top clients and parts).
- Global search across clients, cars and companies.
- The interface in English, Bulgarian, German, Spanish and French.
- Docker setup for local use (MariaDB, PHP-FPM, Caddy, Mailpit) and a
  PHPUnit test suite that runs against a real database.

[0.2.0]: https://github.com/carstio/carstio/compare/v0.1.0...v0.2.0
[0.1.0]: https://github.com/carstio/carstio/releases/tag/v0.1.0
