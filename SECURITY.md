# Security Policy

Carstio stores customer data (names, phones, emails, vehicles) and SMTP
credentials, so we take security reports seriously.

## Reporting a vulnerability

Please **do not open a public GitHub issue** for security problems.

Instead, use GitHub's private vulnerability reporting on this repository:
**Security → Report a vulnerability** (or
<https://github.com/carstio/carstio/security/advisories/new>).

Include as much detail as you can: affected route or component, steps to
reproduce, and the impact you believe it has. You will get a response as
quickly as possible, usually within a few days.

Once a fix is released, we are happy to credit you in the advisory unless
you prefer to stay anonymous.

## Scope notes for self-hosters

- The demo credentials loaded by `doctrine:fixtures:load` are for local
  evaluation only. Never load fixtures on a production instance, and change
  any demo passwords immediately if you did.
- Set a strong, unique `APP_SECRET` in production: it protects Symfony
  internals **and** encrypts the stored SMTP password.
- Run the app behind HTTPS in production; the login form and session cookie
  are only as safe as the transport.
- Keep `APP_ENV=prod` on any internet-facing install so debug pages and the
  profiler are never exposed.
- The application sends `X-Content-Type-Options`, `X-Frame-Options` and
  `Referrer-Policy` headers itself; add `Strict-Transport-Security` and a
  `Content-Security-Policy` at the web server / reverse proxy level.
- Logins are throttled (5 attempts per username and IP per minute) using the
  `cache.app` pool; behind a reverse proxy configure `trusted_proxies` so the
  real client IP is used.

## Supported versions

Carstio is pre-1.0. Security fixes land on the default branch; there are no
long-term support branches yet.
