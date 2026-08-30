# BoardPrep

BoardPrep is a PHP application with a file-backed JSON store by default. It
requires PHP 8.1 or newer and a readable, writable storage directory.

## Configuration

Copy `.env.example` to `.env` and adjust it for the local machine. `.env` is
ignored and must never be committed. The supported local default is
`DB_DRIVER=json`; BoardPrep creates `APP_STORAGE_PATH` when it is missing.
Process environment variables take precedence over values in `.env`.

`APP_TIMEZONE` must be a PHP timezone identifier such as `UTC` or
`Asia/Manila`. MySQL is optional; selecting `DB_DRIVER=mysql` requires the
`pdo_mysql` extension and the documented `DB_*` connection settings.

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

Use `php tools/doctor.php --simulate` for the deeper developer learner simulation
before human acceptance testing. The manual checklist is in
`docs/HUMAN_TESTER_READINESS.md`; ordinary Doctor runs intentionally do not run
the full persona suite.
