# BoardPrep v1 Release Runbook

This runbook is the short operator path for the BoardPrep v1 release
candidate. The existing package builder labels the candidate `RC1.1`; the
committed Git revision is the authoritative release identity.

## Before release

Run from a committed checkout:

```sh
git status --short
git rev-parse HEAD
php tools/Tests/RuntimeConfigurationTest.php
php tools/Tests/ReleaseReadinessContractTest.php
php tools/doctor.php --health --portfolio --json
php tools/doctor.php --release --json
php tools/Tests/ReleasePackageTest.php
```

Repository Health may report REVIEW/WARN content planning signals, but the
release command must report `GATE=PASS` with `P0=0`. Do not release from a
dirty tree. Record the commit and retain the previous known-good release
reference.

## Configuration

Copy `.env.example` to `.env` outside version control. Use:

- `APP_ENV=production`
- `APP_DEBUG=false`
- a valid `APP_TIMEZONE`
- a durable, readable, writable `APP_STORAGE_PATH` for JSON mode; or the
  configured SQLite path after `php tools/db-migrate.php`

Keep `.env`, runtime databases, mutable learner state, and storage backups
outside `public/`. Never expose the repository root through the web server.

## Backup and deployment

1. Quiesce the application and back up the persistent storage directory or
   SQLite database.
2. Record the candidate commit and the previous release commit.
3. Deploy the committed tree with `public/` as the document root. Route
   non-file requests to `public/index.php`.
4. For a local smoke check only, use the documented built-in server command in
   `README.md`; it is not a production hosting recommendation.

## Post-deploy smoke checks

Verify `/`, `/exams`, `/dashboard`, one study/quiz flow, `/history`, and a
missing route. In production, `/developer` and `/api/doctor.php` must return
404. Confirm that a learner can start, submit, review, and resume an attempt.

## Rollback

Stop the application, restore the previous committed release and the matching
persistent-storage backup, then rerun the smoke checks. Do not overwrite
canonical content or migrate mutable state forward without a recorded backup.

Human browser/mobile acceptance remains separate evidence; automated Doctor
and release checks do not claim visual or comprehension acceptance.
