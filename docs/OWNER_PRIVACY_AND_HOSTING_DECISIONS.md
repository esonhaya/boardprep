# Owner privacy and hosting decisions

Complete and approve this sheet before a public launch. Blank fields are
unresolved; this document does not itself constitute legal approval. Do not
publish example values or promises that have not been verified with the actual
operator and provider.

## Owner decisions

| Decision | Owner-approved value/evidence |
|---|---|
| Operator identity (person or legal entity) | `OWNER TO COMPLETE` |
| Public learner contact method and monitored address | `OWNER TO COMPLETE` |
| Privacy-notice reviewer and approval date | `OWNER TO COMPLETE` |
| Application learner-state retention period | `OWNER TO DECIDE; include deletion/expiry behavior` |
| Server access/error log fields and retention | `VERIFY PROVIDER; include IP/user-agent if collected` |
| Backup frequency, location, access, and retention | `VERIFY PROVIDER; application deletion does not erase backups` |
| Hosting provider, account region, and data location | `VERIFY LIVE HOST` |
| HTTPS certificate, redirect, renewal, and proxy behavior | `VERIFY LIVE HOST; require HTTPS for public use` |
| Learner deletion limitation wording | `OWNER TO REVIEW against logs, backups, and provider copies` |
| Final privacy/terms approval | `NOT APPROVED` |

## Verified application behavior (not provider promises)

- BoardPrep does not require a learner account. A random learner identity is
  tied to a PHP browser session; it does not provide account recovery or
  cross-device synchronization.
- The application stores completed attempts and derived learning/progress
  state, and provides a session-scoped export and deletion control.
- The application cannot guarantee deletion of hosting access/error logs,
  external backups, or copies retained outside its own mutable storage.
- PHP session cookies are configured HttpOnly and SameSite=Lax. The Secure
  flag depends on HTTPS state being correctly passed to PHP by the live host.
- Actual log fields/retention, backup behavior, region, TLS, and provider
  handling remain unverified until the owner checks the selected hosting
  account and deployment.

## Release handoff

Attach provider documentation or owner observations for each provider-dependent
field. Update the production privacy notice only with approved, evidence-backed
values. Re-run the privacy and production readiness checks after configuration;
do not mark owner approval or live HTTPS as complete based on local tests.

See [human acceptance instructions](HUMAN_TESTER_READINESS.md) for the safe
same-device Android preview. It binds only to loopback and is not a public or
provider deployment test.

## Site-specific deployment verification template

Complete this table against the selected domain and hosting account. Attach
redacted provider evidence or dated owner observations. `NOT_VERIFIED` is the
initial state for every live-host field; local configuration does not change
it.

| Field | Required evidence / owner action | Value, evidence path, verifier, date | Status |
|---|---|---|---|
| Operator identity and legal entity | Owner supplies the operating person/entity and public learner contact route. | `OWNER TO COMPLETE` | `AWAITING_OWNER_APPROVAL` |
| Approved privacy terms | Owner/legal reviewer approves the exact deployed notice and its limits. | `OWNER TO COMPLETE` | `AWAITING_OWNER_APPROVAL` |
| Retention and deletion | Document attempt/session expiry, application deletion, log retention, backup expiration, and deletion exclusions; test the live deletion flow. | `OWNER TO DECIDE / VERIFY PROVIDER` | `AWAITING_OWNER_APPROVAL` |
| PHP runtime/extensions | Provider panel or `php -v`/`php -m` evidence for supported PHP (8.1+) and `json`, `session`; verify `pdo_mysql` only if MySQL is selected. | `VERIFY LIVE HOST` | `NOT_VERIFIED` |
| HTTPS and secure cookies | Verify redirect to HTTPS, valid/renewing certificate, proxy HTTPS detection, and a live session cookie with `Secure`, `HttpOnly`, and `SameSite=Lax`. | `VERIFY LIVE HOST` | `NOT_VERIFIED` |
| Session isolation | Verify separate browser sessions cannot read each other's attempt, progress, export, deletion, or recovery state; inspect the private PHP session path and locking behavior. | `VERIFY LIVE HOST` | `NOT_VERIFIED` |
| Filesystem permissions | Confirm web worker can write only required storage/session/log locations; storage, `.env`, backups, and repository internals are outside the document root and not served directly. Record owner/group and modes. | `VERIFY LIVE HOST` | `NOT_VERIFIED` |
| Storage driver and durability | Record JSON or MySQL configuration, path/database permissions, backup/restore test, concurrency assumptions, and disk monitoring. | `VERIFY LIVE HOST` | `NOT_VERIFIED` |
| Logs and third parties | Identify access/error log fields, analytics/CDN/processor involvement, regions, and retention. Add only approved disclosures to privacy terms. | `VERIFY PROVIDER; OWNER APPROVAL REQUIRED` | `NOT_VERIFIED` |
| Public contact and deletion request handling | Name the monitored contact, request verification process, response time the owner can meet, and deletion scope including backups/logs. | `OWNER TO COMPLETE` | `AWAITING_OWNER_APPROVAL` |

```text
DOMAIN=
HOSTING_PROVIDER_AND_REGION=
DEPLOYED_COMMIT=
PHP_VERSION_AND_EXTENSIONS_EVIDENCE=
HTTPS_AND_COOKIE_EVIDENCE=
SESSION_ISOLATION_EVIDENCE=
FILESYSTEM_PERMISSION_EVIDENCE=
RETENTION_AND_DELETION_APPROVAL=
OPERATOR_CONTACT_APPROVAL=
PRIVACY_TERMS_APPROVAL=
VERIFIER_AND_DATE=
OVERALL_STATUS=NOT_VERIFIED
```

No placeholder above is a promise to learners. Leave it unresolved until the
actual owner/provider supplies and approves evidence for the selected site.
