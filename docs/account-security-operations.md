# Account security operations

This runbook describes the nonproduction account preview and the technical
account-data lifecycle. Public production account routes remain disabled. This
runbook does not set legal retention periods or make promises about hosting
logs, external copies, or provider backups. The canonical owner/provider
decisions remain in [OWNER_PRIVACY_AND_HOSTING_DECISIONS.md](OWNER_PRIVACY_AND_HOSTING_DECISIONS.md).

## Account-owned data inventory

The server creates `account_id` and `learner_id`; it ignores browser-submitted
learner IDs. Account collections are selected by the authenticated account's
server-issued `learner_id`. Anonymous learner IDs have a separate format and
remain in the same learner collections without becoming account-owned.
The inventory service reports exact account-owned counts, schema versions,
storage mode, ownership field, read/write paths, export/deletion behavior,
backup inclusion, and retention boundary. It separately counts anonymous
rows and rows without an owner field; those rows are excluded from account
deletion and export.

| Data | Ownership and location | Read/write paths | Export | Deletion | Encrypted backup | Retention boundary |
|---|---|---|---|---|---|---|
| Quiz attempts and results | `user_id`; `APP_STORAGE_PATH/attempts.json` or SQLite `storage_records` collection `attempts` | `AttemptRepository` / `LearningHistoryService`; `AttemptService::save` | Included, with internal learner/session IDs removed | Exact-owner records are removed and verified | Included | App records are removed after confirmed deletion; copies outside app storage are independent |
| Weakness summaries | `user_id`; `APP_STORAGE_PATH/weakness.json` or SQLite collection `weakness` | `WeaknessStorageService::all/save/clear` | Included, with internal learner ID removed | Exact-owner records are removed and verified | Included | Same application/external-copy boundary |
| Flashcard review state | `user_id`; `APP_STORAGE_PATH/flashcard_state.json` or SQLite collection `flashcard_state` | `FlashcardStateRepository` | Included, with internal learner ID removed | Exact-owner records are removed and verified | Included | Same application/external-copy boundary |
| Profile and preferences | `account_id`; private `private/learner-accounts/accounts.json` | `AccountRepository`, `AccountSessionService`, `AccountService` | Username, timestamps, and preferences only | Credentials and preferences are removed at completion | Included inside authenticated encryption | Completion leaves only a pseudonymous idempotency/session-revocation tombstone |
| Password and recovery digests | `account_id`; private account JSON | `AccountService` / `AccountRepository` | Excluded | Removed when the credential record is replaced by its tombstone | Included only inside authenticated encryption | External copies are not reached by app deletion |
| Session authorization | `account_id` and `learner_id` inside PHP session data at deployment-configured `session.save_path` | `AccountSessionService` | Excluded | Status/version lookup revokes all sessions; current session logs out. PHP file cleanup follows PHP handler/GC behavior. | Excluded | Not indexed centrally; count and physical cleanup depend on the live PHP session configuration |
| Derived progress and analytics | Computed from quiz attempts at read time | Learning history and analytics services | Represented by source attempts | Disappears when source attempts are removed | Recomputed from restored attempts | No separate record or retention period |
| Shared question usage and anonymous learner records | Global question data or separate `learner-*` identities | Question/storage and anonymous privacy services | Excluded from account export | Never changed by account deletion | Excluded from account backup | These records have their own owner/provider policy |
| Question statistics and flashcard exclusions | Shared aggregate/configuration in `APP_STORAGE_PATH/question-statistics.json` and `flashcard-exclusions.json` | Shared statistics and flashcard-selection services | Excluded from account export | Never changed by account deletion | Excluded from account backup | Not attributed to an individual account |
| Rate-limit buckets | Hashed identifiers and IP scopes in private account JSON | `AccountRepository::reserveAttempts` | Excluded | Account-specific identifier buckets are removed; IP buckets can be shared and expire by configured limiter windows | Excluded | Not a learner-data archive; limits are technical constants |

Historical `database/attempts/attempts.json` rows have no account ownership
field and are not a current account read/write location. The inactive legacy
progress repository is likewise not a current account-owned collection. The
inventory service reports these boundaries so they are not silently treated
as live account data. Active-store attempt rows without a `user_id` are counted
as unattributed legacy rows and are not modified. The application does not
modify shared question usage or question-statistics aggregates during account
deletion.

## Deletion lifecycle

Deletion states are `active`, `deletion_requested`, `deletion_processing`,
`deletion_failed`, and `deletion_completed`.

1. An authenticated account submits the account profile form with CSRF,
   current-password reauthentication, and the exact `DELETE` confirmation.
2. A shared lifecycle lock changes the account state and increments its
   authorization version. Sign-in and account-owned writes stop.
3. The staged journal records owned collection counts and checkpoints. The
   worker deletes only records whose `user_id` exactly matches the account's
   server-issued learner ID, then verifies all owned collections are empty.
4. The private credential/profile entry is replaced by a minimal tombstone
   containing the opaque account/learner IDs and completion state.
   Credentials, recovery digests, username, and preferences are removed.
5. If storage or integrity checks fail, the account remains locked in a
   retryable failure state. Re-running the operation is idempotent. A
   completion response is shown only after clean verification and tombstone
   commit.

For a technical retry after a process interruption, use only an isolated
synthetic fixture with `APP_ENV=testing`,
`BOARDPREP_ACCOUNTS_ENABLED=true`, and a private `APP_STORAGE_PATH`:

```sh
APP_ENV=testing BOARDPREP_ACCOUNTS_ENABLED=true \
APP_STORAGE_PATH=/private/boardprep-account-fixture \
php tools/account-delete.php --list-pending

APP_ENV=testing BOARDPREP_ACCOUNTS_ENABLED=true \
APP_STORAGE_PATH=/private/boardprep-account-fixture \
php tools/account-delete.php --account-id=ACCOUNT_ID \
  --confirm-account-id=ACCOUNT_ID
```

The CLI cannot start a deletion for an active account, is limited to the
testing environment, and requires the account ID twice. `--list-pending`
prints only opaque account IDs and deletion states. Do not point it at live,
staging, or owner storage. Public deletion/registration remains blocked by the
production account feature gate while the owner policy is pending.

The application cannot erase hosting logs, archived backups, exported copies,
or provider-side records through this path. The exact application and external
retention policy is pending owner/provider decisions. Tombstones currently
have no automatic expiry; the owner must decide whether their minimal
operational metadata has a required retention period.

## Export

`POST /account/export` requires an authenticated account, CSRF token, and
current password. The manifest contains timestamp, schema versions, counts,
included collections, excluded secrets, and deletion limitations. It excludes
password hashes, recovery-code digests, active session identifiers, CSRF
tokens, rate-limit bucket secrets, and private encryption keys. Export uses
the same learner ownership boundary as deletion. Anonymous export remains a
separate session-scoped action.

## Encrypted account backup

Backups require PHP ext-sodium with Argon2id and XChaCha20-Poly1305-IETF.
Each archive uses a fresh random Argon2id salt and XChaCha20 nonce. The
format/schema version and bounded KDF parameters are authenticated as AEAD
associated data. A modified header, tag, ciphertext, or passphrase is rejected.
The archive is written to a new owner-only file (`0600`) in an owner-only
directory (`0700`) outside the repository and public root. The archive
contains credentials and recovery-code digests only inside authenticated
encryption. The export JSON is not a credential backup.

The passphrase must be independently generated and held by an authorized
operator. Never place it in a command argument, environment variable, shell
history, issue, CI log, or beside the archive. The CLI accepts one bounded
line from an inherited file descriptor. Example using a separately created
owner-only private directory and an interactive hidden prompt:

```sh
mkdir -m 700 /var/lib/boardprep-private-backups
read -r -s -p 'Backup passphrase: ' backup_passphrase
printf '\n'
printf '%s\n' "$backup_passphrase" | php tools/account-backup.php \
  --create --account-id=ACCOUNT_ID \
  --output=/var/lib/boardprep-private-backups/account.bpk \
  --passphrase-fd=3 3<&0
unset backup_passphrase
```

`ACCOUNT_ID` is an operator-supplied placeholder for the opaque account ID
obtained from a restricted administrative context. Do not print or copy the
private account JSON to a terminal or CI artifact. The example path and
account ID are placeholders. The program refuses the repository, public
root, symlink paths, insecure directory/file modes, and overwrite attempts.
There is no owner passphrase configured in this repository.

Useful commands:

```sh
php tools/account-backup.php --help
php tools/account-backup.php --verify \
  --input=/var/lib/boardprep-private-backups/account.bpk --passphrase-fd=3 3<&0
php tools/account-restore.php --help
php tools/account-restore.php \
  --backup=/var/lib/boardprep-private-backups/account.bpk \
  --destination=/var/lib/boardprep-restore/account-copy --dry-run \
  --passphrase-fd=3 3<&0
```

Restore authenticates and validates the archive before writing. Dry-run is the
default behavior. `--commit-isolated-restore` explicitly creates a new,
owner-only JSON destination atomically; the destination is not activated by
the application and an existing destination is never overwritten. Restore
validates credential shape, recovery digests, manifest counts/schema, unique
record IDs, and exact learner references. Interrupted staging is rolled back.
Restoring into active application storage is not supported. An operator who
later elects to activate a recovered account needs a separate reviewed
procedure and rollback plan.
A completed deletion tombstone in the configured source account store blocks
restoration of that account's old archive. A restore into another deployment
without that tombstone remains an operator-controlled copy outside this
application's deletion reach.

## Recovery and sessions

Registration displays eight 160-bit random recovery codes once and persists
only SHA-256 digests. Rotation requires CSRF and current-password
reauthentication and invalidates all previous codes. Recovery is
identifier/IP-rate-limited and consumes the matching digest under the account
lifecycle lock; one concurrent attempt wins. Successful recovery increments
the authorization version, invalidating previous sessions. There is no email
recovery provider. Recovery codes do not automatically expire in this preview;
rotation and completed deletion revoke remaining codes. The owner must decide
the expiry and recovery policy before public enablement. Loss of all recovery
codes currently requires an operator decision; a delivery or
identity-verification process must be approved before public use.

Password changes also require CSRF and current-password reauthentication and
increment the authorization version. Sign-in and logout rotate the PHP
session ID and CSRF token. Account sessions expire after 30 minutes idle or 12
hours absolute. The account session cookie is HttpOnly and SameSite=Lax;
Secure depends on PHP receiving trusted `HTTPS` state.

Behind a reverse proxy, the provider must prevent direct origin access, strip
client-supplied `X-Forwarded-Proto`, and map the verified TLS connection into
PHP's `$_SERVER['HTTPS']`. BoardPrep does not read arbitrary forwarded
protocol headers. Verify the actual cookie on the hosted site.

## Verification and handling failures

All account lifecycle tests use disposable synthetic storage. JSON and SQLite
fixtures test owned counts, export, write fencing, interrupted deletion,
retry, tombstones, cross-account/anonymous/global preservation, dangling
learner IDs, deleted-account recovery rejection, and concurrent recovery.
Injected ENOSPC and write-commit failures plus malformed credential JSON
confirm that previous credential state remains unchanged. Backup tests run a
full Sodium-enabled round trip in hosted CI and fail closed locally when
ext-sodium is absent. A separate CLI test feeds only synthetic passphrases
through an inherited descriptor, rejects visible command-line and missing
secrets, and scans output for credential leaks. Restore interruption tests
verify that no partial destination or source mutation remains.

On any storage failure, preserve the locked account and its journal, retain the
sanitized failure category, restore service availability only after a safe
retry succeeds, and do not report completion before full ownership
verification. Never test deletion or restoration against real learner
storage.
