# BoardPrep v1 deployment runbook

This is the operator path for the immutable `BoardPrep v1.0.0` content release
and the `BoardPrep v1.0.1` production-hardening patch. It describes the
repository's actual PHP web-app
deployment model; it does not claim that a hosting provider, domain, or TLS
certificate is provisioned.

## Requirements

- PHP 8.1 or newer with `json`, `session`, `PDO`, and `mbstring`.
- A PHP memory limit of at least 512 MB, or provider settings that allow the
  application to set that limit.
- `public/` as the web-server document root and a rewrite/front-controller
  rule sending non-files to `public/index.php`.
- Durable readable/writable private storage. JSON is the default single-
  instance profile; SQLite is supported for mutable state when `pdo_sqlite`
  is available. MySQL is not claimed by this release.
- `APP_ENV=production`, `APP_DEBUG=false`, a valid `APP_TIMEZONE`, and the
  selected `DB_*` settings from `.env.example`.
- HTTPS for any internet-facing deployment that carries sessions or learner
  data. Pass the original HTTPS state to PHP so secure cookies are enabled.

The complete machine-readable profile is
`config/deployment-profile.json`. Keep `.env`, databases, backups, logs, and
mutable state outside the actual public document root. The runtime profile
also requires `mbstring`, which is used by learner-facing pages.

## Hosting selection checklist

Choose a provider only if each minimum is confirmed:

- PHP 8.1+, `json`, `session`, `PDO`, `mbstring`, and a 512 MB memory limit.
- A domain document root set to `public/`, or a private application directory
  plus a `public_html` front-controller shim and copied public assets.
- Apache rewrite support with `.htaccess` overrides, or provider-managed
  equivalent routing to `index.php`.
- A durable directory outside the public root that the PHP worker can read,
  write, and create directories/files in; a writable PHP session path.
- HTTPS with the original HTTPS state passed through to PHP.
- A control panel that can select PHP/extensions, set environment values or
  preserve a private `.env`, upload/extract files, and make protected backups.

Recommended capabilities are `pdo_sqlite`, a per-domain PHP setting/profile,
SSH/SFTP or a controlled PHP CLI task, separate addon-domain roots, off-account
backups, and provider access/error logs. The app works without SSH, but that
removes server-side Doctor preflight, command-line backups/restores, and CLI
smoke checks.

Ask the provider: Can the domain root point to a subdirectory? If not, can PHP
include files from a sibling private directory? Are `mod_rewrite` and required
`.htaccess` overrides enabled? Can PHP 8.1+ load `mbstring` and use at least
512 MB? Can the PHP worker write to private storage and its session directory?
Does HTTPS reach PHP as `HTTPS=on` behind the proxy? Can addon domains have
separate roots and per-site environment/database credentials? What are inode,
storage, CPU, process, and backup limits? Can a private backup be downloaded
without making it web-accessible?

Reject plans that require publishing the package root, placing private state
under `public_html`, enabling production debug, or making private directories
world-writable. Shared hosting gives projects under one account access to the
same account permissions; it is not strong process isolation.

## Configure and preflight

Start from the immutable v1.0.1 package, or another committed package built by
`php tools/release.php`. Copy `.env.example` to a private `.env` beside the
application root, set production values, and keep it outside the public root.
For preflight against a nonstandard `public_html` directory, set optional
`APP_PUBLIC_PATH` to that actual filesystem path. It affects preflight only.
Then run:

```sh
php tools/doctor.php --preflight --json
php tools/doctor.php --smoke --json
```

Preflight returns `PASS`, `WARN`, or `FAIL`. It checks runtime requirements,
the configured storage, release identity, canonical JSON, and that writable
storage, SQLite, and `.env` do not overlap the configured public root. A `FAIL`
must be corrected before serving traffic. Smoke is a fast, non-destructive
application/bootstrap check.

The retained v1.0.1 archive is immutable: its embedded Doctor and profile
predate the Batch 661 `mbstring` and `PUBLIC_DATA_ISOLATION` checks. A `PASS`
from that archive does not verify those two checks. Confirm `mbstring` in the
provider panel and follow the private-path layout below; the Batch 661 source
preflight checks path isolation when run against its own tree. To run the
Batch 661 preflight against an extracted v1.0.1 package without modifying it,
run the Batch 661 Doctor source with `--root`:

```sh
APP_PUBLIC_PATH=/absolute/path/to/public_html \
php /path/to/BoardPrep-batch661/tools/doctor.php \
  --preflight --root=/absolute/path/to/extracted/BoardPrep_v1.0.1_d18db80 --json
```

`--root` is a CLI-only preflight option in the Batch 661 source. Both paths
must exist on the machine where the command runs; a local result still cannot
verify remote hosting settings or permissions.
The heavier maintenance commands remain:

```sh
php tools/doctor.php --health --portfolio --json
php tools/doctor.php --release --json
```

Without SSH, run preflight and smoke locally against the exact extracted
package before upload. This verifies the package on the local PHP runtime,
not the provider's PHP settings, paths, permissions, rewrite configuration,
or session directory. Use Batch 661 Doctor `--root` for the updated extension
and path-isolation checks; the archive's embedded Doctor remains unchanged.
In the hosting panel, select PHP 8.1+, enable the
required extensions, set `APP_ENV=production` and `APP_DEBUG=false`, and
confirm private storage permissions. Do not upload a public diagnostic or
installer script. After upload, use only the safe health and learner HTTP
checks below. A no-SSH deployment cannot claim remote Doctor preflight or CLI
backup/restore verification.

The public `public/health.php` endpoint is intentionally lightweight. It
returns only liveness/readiness and release identity; detailed Doctor output
stays in CLI/developer surfaces.

## Backup

Before first deployment, update, or migration, stop/quiesce writes and record
the current release commit. Back up mutable state only:

```sh
php tools/backup.php --output=/secure/path/boardprep-backup
```

The backup contains a manifest and hashes for JSON mutable collections or the
SQLite database. Canonical questions, taxonomy, and study content are supplied
by the release package and are deliberately excluded from the mutable backup.
Keep the backup outside the application and protect its permissions.

Without SSH, use the provider's private account-backup facility or download a
protected copy of `.env`, the private `storage/` state, and the SQLite file if
used. Store an additional encrypted copy outside the hosting account. A
provider account backup is not verified until a restore has been tested in a
separate private staging directory.

Verify or plan a restore without touching live state:

```sh
php tools/restore.php \
  --input=/secure/path/boardprep-backup \
  --destination=/temporary/restore-check \
  --dry-run
```

An actual restore requires an explicit destination and, for a live destination,
`--confirm-live`; stop the application first. Never restore over current data
without a verified backup and an explicit change decision.

## Deploy

1. Record the previous known-good commit and back up persistent state.
2. Prepare the domain or subdomain, point DNS to the provider, enable HTTPS,
   and confirm PHP receives the HTTPS state for secure session cookies.
3. Upload and extract the exact package into a private application directory.
   Keep the retained release zip outside the document root too.
4. Prepare durable writable storage outside the document root. The package's
   `storage/` contains private canonical content as well as the default place
   for mutable JSON. Preserve it and `.env` during updates. If using a separate
   `APP_STORAGE_PATH`, copy the package's canonical `storage/` JSON files into
   that private directory before first use; preflight expects them there.
5. Set production configuration and permissions. Prefer owner-only access; use
   the narrowest owner/group permissions the PHP worker supports. Do not use
   world-writable directories. The PHP process needs write access to the
   configured JSON storage, SQLite parent directory if selected, and its
   session-save path.
6. Run remote preflight/smoke/backup CLI commands when SSH or a controlled PHP
   CLI task exists. Without SSH, do the package preflight locally, confirm
   server PHP/extensions and permissions in the panel, then use the safe HTTP
   checks below. This does not verify server-side CLI results.
7. Accept traffic only after route, health, session, and sensitive-file checks
   pass. Use a maintenance window for replacement; no zero-downtime claim is
   made.

### `public/` as document root

This is the preferred mode when the provider lets the domain root target the
package's `public/` directory. Keep the package root, `.env`, `storage/`, tools,
logs, and backups outside that document root. Configure Apache or the provider's
equivalent to serve existing public files and route other requests to
`index.php`. Do not set the document root to the package root.

### Fixed `public_html` document root

Use this mode only when the host permits PHP to include files from a private
sibling directory and permits routing rules. A safe account layout is:

```text
/home/account/boardprep-private/   complete extracted package, .env, storage/
/home/account/public_html/         index.php, health.php, .htaccess, assets/
```

Put only the following under `public_html`: these two small PHP shims, the
Apache routing file, and a copy of `boardprep-private/public/assets/`. Do not
copy the full package or `public/` directory. Do not use symlinks; copy assets
as ordinary files. Set the private path in both shims to match the actual
account layout, and keep PHP error display disabled in the hosting panel.

`public_html/index.php`:

```php
<?php
declare(strict_types=1);
$privateRoot = realpath(__DIR__ . '/../boardprep-private');
if ($privateRoot === false || !is_file($privateRoot . '/public/index.php')) {
    http_response_code(503);
    header('Content-Type: text/plain; charset=UTF-8');
    exit('Service unavailable.');
}
require $privateRoot . '/public/index.php';
```

`public_html/health.php` uses the same guard and private-root expression, then
requires `$privateRoot . '/public/health.php'`:

```php
<?php
declare(strict_types=1);
$privateRoot = realpath(__DIR__ . '/../boardprep-private');
if ($privateRoot === false || !is_file($privateRoot . '/public/health.php')) {
    http_response_code(503);
    header('Content-Type: text/plain; charset=UTF-8');
    exit('Service unavailable.');
}
require $privateRoot . '/public/health.php';
```

The generic failure response does not print the configured path. The private
app's front controller keeps its own `__DIR__` paths, so it boots from outside
the document root.

For Apache with rewrite overrides enabled, the document-root `.htaccess` can
use:

```apache
Options -Indexes
RewriteEngine On
RewriteCond %{REQUEST_FILENAME} -f [OR]
RewriteCond %{REQUEST_FILENAME} -d
RewriteRule ^ - [L]
RewriteRule ^ index.php [QSA,L]
```

If the provider disallows `Options -Indexes`, ask it to disable listing at the
vhost level. If `.htaccess` or rewrite rules are unavailable, require provider
managed equivalent routing; otherwise this layout is unsupported. For Nginx,
the provider must configure the equivalent `try_files` route to `index.php`.
An Apache or Nginx configuration cannot be confirmed by the local PHP server.

Map each domain/subdomain to its own document root. For unrelated PHP projects,
keep each private code/data/config directory separate, use distinct database
credentials and backup sets, use separate writable directories and log files,
and avoid sharing cookie domains. BoardPrep uses PHP's default `PHPSESSID` name
and host-only cookie scope; do not mount it under a shared same-host path with
another app that uses the same cookie name. Shared account ownership still
allows account-level filesystem access; shared PHP users, credentials, session
storage, logs, backups, inode quotas, CPU, and process limits can create
cross-project exposure or contention. Directory separation is not strong
process isolation.

The package is built from `git archive`, so uncommitted files, `.git`, test
fixtures, local sessions, and development caches cannot enter it. The package
manifest identifies `product_version`, `release_tag`, and `build_commit`.

For the first live deployment, use the exact package and non-secret input
contract in `PRODUCTION_HANDOFF_V1.0.1.md`. Do not deploy from a dirty
checkout. The `v1.0.0` tag is immutable; `v1.0.1` is a backward-compatible
operational patch and does not change question content.

## Post-deploy smoke

Against the real URL, verify:

- home/dashboard and board selection load;
- study loads and one attempt starts;
- one answer submits once and a result appears;
- review, progress, history, and continue/resume work;
- `/health.php` returns a safe response;
- `/developer` and `/api/doctor.php` are not publicly exposed in production;
- `/.env`, `/storage/questions.json`, `/config/app.php`, `/tools/doctor.php`,
  and `/RELEASE_MANIFEST.json` are not publicly readable;
- no debug output, stack trace, filesystem path, or secret is visible.

These HTTP checks require a real server/domain/TLS target and are not claimed by
the local production-like audit. A first deployment is accepted only after
the same checklist succeeds against the real URL.

## Rollback

Stop or quiesce the application, switch code to the previous recorded
known-good release, and rerun preflight and the smoke checklist. Restore data
only when state/schema compatibility requires it, using the verified backup
and an explicit restore decision. Do not use an unreviewed destructive reset.

For a no-symlink host, keep the previous private release directory and change
the shim's private-root path during the maintenance window. Preserve the
current `.env` and writable state directory. Confirm the rollback build
identity at `/health.php` and repeat the learner smoke before reopening.

## First-admin setup and limitations

The v1.0.1 production app has no learner login, production admin route, or
first-admin bootstrap flow. Developer authoring and Doctor routes are disabled
in production. No first-admin action applies to this release; do not switch a
public site to `APP_ENV=development` to obtain those tools. If production
content administration is a requirement, this release is not sufficient for
that requirement.

## Troubleshooting

- Blank page or HTTP 500: keep `display_errors=Off`; inspect the provider's
  private PHP error log. Confirm PHP version/extensions, 512 MB memory, and
  private storage/session write permissions.
- Routes return 404: confirm the domain root, `.htaccess` override/rewrite, or
  provider-managed `try_files` rule sends non-files to `index.php`.
- Health returns 503: verify the private root path in the shim, production
  environment values, required canonical JSON in `APP_STORAGE_PATH`, and that
  the configured storage path is outside the actual document root.
- Session does not persist or secure cookies are absent: enable HTTPS and
  ensure the proxy passes `HTTPS=on` to PHP; confirm the PHP session directory
  is writable and the host-only cookie is not shared through a parent domain.
- SQLite fails: confirm `pdo_sqlite`, the private database path, and write
  access to its parent directory. JSON mode does not require SQLite.
- If the provider cannot supply the required PHP profile, private writable
  paths, HTTPS, or route fallback, do not deploy there; choose a compatible
  host or a VPS.

The release supports shared hosting with these conditions; a provider that
cannot meet them requires a different host, typically a VPS. The local
simulations do not verify any real provider, Apache configuration, or account
isolation.

## Updates and maintenance

For each update: backup, fetch/checkout the intended release, install required
dependencies, run migrations only when documented, run preflight, run smoke,
then serve traffic. Retain the prior release reference until the new release
is accepted. Do not claim zero-downtime deployment; this project documents a
safe simple deployment path.

Use event-driven maintenance categories: `BUGFIX`, `CURRENTNESS_UPDATE`,
`OFFICIAL_SCOPE_UPDATE`, `EVIDENCE_BACKED_CONTENT`, `FEATURE`, `MAINTENANCE`,
`PERFORMANCE`, and `SECURITY`. Question expansion is evidence-backed only;
numeric thinness is not a release requirement. Architecture visual questions
remain a post-release feature backlog.

Keep backups outside the public document root; retain the previous release
directory or archive until the new one passes smoke. Simple shared-hosting
replacement during a maintenance window is supported only when the provider
meets the checklist above. A release-directory strategy works without
symlinks; zero-downtime is not claimed.
