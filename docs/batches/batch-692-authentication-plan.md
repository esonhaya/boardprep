# Batch 692 security milestone: real learner accounts

Owner request F691-08 calls for registration, sign-in, and persistent profiles. Batch 691 keeps the current anonymous-session identity boundary. Account creation is deferred until this security milestone can be implemented and reviewed as a whole.

## Required design evidence before implementation

1. Define the account data model, credential-verification method, password length/compromise policy, rate limits, anti-enumeration behavior, and account recovery process. Store only a purpose-built password hash supported by the deployed PHP runtime; never store or log raw passwords or recovery codes.
2. Define session fixation, rotation, cookie, CSRF, logout, idle/absolute expiry, concurrent session, and account-enumeration controls. Document the production HTTPS and secure-cookie prerequisites without claiming they exist before deployment evidence.
3. Threat-model anonymous learner records. Keep anonymous data anonymous by default. Link a session to an account only after authenticated, explicit user action; verify ownership; make the operation transactional, idempotent, and auditable; preserve attempts, results, history, progress, and flashcard state; provide safe rollback and collision handling.
4. Specify account export, deletion, retention, backups, support/contact, legal operator, and hosting responsibilities with owner/provider facts still awaiting verification. Define what application deletion can and cannot remove from provider backups.
5. Add independent security review, privacy-owner approval, deployment evidence, migration tests, two-user isolation tests, recovery tests, abuse/rate-limit tests, and upgrade/rollback evidence as release gates.

## Implementation sequence

1. Approve the threat model, identity semantics, recovery and data-linking rules, and deployment prerequisites.
2. Implement credentials, login/session rotation, CSRF-protected registration and account changes, logout, recovery, rate limiting, and authorization together.
3. Implement explicit anonymous-session linking as an opt-in transaction with record-count and ownership verification. Do not merge by email or infer account ownership.
4. Verify account isolation, privacy export/deletion, lockout/rate limits, recovery, session cookies, and migration behavior in isolated fixtures.
5. Complete independent review and owner/provider approval before enabling production account creation.

Until these steps pass, user profiles mean validated preferences stored in the existing anonymous browser session. No real registration, sign-in, persistent account identity, or anonymous-data migration is implemented.

## Batch 692 implementation status — 2026-10-18

Batch 692 implements and tests a private, nonproduction account foundation.
This note supersedes the earlier “not implemented” status above; it does not
close the production requirements. Registration, sign-in, persistent account
preferences, PHP-native password hashing, hashed single-use recovery codes,
rate limiting, session rotation/expiry, account-scoped export, and account
isolation are available only when the explicit nonproduction feature flag is
enabled. Production mode never registers these routes, even if the flag is set.

The preview password policy requires 12–128 characters (with a runtime-specific
72-byte limit when Argon2id is unavailable). No breached-password lookup is
configured; decide and review that control before any public enablement. There
is no email recovery provider. Anonymous records are not linked. A confirmed
deletion action locks future sign-in but does not erase learner records. The
unencrypted backup helper excludes the credential file, so protected backup
and restore must be designed and tested before accounts can be operated
publicly. Independent security review, owner-approved privacy/deletion terms,
and live HTTPS/session/storage evidence remain open.
