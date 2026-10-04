# BoardPrep v1 deployment runbook

This is the operator path for the immutable `BoardPrep v1.0.0` content release
and the `BoardPrep v1.0.1` production-hardening patch. It describes the
repository's actual PHP web-app
deployment model; it does not claim that a hosting provider, domain, or TLS
certificate is provisioned.

## Requirements

- PHP 8.1 or newer with `json`, `session`, and `PDO`.
- `public/` as the web-server document root and a rewrite/front-controller
  rule sending non-files to `public/index.php`.
- Durable readable/writable storage. JSON is the default single-instance
  profile; SQLite is recommended for mutable state where available.
- `APP_ENV=production`, `APP_DEBUG=false`, a valid `APP_TIMEZONE`, and the
  selected `DB_*` settings from `.env.example`.
- HTTPS for any internet-facing deployment that carries sessions or learner
  data. Pass the original HTTPS state to PHP so secure cookies are enabled.

The complete machine-readable profile is
`config/deployment-profile.json`. Keep `.env`, databases, backups, logs, and
mutable state outside `public/`.

## Configure and preflight

Start from a clean committed checkout or an archive created by
`php tools/release.php`. Copy `.env.example` to an untracked `.env`, set the
production values, then run:

```sh
php tools/doctor.php --preflight --json
php tools/doctor.php --smoke --json
```

Preflight returns `PASS`, `WARN`, or `FAIL`. A `FAIL` must be corrected before
serving traffic. Smoke is a fast, non-destructive application/bootstrap check.
The heavier maintenance commands remain:

```sh
php tools/doctor.php --health --portfolio --json
php tools/doctor.php --release --json
```

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
2. Deploy the committed package/checkout generated from `tools/release.php`.
3. Prepare the documented writable storage path without replacing canonical
   content with local test data.
4. Install only documented runtime dependencies and configure the environment.
5. Run preflight and smoke before accepting traffic.
6. Start/configure the PHP application behind the chosen web server with
   `public/` as its root. The PHP built-in server is for local smoke testing
   only, not a production hosting recommendation.

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
- admin access is protected and permitted maintenance works;
- `/health.php` returns a safe response;
- `/developer` and `/api/doctor.php` are not publicly exposed in production;
- no debug output, stack trace, filesystem path, or secret is visible.

These HTTP checks require a real server/domain/TLS target and are not claimed by
the local production-like audit. A first deployment is accepted only after
the same checklist succeeds against the real URL.

## Rollback

Stop or quiesce the application, switch code to the previous recorded
known-good release, and rerun preflight and the smoke checklist. Restore data
only when state/schema compatibility requires it, using the verified backup
and an explicit restore decision. Do not use an unreviewed destructive reset.

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
replacement during a maintenance window is supported. A release-directory and
symlink strategy is optional, not required, and zero-downtime is not claimed.
