# BoardPrep v1.0.1 production handoff

This describes the retained v1.0.1 package. No hosting account, domain, or TLS
endpoint has been provisioned or tested.

## Artifact identity

- Archive: `BoardPrep_v1.0.1_d18db80.zip`
- Build commit: `d18db80238d689e8198b2db468e47953f8d96d21`
- SHA-256: `61e80681f14713be2f878f1233aba2289225aea10071ecaa927b4a866084baef`
- Canonical questions: 8,962; the package excludes mutable learner state,
  `.env`, logs, and Git metadata.

The release tag and archive remain immutable. Compare the downloaded archive's
SHA-256 before extraction.

## Hosting contract

- PHP 8.1+ with `json`, `session`, `PDO`, and `mbstring`; PHP memory limit of
  512 MB. `pdo_sqlite` is optional for SQLite mutable-state storage.
- JSON is the default single-instance profile. SQLite supports mutable learner
  state while canonical content and other collections remain JSON. MySQL is
  not claimed by this release.
- Prefer `public/` as document root. Fixed `public_html` hosts require the
  private-app shim and assets-only copy described in
  [`RELEASE_RUNBOOK_V1.md`](RELEASE_RUNBOOK_V1.md).
- Keep the entire package, `.env`, canonical and mutable storage, SQLite,
  backups, and logs outside the actual public document root. If
  `APP_STORAGE_PATH` is separate, seed it with the package's canonical JSON
  files before first use.
- SSH is optional. Without it, server-side Doctor, backup, restore, and CLI
  smoke checks are unavailable; local checks do not verify provider settings.

The retained archive's embedded Doctor predates the Batch 661 `mbstring` and
`PUBLIC_DATA_ISOLATION` checks. Batch 661's source Doctor supports
`--preflight --root=/path/to/extracted/package` without modifying that package.
The exact command and limits are in the runbook. No public install or
diagnostic endpoint is required.

## Deployment record

Record only non-secret values in the deployment worksheet:

    HOST_TYPE=shared_hosting|vps|other
    ACCESS_METHOD=ssh|sftp|control_panel|other
    REMOTE_DEPLOY_PATH=<account path>
    DOMAIN=<domain>
    HTTPS_STATUS=ready|pending|not_available
    PHP_VERSION=<server version>
    EXISTING_PRODUCTION_DATA=yes|no
    ADMIN_SETUP=not-applicable-no-production-admin-in-v1.0.1

Never record passwords, private keys, tokens, or database secrets here.

## Admin boundary

The production v1.0.1 app has no learner login, production admin route, or
first-admin bootstrap flow. Developer authoring and Doctor HTTP routes are
disabled in production. Do not expose them by running the public app in
development mode. If production content administration is required, this
release does not provide it.

Follow [`RELEASE_RUNBOOK_V1.md`](RELEASE_RUNBOOK_V1.md) for provider selection,
layout, deployment, backup, rollback, HTTP checks, and troubleshooting.
