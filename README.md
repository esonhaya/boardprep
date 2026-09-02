# BoardPrep

BoardPrep is a PHP application with a file-backed JSON store by default. It
requires PHP 8.1 or newer and a readable, writable storage directory.

## Configuration

Copy `.env.example` to `.env` and adjust it for the local machine. `.env` is
ignored and must never be committed. The supported local default is
`DB_DRIVER=json`; BoardPrep creates `APP_STORAGE_PATH` when it is missing.
Process environment variables take precedence over values in `.env`.

`APP_TIMEZONE` must be a PHP timezone identifier such as `UTC` or
`Asia/Manila`. SQLite is supported for the mutable attempts proof domain and
requires `pdo_sqlite`; set `DB_DRIVER=sqlite` and optionally
`DB_SQLITE_PATH=/absolute/path/to/boardprep.sqlite`. MySQL remains optional;
selecting `DB_DRIVER=mysql` requires the `pdo_mysql` extension and the
documented `DB_*` connection settings.

`APP_ENV` must be `production`, `development`, or `testing`. Production rejects
`APP_DEBUG=true`; developer authoring and Doctor HTTP routes are available only
outside production. Unhandled exceptions are written through PHP's configured
error log while learner-facing production responses remain generic.

## Deployment and runtime

Use PHP 8.1 or newer with the `json` and `session` extensions. Configure the web
server document root as `public/`, deny directory listing, serve existing files
directly, and send every other request to `public/index.php`. Never expose the
repository root: `.env`, `app/`, `config/`, `database/`, `storage/`, and `tools/`
are not public content.

For the default JSON driver, `APP_STORAGE_PATH` must point to durable storage
that the PHP process can read, write, and create files/directories in. BoardPrep
creates a missing storage directory and collection files, then fails startup
with a generic HTTP 500 and a detailed server-log entry if storage is
unavailable. Back up this directory before deployment or migration. MySQL does
not use this filesystem store.

For SQLite, initialize the schema and import legacy mutable user state without
deleting the JSON source:

```sh
DB_DRIVER=sqlite DB_SQLITE_PATH=/absolute/path/to/boardprep.sqlite \
php tools/db-migrate.php
```

The mutable `attempts` and `weakness` collections are routed to SQLite in this
foundation; canonical questions, study material, taxonomy, and other curated collections
remain source-controlled/file-backed until a dedicated migration contract is
approved. Keep the database outside `public/` and include it in deployment
backups. A verified SQLite backup should use a quiescent application state (or
SQLite's backup API); restore with the application stopped and rerun migrations.

For a production-style local smoke test, use a temporary storage directory and
the repository's built-in-server router:

```sh
APP_ENV=production APP_DEBUG=false APP_TIMEZONE=UTC \
APP_STORAGE_PATH=/absolute/writable/path php -S 127.0.0.1:8080 -t public public/router.php
```

Then verify `/`, `/dashboard`, `/assets/css/style.css`, and a missing route;
`/developer` and `/api/doctor.php` must return 404 in production. The built-in
server is a smoke-test aid, not a recommended production server.

## Verification

Run `php tools/function-test.php`, `php tools/http-test.php`,
`php tools/Tests/RuntimeConfigurationTest.php`, `php tools/Tests/QuizTest.php`,
and `php tools/doctor.php` from the repository root. Developer-route tests
require `APP_ENV=testing`.

Developer simulation: `php tools/doctor.php --simulate`. This runs the registered
scenario suite after the ordinary Doctor checks and is deeper than those checks.
Run it before human acceptance testing. The manual checklist is in
`docs/HUMAN_TESTER_READINESS.md`; ordinary Doctor runs intentionally do not run
the full persona suite.

## Release-candidate acceptance

The single human acceptance entry point is
[`docs/HUMAN_TESTER_READINESS.md`](docs/HUMAN_TESTER_READINESS.md). It contains
the 12 bundled MVP cases, high-risk-first execution order, test-session header,
copy/paste result record, evidence guidance, defect handling, and the separate
automated-versus-human release gate. Human acceptance is never inferred from
the simulation report and all cases remain `NOT_RUN` until a real tester records
them.

Before handing a build to a tester, run `php tools/doctor.php --simulate` and
record its result in the session header. Automated readiness and human
readiness are independent: real browser/mobile behavior, visual judgment,
accessibility, and learner comprehension require human evidence.

## Session and proxy requirements

Learner sessions use strict session IDs and cookies marked `HttpOnly` and
`SameSite=Lax`. In HTTPS production, the front server must pass the original
HTTPS state through to PHP (`HTTPS=on`, or the equivalent server configuration)
so the session cookie receives its `Secure` flag. The built-in HTTP smoke test
is intentionally non-TLS and therefore uses a non-secure cookie.
