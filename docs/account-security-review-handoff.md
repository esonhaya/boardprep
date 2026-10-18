# Account security review handoff

## Review status and scope

This is a technical handoff for an independent reviewer. It is not an
independent audit, penetration test, owner approval, provider verification, or
launch authorization. Public production account routes remain absent even
when `BOARDPREP_ACCOUNTS_ENABLED=true`.

Review the account preview, server-side ownership checks, JSON and SQLite
storage adapters, account lifecycle locking, account deletion journal,
password/recovery/session transitions, encrypted backup envelope, isolated
restore transaction, CLI secret handling, and the adversarial test mapping
below. The canonical open owner/provider decisions are in
[OWNER_PRIVACY_AND_HOSTING_DECISIONS.md](OWNER_PRIVACY_AND_HOSTING_DECISIONS.md).

## Architecture and trust boundaries

- **Browser to application:** request fields, cookies, usernames, record IDs,
  route parameters, and learner IDs are untrusted. Account learner IDs come
  from the private account record reached through authenticated session state.
- **Application to account store:** a private JSON account file is outside the
  public root, protected with owner-only permissions and a file lock. Writes
  use a temporary file and rename. JSON and SQLite learner records use the
  common storage interface; learner mutations and account lifecycle changes
  serialize on a private lifecycle lock.
- **Application to learner collections:** `attempts`, `weakness`, and
  `flashcard_state` rows share storage with anonymous learner records. Exact
  `user_id` equality isolates an account's server-issued learner ID. Global
  question content and usage aggregates are outside account deletion scope.
- **Application to PHP sessions:** session authorization data is held by the
  configured PHP session handler. The app validates account ID, learner ID,
  account status, authorization version, idle time, and absolute time on each
  authenticated request. The app does not enumerate all PHP session files by
  account.
- **Backup operator boundary:** the account archive is ciphertext in a new
  owner-only file. A separately held passphrase is supplied to PHP through an
  inherited file descriptor. The repository, web root, symlinks, insecure
  directory permissions, and existing output files are rejected.
- **Restore boundary:** authenticated records are validated, staged in a new
  private directory, and committed by same-filesystem directory rename. This
  destination is not activated by the app and does not overwrite active data.
- **Hosting/provider boundary:** TLS termination, PHP session storage/GC,
  backups, access/error logs, filesystem ownership, region, support contact,
  and data retention require live provider and owner evidence.

## Data model and ownership

An account record has a random opaque `account_id`, a separate random
`account-learner-*` ID, normalized username, native PHP password hash,
preferences, `auth_version`, and SHA-256 recovery-code digests. Account-owned
quiz attempts, weakness summaries, and flashcard review state use that
learner ID in `user_id`. The account data inventory service reports exact
record counts, schema versions, storage mode, ownership field, read/write
paths, export/deletion behavior, backup inclusion, and retention boundary.

Performance summaries are computed from quiz attempts. Anonymous learner rows
use `learner-*` IDs. `storage/questions.json` question usage is a shared
aggregate and is not attributed to a single account. Historical
`database/attempts/attempts.json` copies lack account ownership fields and are
not current account read/write storage. See
[account-security-operations.md](account-security-operations.md) for the full
collection table.

## Threat model and implemented controls

| Threat | Impact | Controls in code | Residual verification |
|---|---|---|---|
| Account A reads or mutates B's learning records | Cross-account disclosure or corruption | Server-issued learner IDs; repository/service owner filters; lifecycle, export, and browser A/B fixtures | Independent review and live host isolation remain pending |
| Anonymous learner becomes linked to a signed-in account | Consent and ownership failure | Separate identity formats and session slots; no linking route; deletion fixture preserves anonymous C | No link feature is approved |
| CSRF or stale session triggers deletion/export/security change | Unauthorized data removal or disclosure | CSRF validation, current-password reauthentication, explicit `DELETE`, session/version checks, no-store responses | Live cookie/proxy behavior remains pending |
| Login/session fixation or password change leaves old session active | Account takeover | Strict cookie mode, cookie-only sessions, session ID/CSRF rotation, `auth_version`, idle/absolute expiry | PHP session handler and real cookie flags need provider verification |
| Recovery code replay or concurrent use | Unauthorized account recovery | 160-bit random codes, SHA-256 digests, `hash_equals`, bounded identifier/IP rate limits, lifecycle lock, consume-once digest removal | Recovery remains code-only; no delivery/identity provider |
| Login, recovery, or deletion races account data writes | Half-deleted or resurrected records | Shared lifecycle lock; writes require active account; deletion blocks login/writes before per-collection journal checkpoints | Storage semantics should be reviewed on the deployment filesystem/database |
| Deletion retry erases another learner or leaves records while reporting success | Data loss or false completion | Server identity lookup, exact owner match, delete-by-record ID after owner recheck, collection recount, minimal tombstone, idempotent retry | External backups/logs are expressly out of scope |
| Backup disclosure or modification | Credential/data theft or silent corruption | ext-sodium Argon2id, fresh salt and XChaCha nonce, AEAD header authentication, strict file modes, no overwrite, tag validation | Archive safety depends on operator passphrase handling and host security |
| Restore path traversal, collision, or interruption | Overwrite, path escape, or partial active restore | Absolute traversal-free paths, symlink checks, outside-root checks, empty destination, destination lock, private stage, atomic rename and rollback | Activation into an active app is intentionally unsupported |

## Authentication, password, and session configuration

Passwords use `password_hash` / `password_verify`. Argon2id uses 65,536 KiB,
three iterations, one lane when PHP provides it; otherwise the runtime's
`PASSWORD_DEFAULT` is used. The policy accepts 12–128 characters and enforces
the bcrypt 72-byte limit on bcrypt-only runtimes. No breached-password
screening service is configured.

Registration generates eight 160-bit random recovery codes, displays them at
registration/rotation only, and stores SHA-256 digests. Recovery and
reauthentication use constant-time digest/password verification. Recovery
checks a bounded hashed identifier bucket and IP bucket; a single shared
account lifecycle lock makes code consumption single-use under concurrency.
Login uses generic failure text and bounded identifier/IP buckets. Limits are
8 login attempts per identifier and 24 per IP per 15 minutes, 5 registrations
per IP per hour, and 10 recovery attempts per identifier/IP per 15 minutes.
Sensitive password reauthentication is limited to 8 account and 24 IP
attempts per 15 minutes. `REMOTE_ADDR` supplies the address bucket; forwarded
client-IP headers are not trusted.

Sessions rotate on login, logout, and password changes; recovery increments
`auth_version` to revoke previous sessions. Account session limits are 30
minutes idle and 12 hours absolute. Cookie configuration is HttpOnly,
SameSite=Lax, strict ID, and cookie-only. Secure and HSTS depend on PHP seeing
trusted `$_SERVER['HTTPS']`. There is no `X-Forwarded-Proto` trust in app
code. An approved proxy must block direct origin traffic, strip incoming
forwarded-protocol values, and map verified TLS state into PHP. Validate this
with the live provider.

## Deletion states and recovery behavior

The lifecycle is `active` → `deletion_requested` →
`deletion_processing` → `deletion_completed`, with `deletion_failed` as a
retryable locked state. An account submits CSRF, current password, and the
explicit confirmation. The lifecycle lock then prevents sign-in and new
account-owned writes. An atomic journal captures initial counts and
per-collection checkpoints. Retries re-scan current ownership and delete only
remaining rows. The service verifies zero owned rows before replacing the
credential/profile entry with a minimal idempotency/session-revocation
tombstone.

Failure before final verification never produces a completion response. The
current operation does not erase provider logs, PHP session files immediately,
historical external backups, exports, or other external copies. Owner/provider
retention and live deletion handling are unresolved. The tombstone currently
has no automatic expiry.

## Backup format, key derivation, and restore

The envelope format is `boardprep-encrypted-account-backup`, schema version
1. `sodium_crypto_pwhash` uses Argon2id13 and the runtime's interactive
resource limits with a fresh random libsodium salt. The 32-byte key encrypts
the JSON payload using
`XChaCha20-Poly1305-IETF` with a fresh random 24-byte nonce. The serialized
format, schema, timestamp, KDF parameters, salt, algorithm, and nonce form
authenticated associated data. KDF parameters must match the supported
bounded constants. Truncation, wrong passwords, authentication failure, and
unsupported schema fail closed. The plaintext payload includes credentials,
preferences, recovery digests, and owned learner records with a count/schema
manifest; session identifiers, CSRF tokens, rate-limit secrets, shared
aggregates, and anonymous records are excluded.

The archive password is not derived from a repository secret. The operator
must generate and store it separately. The CLI accepts one line from a
restricted inherited descriptor; it rejects password arguments and missing
secrets. Backup output is new-only, owner-only, written to a temporary file,
synchronized where PHP supports `fsync`, then hard-linked into place without
overwrite. Restore dry-run decrypts and validates with no destination writes.
Commit requires an explicit isolated-restore flag, private parent, empty
destination, and same-filesystem atomic rename. It does not activate an
account. A future activation procedure requires independent review and a
verified rollback plan.

## Failure handling

Lifecycle and backup tests inject a deletion interruption after one
collection, storage ENOSPC before credential temp write, a write/commit
permission failure after staging, corrupt credential JSON, archive truncation,
wrong password, modified tag, unsupported version, traversal, restore
collision, and restore interruption. Expected behavior is a non-success
status, no source mutation for backup/restore failures, unchanged prior
credentials for atomic credential failures, and a retryable deletion journal
for deletion failures. No test loads real accounts or deletes data from live
storage.

## Known unresolved risks

| Severity | Exploitability / condition | Status |
|---|---|---|
| High, conditional on deployment | If a public origin accepts HTTP or an untrusted proxy can make PHP believe TLS is active, a session cookie may be exposed or misflagged. | No live HTTPS/proxy/cookie evidence; public accounts stay disabled. |
| Medium | A previously authenticated PHP session file can remain on disk until its configured handler/GC removes it, although account status/version checks deny it. | Session path, GC, and provider retention are not indexed or verified. |
| Medium | Theft or weak user-generated backup passphrase enables offline guessing against an encrypted archive. | The CLI enforces minimum length and bounded KDF parameters; operator entropy/storage process needs independent review. |
| Medium | Loss of every recovery code leaves no verified recovery route. | No email provider or owner recovery policy is configured. |
| Medium | A recovery code that was copied or stored insecurely remains valid until consumed, rotated, or deleted. | Codes have no automatic expiry in the preview; owner recovery and expiry policy is pending, and public accounts stay disabled. |
| Medium | A pre-deletion archive can recreate credentials/data in an isolated destination if an operator restores it in a different store without the deletion tombstone. | Restore in the same configured store rejects completed-deletion tombstones and never activates/overwrites. External copies and cross-deployment restore remain governed by the pending owner/provider archive policy. |
| Low to medium | Passwords are not screened against a breached-password corpus. | Owner/security decision remains open. |
| Process blocker | No independent review, legal privacy approval, operator contact, provider commitment, or public launch authorization is documented. | All remain pending in the canonical owner decision sheet. |

No independent audit or penetration-test result is claimed. Reviewers should
classify any confirmed issue by severity, prerequisites, and practical
exploitability and attach exact reproductions to the review record.

## Security test mapping

| Area | Automated coverage |
|---|---|
| Password hashing, registration, session rotation, expiry, rate limits, atomic account concurrency | `tools/Tests/AccountSecurityTest.php`, `tools/Tests/AccountConcurrencyWorker.php` |
| HTTP routes, CSRF, generic failures, recovery replay, production feature gate | `tools/Tests/AccountHttpTest.php` |
| JSON and SQLite ownership, inventory/export, deletion journal/retry, recovery concurrency, failure injection, identity integrity | `tools/Tests/AccountLifecycleTest.php` and JSON/SQLite wrappers |
| Testing-only deletion journal recovery CLI | `tools/account-delete.php`, `tools/Tests/AccountDeletionCliTest.php` |
| Authenticated encryption, randomization, tag/password/truncation/schema rejection, restore rollback/collision | `tools/Tests/AccountBackupTest.php` |
| CLI secret descriptor enforcement, argument/missing-secret rejection, sanitized output, create/verify/restore dry-run | `tools/Tests/AccountBackupCliTest.php` |
| Anonymous records and flashcard/quiz ownership | `tools/Tests/AnonymousLearnerPrivacyHttpTest.php`, `tools/Tests/FlashcardHttpTest.php`, `tools/Tests/FlashcardIntegrationTest.php`, quiz result tests |
| Session cookie configuration and forwarded-header trust | `tools/Tests/SessionConfigurationTest.php`, `tools/Tests/SecurityTrustBoundaryTest.php` |
| Ten-board HTTP journey and production exclusion | `tools/Tests/TenBoardLearnerJourneyHttpTest.php`, Doctor production preflight/smoke |
| Hosted browser account and site journeys | `tests/browser/accounts/account-auth.spec.cjs`, Batch 693 explorer workflow evidence |

CI evidence is tied to exact commits. Application source changes ended at
`af27d0b5968bd90d72ea956d07127e2fbcb8825f`. The bounded site and account
explorer tested that source commit. The final browser acceptance and backup
CLI secret-handling checks tested `4d4434289690dd270c6f618cf6a1ff2caa7d5338`;
the browser result is registered as a verified exact-commit record. The
evidence/docs commit triggers a final Release Gates run on a documentation-only
descendant; its result is added below after completion.

```text
APPLICATION_SOURCE_COMMIT=af27d0b5968bd90d72ea956d07127e2fbcb8825f
QA_COMMIT=4d4434289690dd270c6f618cf6a1ff2caa7d5338
DOCUMENTATION_COMMIT=<PENDING_EVIDENCE_COMMIT>
CI_COMMIT=4d4434289690dd270c6f618cf6a1ff2caa7d5338
INITIAL_RELEASE_GATES_RUN=39089492703 (technical jobs passed; Product Readiness preceded latest browser evidence registration) https://github.com/esonhaya/boardprep/actions/runs/39089492703
BROWSER_ACCEPTANCE_RUN=39089492279 PASS; 60 passed, 0 failed, 8 skipped https://github.com/esonhaya/boardprep/actions/runs/39089492279
SITE_EXPLORER_RUN=39088111773 PASS; 100 routes, 290 actions, 10 boards https://github.com/esonhaya/boardprep/actions/runs/39088111773
AUTH_EXPLORER_SCENARIOS=32 pass, 0 fail, on site explorer run 39088111773
FINAL_RELEASE_GATES_RUN=<PENDING_EVIDENCE_COMMIT_RUN>
INDEPENDENT_REVIEWER=<PENDING>
INDEPENDENT_REVIEW_STATUS=PENDING
PRODUCT_READINESS=BLOCKED; owner/provider privacy and launch decisions remain pending
```

## Owner/provider handoff still required

The owner must supply the legal operator identity, monitored learner contact,
approved privacy wording, application retention/deletion policy, recovery and
code-expiry policy, backup retention and access policy, and public-launch decision. The
provider must supply hosting location/region, PHP/Sodium availability, live
HTTPS/proxy and cookie evidence, filesystem and session paths/permissions,
session GC behavior, backup/log retention, and deletion commitments. No
retention value or approval date is supplied by this handoff.
