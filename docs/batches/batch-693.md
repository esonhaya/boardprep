# Batch 693 — account data lifecycle and encrypted recovery

## Scope and release boundary

Batch 693 completes the nonproduction account-security foundations from Batch
692. It implements account-owned data inventory, confirmed staged deletion,
export parity, encrypted account backup, isolated restore, and recovery/session
hardening. Public production registration and account routes remain disabled.
No owner privacy approval, provider verification, independent review, or
public launch approval is inferred from code or automated tests.

Operational procedures and the collection map are in
[account-security-operations.md](../account-security-operations.md). The
independent reviewer package is in
[account-security-review-handoff.md](../account-security-review-handoff.md).
The canonical owner/provider decision record remains
[OWNER_PRIVACY_AND_HOSTING_DECISIONS.md](../OWNER_PRIVACY_AND_HOSTING_DECISIONS.md).

## Protected baseline

| Item | Captured value |
|---|---|
| Expected `HEAD` / `origin/main` | `db478533e7bed7da09b7c4604d69c0bda24d8d04` |
| HayaDoctor submodule | `dc4fcda730d9a922f726593746e34ca0c9c0e890` |
| Canonical questions | 8,962 |
| Question identity SHA-256 | `44e07c081a5a23dd7cf195616db41ee492d72e5ea40bbff3d8229b6b4bb4e882` |
| Question-content hash excluding usage/timestamps | `432a7979f798029a8da2f5d600746abb145b5df7c3982fe8a54ba328f72b1582` |
| Question file hash at batch start | `26abd43caaefa06f5536ae93b3cc09ed9f22b0fc76803cfc7afc63b4e005d237` |
| Question usage delta | Existing 19-row delta in `storage/questions.json`; preserved and excluded from commits |
| Batch 692 Doctor baseline | P0=0, P1=0, P2=1, P3=2 |
| Batch 692 browser baseline | 60 PASS / 0 FAIL / 8 SKIP |
| Batch 692 account explorer baseline | 23 scenarios PASS |
| Batch 692 site explorer baseline | 100 routes, 290 actions, 10 boards |
| Existing stashes | `stash@{0}` `5a7ea638911d71dacb4150e116fe915b808d633d`; `stash@{1}` `9e02b18188be87d6f0d512e0d82562c4f419fd25` |
| Storage free at start | 6,789,280 KiB (~6.47 GiB) |
| Storage free at final verification | 6,381,672 KiB (~6.09 GiB), above 1.5 GiB floor |

The exact initial worktree, protected-file hashes, question hashes, storage
free space, and stashes were captured in the mode-0600 file
`/data/data/com.termux/files/usr/tmp/boardprep-batch-693-baseline.json`.
It records the known question usage delta and pre-existing untracked files.
The pre-existing worktree changes, both stashes, historical archives, owner
acceptance feedback, and untracked drawings/content are preserved. No real
account or learner storage was loaded by the tests.

## Implementation

- `AccountDataInventoryService` maps exact learner ownership, per-collection
  storage modes and paths, read/write services, exports, deletion, backup,
  and retention boundaries. It distinguishes account credentials and
  preferences, PHP session authorizations, derived analytics, shared question
  usage, anonymous rows, and inactive historical paths.
- Account deletion requires the authenticated session, CSRF, current-password
  reauthentication, and explicit `DELETE` confirmation. A shared private lock
  serializes account login/recovery/profile changes and account-owned quiz,
  weakness, and flashcard writes against deletion.
- Deletion transitions through request, processing, retryable failure, and
  completed states. A journal checkpoints each owned collection; exact-owner
  rows are deleted and verified before credentials/profile values are removed
  and a minimal idempotency/session-revocation tombstone is committed.
- `tools/account-delete.php` lists only pseudonymous pending IDs and retries
  only a previously confirmed deletion in `APP_ENV=testing`; it requires the
  account ID twice and is covered by a disposable synthetic fixture test.
- Account export includes account preferences, quiz attempts, weakness
  summaries, and flashcard review state with a schema/count manifest and
  explicit excluded-secret/deletion-limitations fields. Reauthentication is
  required; anonymous export remains separate.
- The account backup format uses ext-sodium Argon2id13 key derivation and
  XChaCha20-Poly1305-IETF authenticated encryption with a fresh salt and nonce
  per archive. The manifest uses one consistent credential-profile collection
  inventory, rejects unsupported crypto/schema parameters, and writes only
  new owner-only archives outside the repository/web root. Backup creation
  stays under the account lifecycle lock through atomic archive publication.
- Restore authenticates before validation, defaults to dry-run, rejects
  collisions and unsafe destinations, stages private JSON, and atomically
  publishes only a new isolated destination. It does not activate restored
  accounts or overwrite active records; a completed deletion tombstone in the
  configured source store blocks restoration of that account's old archive.
- Recovery code consumption is serialized and one-time; rotation replaces all
  previous digests. Password recovery increments the auth version and revokes
  stale sessions. Account sessions continue to require active status, matching
  server-issued identity, and the current password version.
- Browser account journeys now cover practice history, flashcard review,
  export, encrypted backup/restore fixture, A/B/anonymous isolation, and
  deletion of a separate synthetic D account. Artifact capture masks learner
  content and excludes passwords, recovery codes, passphrases, and cookies.

The current Termux PHP runtime has no ext-sodium. Local backup commands and
tests therefore fail closed with `DEPENDENCY_MISSING`. The hosted PHP 8.3
Sodium-enabled suite passed the authenticated encryption round trip, wrong
passphrase/tag/truncation/schema checks, isolated dry-run and commit restore,
collision rejection, and interrupted-restore rollback on commit `3efa6aa`.
The hosted backup CLI secret-handling suite also passed on commit `4d44342`.

## Validation record

Local isolated tests completed during implementation:

| Test | Result |
|---|---|
| Account lifecycle JSON | PASS — inventory/export, deletion interruption/retry/idempotence, account A/B/anonymous/global preservation, recovery concurrency/replay, injected storage failures |
| Account lifecycle SQLite | PASS — same lifecycle and isolation cases |
| Encrypted backup local fail-closed check | PASS — missing ext-sodium returns `DEPENDENCY_MISSING` |
| PHP syntax for backup/restore/inventory/repository/CLI | PASS |
| Browser explorer JavaScript syntax | PASS |
| Account security HTTP/session suites | PASS — account security, HTTP boundary, session configuration, and trust-boundary jobs on `3efa6aa`; all hosted security/privacy jobs passed again on `4d44342` |
| Hosted encrypted backup/restore suite | PASS — Sodium-enabled authentication, wrong-password/tag/truncation/schema rejection, private-path, atomic restore and interruption tests; Release Gates run `39089492703` |
| Backup/restore CLI secret handling | PASS — hosted descriptor, missing-secret, visible-argument rejection, create/verify, restore dry-run, and sanitized-output coverage on `4d44342` |
| Hosted browser acceptance | PASS — 60 passed, 0 failed, 0 flaky, 8 skipped, 0 not run on `4d44342`; exact-commit run `39089492279`, artifact `12122123398`, registered and verified |
| Hosted account and site explorers | PASS — 32 account scenarios; site explorer 100 routes, 290 actions, 10 boards on application source commit `af27d0b`; later application source remained unchanged |
| Doctor release | PASS — Release Gates run `39089492703` on exact QA commit `4d44342` |
| Production preflight/smoke and ten-board HTTP | PASS — Release Gates run `39089492703` on `4d44342` |
| Product Readiness | BLOCKED — final evidence-aware run `39090027198`: `privacy_terms=no`, one in-scope feature incomplete, three mandatory evidence items not passed. Owner/provider and public-launch decisions remain pending. |

The final evidence-aware Release Gates run `39090027198` tested commit
`f51abb7d6a9cea71d240c0e54b152d5fe95ea353`. Doctor, every security/privacy
matrix job, production preflight/smoke, and the ten-board HTTP journey passed.
The workflow conclusion is `failure` solely because the formal Product
Readiness gate correctly rejects the missing owner/provider approvals and
remaining evidence. No technical security failure was waived.

The first Sodium-enabled backup run exposed an expected-error-category
ordering assertion for a public-root path; inspection confirmed that the path
was rejected before any write, and the check order was adjusted to return the
specific protected-root category first. The next run exposed an undefined
repository variable in the synthetic test fixture. Both findings were fixed,
and the complete Sodium-enabled suite passed on `3efa6aa`. The CLI secret
handling checks passed on `4d44342`. No security test was turned into a skip
or pass-through.

## Security and privacy status

| Evidence class | Status |
|---|---|
| `TECHNICALLY_IMPLEMENTED` | Account inventory, export manifest, staged deletion, encrypted backup/isolated restore, recovery and session transitions |
| `AUTOMATED_VERIFIED` | Isolated suites and hosted exact-commit checks recorded in the final report |
| `HUMAN_VERIFIED` | Pending |
| `OWNER_APPROVED` | Pending |
| `PROVIDER_VERIFIED` | Pending |
| `INDEPENDENTLY_REVIEWED` | Pending |

Application deletion cannot erase provider logs, PHP session files immediately,
historical backups, external exports, or copies outside application storage.
The PHP session handler/GC, provider log/backup retention, hosting region,
HTTPS/proxy configuration, legal operator, monitored support contact,
privacy-notice approval, application retention period, recovery policy
(including whether recovery codes should expire), independent reviewer, and
public launch authorization remain unresolved.

## Commit and hosted evidence

```text
HEAD_BEFORE=db478533e7bed7da09b7c4604d69c0bda24d8d04
HEAD_AFTER_APPLICATION=af27d0b5968bd90d72ea956d07127e2fbcb8825f
APPLICATION_CODE_FIX=af27d0b5968bd90d72ea956d07127e2fbcb8825f
APPLICATION_TEST_COMMIT=4d4434289690dd270c6f618cf6a1ff2caa7d5338
CI_COMMIT=4d4434289690dd270c6f618cf6a1ff2caa7d5338
RELEASE_GATES_RUN=39090027198 (Doctor, all security/privacy jobs, preflight/smoke, and ten-board HTTP passed; Product Readiness blocked on owner/provider evidence)
BROWSER_ACCEPTANCE_RUN=39089492279 (PASS, 60/0/8; https://github.com/esonhaya/boardprep/actions/runs/39089492279)
SITE_EXPLORER_RUN=39088111773 (PASS, 100 routes, 290 actions, 10 boards; https://github.com/esonhaya/boardprep/actions/runs/39088111773)
ACCOUNT_EXPLORER_SCENARIOS=32 PASS (same site-explorer workflow run)
REGISTERED_BROWSER_EVIDENCE=VERIFIED_PASS, run 39089492279, commit 4d4434289690dd270c6f618cf6a1ff2caa7d5338
EVIDENCE_DOCUMENTATION_COMMIT=f51abb7d6a9cea71d240c0e54b152d5fe95ea353
FINAL_RELEASE_GATES_RUN=39090027198 (https://github.com/esonhaya/boardprep/actions/runs/39090027198)
```

The complete requested field-by-field status report is saved at
`/data/data/com.termux/files/usr/tmp/boardprep-batch-693-final-report.txt`.
