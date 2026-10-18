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
| Independent security reviewer and review date | `OWNER TO COMPLETE; INDEPENDENTLY_REVIEWED=PENDING` |
| Application learner-state retention period | `OWNER TO DECIDE; include deletion/expiry behavior` |
| Server access/error log fields and retention | `VERIFY PROVIDER; include IP/user-agent if collected` |
| Backup frequency, location, access, and retention | `VERIFY PROVIDER; application deletion does not erase backups` |
| Provider log/backup deletion commitments | `VERIFY PROVIDER; no application deletion guarantee` |
| Hosting provider, account region, and data location | `VERIFY LIVE HOST` |
| HTTPS certificate, redirect, renewal, and proxy behavior | `VERIFY LIVE HOST; require HTTPS for public use` |
| Learner deletion limitation wording | `OWNER TO REVIEW against logs, backups, and provider copies` |
| Account recovery and code expiry policy | `OWNER TO DECIDE; code-only recovery, preview codes do not automatically expire` |
| Final privacy/terms approval | `NOT APPROVED` |
| Public account launch authorization | `NOT AUTHORIZED; OWNER TO COMPLETE after all gates` |

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
- The application reads HTTPS state from `$_SERVER['HTTPS']`; it does not
  trust `X-Forwarded-Proto`. A reverse-proxy deployment must block direct
  origin access, strip client-supplied forwarded-protocol headers, and map
  TLS state from the approved proxy to PHP's HTTPS server variable. Verify the
  resulting cookie flags at the live site before public use.
- Actual log fields/retention, backup behavior, region, TLS, and provider
  handling remain unverified until the owner checks the selected hosting
  account and deployment.

## Release handoff

Attach provider documentation or owner observations for each provider-dependent
field. Update the production privacy notice only with approved, evidence-backed
values. Re-run the privacy and production readiness checks after configuration;
do not mark owner approval or live HTTPS as complete based on local tests.

## Bounded closure checklist for BP662-CAP-001

Use the site-specific table below as the single deployment record. Close the
capability only after the operator has supplied dated evidence for each item:

1. Name the real operating person or entity and a monitored public learner
   support/contact route.
2. Approve the exact privacy notice and record its reviewer and approval date.
3. Set application learner-record retention and describe deletion scope,
   including records kept in logs, provider backups, or external copies.
4. Identify the hosting provider, account region, data location, PHP runtime,
   and required extensions from the selected live account.
5. Verify live HTTPS redirect, certificate validity and renewal, proxy HTTPS
   detection, and the `Secure`, `HttpOnly`, and `SameSite=Lax` session cookie.
6. Verify storage/session paths and permissions, session isolation, backup
   access/retention, and the difference between app deletion and backup expiry.

Record a verifier, date, and redacted evidence reference for each result in the
table. Do not put account credentials, session tokens, or learner records in a
public report or CI artifact. Leave any item awaiting owner/provider input
unresolved; no local test can complete those external checks.

See [human acceptance instructions](HUMAN_TESTER_READINESS.md) for the safe
same-device Android preview. It binds only to loopback and is not a public or
provider deployment test.

## Account-preview boundary — Batch 693 update

Registration and persistent account profiles are available only in an
explicitly enabled nonproduction preview. Public production account routes
are absent, and `BOARDPREP_ACCOUNTS_ENABLED` defaults to false. The preview
uses usernames rather than unverified email addresses and issues hashed,
single-use recovery codes; it does not send or claim to send recovery email.
Anonymous records are never linked automatically, and no linking route is
implemented.

The preview enforces a 12–128 character password policy and uses PHP's native
password APIs. Login and sensitive password-confirmation actions are rate
limited. Recovery is code-only; no email delivery is configured or claimed.
No breached-password lookup is configured; decide whether to require it before
public account enablement.

Batch 693 adds an account-owned inventory, password-confirmed and CSRF-protected
deletion, staged idempotent application-record deletion, account export
manifest, and encrypted account-only backup with isolated restore tooling.
Deletion removes account-owned attempt, weakness, flashcard, preference,
password-hash, and recovery-digest records after ownership checks. Its minimal
tombstone retains pseudonymous account/learner IDs and completion status so
retries are idempotent and old sessions remain invalid. All
account sessions fail the account status/version lookup; physical PHP session
files follow the configured PHP session handler and garbage collection. That
deployment behavior has not been verified. Application deletion does not erase
provider logs, historical external backups, or other copies outside mutable
application storage. No application or backup retention period is set here.

The encrypted backup uses PHP ext-sodium Argon2id and
XChaCha20-Poly1305-IETF authenticated encryption. It requires a separately
held passphrase. Restore creates a new private destination and does not
activate or overwrite an account. The current Termux PHP runtime lacks
ext-sodium; local checks prove fail-closed behavior, while full cryptographic
round-trip evidence must come from the hosted Sodium-enabled CI run.

Public production account routes remain absent and public registration stays
disabled. Technical implementation and automated fixtures do not constitute
human acceptance, owner approval, provider verification, or independent
security review. `BP692-CAP-001` remains open in
`config/product-readiness.json` pending the decisions and live evidence below.

## Evidence status for account security

| Evidence class | Batch 693 status |
|---|---|
| `TECHNICALLY_IMPLEMENTED` | Inventory, staged deletion, export manifest, encrypted backup, isolated restore, account recovery/session hardening, and nonproduction-only routes. |
| `AUTOMATED_VERIFIED` | Isolated JSON and SQLite lifecycle tests; concurrent recovery; account HTTP/security; Sodium-enabled encrypted backup/restore; browser acceptance (60 passed, 0 failed); account explorer (32 scenarios); site explorer (100 routes, 290 actions, 10 boards); Doctor, preflight/smoke, and ten-board HTTP. Exact runs and commits are recorded in `docs/batches/batch-693.md`. |
| `HUMAN_VERIFIED` | `PENDING`; no live account-provider or production deployment review was performed. |
| `OWNER_APPROVED` | `PENDING`; operator identity, monitored contact, exact notice, application retention, deletion policy, and public launch authorization remain unanswered. |
| `PROVIDER_VERIFIED` | `PENDING`; hosting region, logs, backups, PHP session storage/GC, HTTPS/proxy trust, and provider deletion commitments remain unverified. |
| `INDEPENDENTLY_REVIEWED` | `PENDING`; no independent reviewer has assessed this implementation. |

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
