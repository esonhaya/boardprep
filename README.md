# BoardPrep v1.0.1

BoardPrep is a PHP application with a file-backed JSON store by default. It
requires PHP 8.1 or newer and a readable, writable storage directory.

## Configuration

Copy `.env.example` to `.env` and adjust it for the local machine. `.env` is
ignored and must never be committed. The supported local default is
`DB_DRIVER=json`; BoardPrep creates `APP_STORAGE_PATH` when it is missing, but
an external storage directory must first contain the release's canonical JSON
files.
Process environment variables take precedence over values in `.env`.

`APP_TIMEZONE` must be a PHP timezone identifier such as `UTC` or
`Asia/Manila`. SQLite is supported for mutable learner state and
requires `pdo_sqlite`; set `DB_DRIVER=sqlite` and optionally
`DB_SQLITE_PATH=/absolute/path/to/boardprep.sqlite`. MySQL support is not
claimed by the v1.0.1 production profile; use JSON or SQLite for this release.

`APP_ENV` must be `production`, `development`, or `testing`. Production rejects
`APP_DEBUG=true`; developer authoring and Doctor HTTP routes are available only
outside production. Unhandled exceptions are written through PHP's configured
error log while learner-facing production responses remain generic.

## Deployment and runtime

Use PHP 8.1 or newer with `json`, `session`, `PDO`, and `mbstring`. The
recommended document root is `public/`; serve existing files directly and send
every other request to `public/index.php`. When a provider fixes the document
root at `public_html`, keep the package in a sibling private directory and use
the front-controller shim described in
[`docs/RELEASE_RUNBOOK_V1.md`](docs/RELEASE_RUNBOOK_V1.md). Never expose the
repository root: `.env`, `app/`, `config/`, `database/`, `storage/`, and `tools/`
are private application files.

For the default JSON driver, `APP_STORAGE_PATH` must point to durable storage
that the PHP process can read, write, and create files/directories in. Keep it
outside the actual public document root. If it points to a separate data
directory, seed that directory with the release's canonical `storage/` JSON
files before first use; preflight checks those files there as well as
writeability. The simplest shared-hosting layout keeps the package's `storage/`
directory in the private application tree. JSON uses locked atomic writes and
is a single-instance profile. SQLite is supported for mutable learner state
when `pdo_sqlite` is available; canonical content and other JSON collections
still use `APP_STORAGE_PATH`.

For SQLite, initialize the schema and import legacy mutable user state without
deleting the JSON source:

```sh
DB_DRIVER=sqlite DB_SQLITE_PATH=/absolute/path/to/boardprep.sqlite \
php tools/db-migrate.php
```

The migration command is idempotent and imports existing JSON-backed attempts,
weakness, flashcard state, and question-statistics records when present.

The mutable `attempts`, `weakness`, `flashcard_state`, and `question-statistics`
collections are routed to SQLite in this foundation; canonical questions, study material, taxonomy, and other curated collections
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
`php tools/Tests/ReleaseReadinessContractTest.php`, and
`php tools/doctor.php --release --json` from the repository root. Developer-route tests
require `APP_ENV=testing`.

The current operational patch release is BoardPrep v1.0.1; immutable content
remains based on v1.0.0. The concise operator handoff is in
[`docs/RELEASE_RUNBOOK_V1.md`](docs/RELEASE_RUNBOOK_V1.md). The existing
`tools/release.php` package builder creates a deterministic production-only
archive and embeds the product version and exact committed build revision in
its manifest. Before deployment, run `php tools/doctor.php --preflight --json`
and `php tools/doctor.php --smoke --json`.

The deployment profile is tracked in `config/deployment-profile.json`.
Mutable learner state is backed up separately from canonical content with
`php tools/backup.php --output=/path/to/backup`; restore is explicit and
supports a dry run. The lightweight `public/health.php` endpoint exposes only
liveness, readiness, version, and build identity.

Developer simulation: `php tools/doctor.php --simulate`. This runs the registered
scenario suite after the ordinary Doctor checks and is deeper than those checks.
Run it before human acceptance testing. The manual checklist is in
`docs/HUMAN_TESTER_READINESS.md`; ordinary Doctor runs intentionally do not run
the full persona suite.

### Browser acceptance

The Playwright acceptance harness runs the real PHP built-in server against an
isolated committed-tree fixture and covers mobile (390x844) and desktop
(1366x768) Chromium projects. It captures screenshots, checks console/page and
request failures, verifies rendered images, checks page-level overflow, and
drives representative quiz, study, flashcard, progress, and history journeys.

```sh
npm ci
npm run test:browser
```

On a workstation or CI runner with Chromium available, install the browser
once with `npm run test:browser:install`. Optional visual baselines can be
created or refreshed with `npm run test:browser:update-snapshots`; baseline
comparisons are enabled for that command with a conservative pixel threshold.
The local Termux/Android runtime reports `BROWSER_ACCEPTANCE=DEPENDENCY_MISSING`
without changing application data; CI installs Chromium and treats browser
failures as blocking.

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
