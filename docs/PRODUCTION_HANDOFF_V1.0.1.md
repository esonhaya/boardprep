# BoardPrep v1.0.1 production handoff

This handoff is intentionally provider-neutral. Local engineering is complete;
the remaining step is an actual server/domain deployment.

## Required server profile

- PHP 8.1 or newer; required extensions: json, session, and PDO.
- public/ is the document root and non-file routes reach public/index.php.
- Durable writable storage is required. JSON is a single-instance default;
  SQLite is supported for mutable state when pdo_sqlite is available.
- HTTPS is required for an internet-facing learner/admin deployment.
- CLI access is useful for preflight, smoke, backup, restore, and release
  checks but is not required if the host supplies an equivalent controlled
  process.

Canonical educational content ships in the release package. Mutable attempts,
progress, history, flashcard state, statistics, configuration, databases,
backups, logs, and sessions must remain outside the replaceable release files.

## Non-secret deployment input contract

Supply these values outside Git:

    HOST_TYPE=shared_hosting|vps|other
    HOST_ADDRESS=<hostname-or-ip>
    ACCESS_METHOD=ssh|sftp|control_panel|other
    REMOTE_DEPLOY_PATH=<absolute-or-host-relative-path>
    DOMAIN=<domain-or-local-url>
    HTTPS_STATUS=ready|pending|not_available
    PHP_VERSION=<server-version>
    EXISTING_PRODUCTION_DATA=yes|no
    ADMIN_SETUP=existing|needs-provisioning|not-applicable

Never place passwords, private keys, API tokens, or database secrets in this
file or in the repository. APP_DEBUG=false is mandatory in production.

## First deployment

1. Select the exact v1.0.1 artifact and record its SHA-256.
2. Configure .env from .env.example; keep it outside public/.
3. Prepare the writable persistent-storage path and correct permissions.
4. Back up any existing mutable data before replacing code.
5. Upload/extract the package with public/ as document root.
6. Run php tools/doctor.php --preflight --json and
   php tools/doctor.php --smoke --json where CLI is available.
7. Verify the HTTP smoke checklist below before accepting traffic.

On basic shared hosting, use a maintenance window: upload a new release
directory, preserve the existing mutable storage/configuration, switch the
document root or files together as the host permits, then smoke-test. On a
VPS, a release-directory/current-link strategy is optional; do not claim
zero-downtime unless the host actually provides it.

## Update and rollback

Before every update, back up mutable state with tools/backup.php and retain
the previous known-good release. If preflight, smoke, release identity,
storage integrity, authorization, or user-state preservation fails, stop
traffic and restore the previous release. Restore persistent data only when
compatibility requires it, using a verified backup and an explicit destination.
Keep backups outside the public document root with simple operator-managed
retention.

## Post-deploy smoke

Against the real URL verify:

- home/dashboard and board selection;
- study page and attempt start;
- one answer submission and result;
- progress, review, history, and resume;
- /health.php returns only safe liveness/readiness/version fields;
- admin access works only through the configured protected path;
- Developer Health is not public;
- no debug output, stack trace, filesystem path, or secret is visible.

Use a test learner only when necessary and remove its data afterward. Do not
use a destructive smoke action against real learner records.

## Admin and diagnostics

There is no default production admin credential. Provision an administrator
through the existing authenticated/configured procedure supplied for the
deployment; do not add a public bootstrap route. Detailed Developer Health is
an authenticated/developer diagnostic surface, while public/health.php is
the minimal uptime/readiness check.

## Operations baseline

Monitor HTTP availability, the safe health response, application error logs,
storage capacity, and backup success. Rotate application/server logs according
to the hosting provider's facilities. Trigger maintenance from events:
production bug, official scope/currentness change, new authoritative evidence,
security issue, or a supported feature request. Numeric question thinness is
not a deployment or release requirement.
