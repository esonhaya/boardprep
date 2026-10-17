# Batch 692 — secure learner account foundation

## Result

Batch 692 adds a real account and persistent profile foundation for isolated
development and test use. It does not enable public accounts or claim that the
foundation has received independent security review. Production account routes
are never registered, including when the feature flag is set.

The account preview is available only outside `APP_ENV=production` when
`BOARDPREP_ACCOUNTS_ENABLED=true`. Browser CI uses a separate loopback server,
private temporary account storage, and separate PHP sessions. Anonymous
practice remains available without registration.

## Account and session boundaries

- Usernames are normalized, validated identifiers; the application does not
  claim or verify an email address.
- Passwords use PHP `password_hash` / `password_verify`: Argon2id at
  65,536 KiB, three iterations, one lane when that algorithm exists; otherwise
  PHP `PASSWORD_DEFAULT`. Passwords must be 12–128 characters. A bcrypt-only
  runtime also enforces its 72-byte limit. A breached-password service is not
  configured.
- Registration and sign-in use generic responses. Failed attempts are kept in
  hashed identifier/IP buckets: 5 registrations per IP per hour; 8 login
  attempts per normalized identifier and 24 per IP per 15 minutes; recovery
  and password reauthentication have separate bounded limits.
- Recovery displays eight cryptographically random, single-use codes once and
  stores only SHA-256 digests. There is no email delivery or claim of delivery.
- The session cookie is strict-ID, cookie-only, HttpOnly, and SameSite=Lax;
  Secure is set when PHP detects HTTPS. Login, logout, password changes, and
  recovery rotate session/CSRF identity. Account sessions expire after 30 idle
  minutes or 12 hours absolute.
- Account credential/profile JSON is outside the public root, protected by
  private directory/file permissions, a shared lock, atomic replacement,
  unique identities, and fail-closed corruption handling.
- Each account receives a server-generated learner identity. It is separate
  from the current anonymous session. Login preserves the guest identity and
  in-progress guest quiz separately; it does not link or merge guest records.
  There is no anonymous-to-account linking route.
- Account exports require a CSRF token and current password and include only
  records for the authenticated account learner identity. Credential hashes,
  recovery digests, session values, and private account IDs are excluded.
- The deletion control records a request and disables future login; it does
  not delete learner records. The private credential store is excluded from
  the current unencrypted backup/package path. Neither limitation is hidden.

## Routes and usage

When enabled in a local testing/development environment, the nonproduction
routes provide account creation, sign-in/out, profile and preference changes,
password changes, recovery-code replacement and recovery, a scoped account
export, and a deletion request. `/account` redirects to the profile or login.
The separate anonymous learning routes continue to work for signed-out users.

Hosted account browser checks run with:

```sh
npm run test:explore:auth:smoke
npm run test:explore:auth:full
```

Run them on supported desktop/CI Chromium. The browser server binds to
`127.0.0.1`; the Android Termux runner reports Chromium unavailable and does
not attempt a browser installation. Explorer artifacts are sanitized and
temporary; they do not contain password, username, recovery-code, cookie, or
learner-record values.

The shared browser fixture copies canonical question content but starts
attempts, weakness summaries, flashcard state, and question statistics as
empty private fixture data. A synthetic-marker regression protects this
boundary. During Batch 692 investigation, an earlier browser fixture run was
found to have copied tracked attempt records into its temporary runtime. The
source records were not changed. The fixture was corrected before the final
browser runs and a synthetic marker now proves that mutable state is cleared.

Focused tests include:

```sh
php tools/Tests/AccountSecurityTest.php
php tools/Tests/AccountHttpTest.php
php tools/Tests/SessionConfigurationTest.php
php tools/Tests/LearnerPreferenceServiceTest.php
php tools/Tests/FlashcardHttpTest.php
php tools/Tests/FlashcardIntegrationTest.php
php tools/Tests/AnonymousLearnerPrivacyHttpTest.php
php tools/Tests/TenBoardLearnerJourneyHttpTest.php
npm run test:browser:fixture
```

Automated HTTP/browser results are technical evidence only. They do not count
as owner acceptance or independent security review.

## Persistent profile and learner data

Preferred board, LET candidate level/specialization, Civil Service level,
subject interests, and learning preferences persist on the authenticated
profile. Exam candidate level remains distinct from performance-derived
Beginner/Intermediate/Advanced level. Explicit valid route context overrides a
saved default. Flashcard board/subject/topic combinations remain eligibility
validated; invalid descendants are cleared and tampered combinations rejected.

Attempts, result history, progress, flashcard state, and profile requests use
the authenticated server-issued learner identity. The account A/B tests use
synthetic records and confirm that one account cannot retrieve the other's
preferences or exported records. Anonymous identity continues to be separate.
No existing owner records were used as migration fixtures, and no migration
was attempted.

## Test and CI record

Application changes are in commits `1d57912122ea0266907e046f206e83f04aa1c87f`
and the subsequent isolated-fixture/browser-test corrections. The release
gates on the first complete application commit ran as workflow
`39069165553`; Doctor, all nine security/privacy checks, ten-board learner HTTP,
and production preflight/smoke passed. Formal Product Readiness correctly
remained failed because browser evidence was not yet registered and actual
owner/privacy/live-host blockers remain.

Hosted browser attempts before correction exposed test issues rather than
account defects: CI retries replayed stateful account journeys against retained
rate limits, and the password-change confirmation uses the accessible `alert`
role rather than `status`. The authentication test now disables retries,
preserves the primary failure evidence, and checks the role used by the UI.

On exact commit `07711e628b492d8ef47e6e11257f6ac3d13480f2`, Browser acceptance
run `39070596949` completed `60 PASS / 0 FAIL / 8 SKIP` across 68 tests. The
eight skips are project-conditional: the account journey runs once in mobile
emulation; seven other acceptance cases run only in their designated mobile or
desktop project. There were no flaky or unrun cases. This verified browser
evidence is registered in `docs/release-evidence/browser-workflows.json`.

The exact-commit bounded site explorer was run `39070597000` with seed 691.
Its final report is `PASS`: 100 routes, 290 actions, 21 state transitions, 10
forms, all 10 boards, 10 board/subject pairs, and 50/50 checked question
deliveries; final report has no confirmed defects, review cues, console/page
errors, failed requests, or unexpected HTTP errors. Attempt 1 stopped after
84 seconds on `net::ERR_ABORTED` for `app-shell.js` during a study-material
navigation; the retained screenshot/report show a canceled asset request.
Attempt 2 ran the full traversal for 81 seconds and did not reproduce it. This
is recorded as a transient, unverified navigation cancellation rather than a
closed product defect.

The authentication portion of that same exact-commit workflow passed all 23
scenarios with 45 main-frame navigations. It had no confirmed account defects.
The PHP security suite separately tests idle/absolute expiry and cross-session
revocation; the browser suite does not wait through the 30-minute idle window.

## Production blockers

`BP692-CAP-001` remains open. Public account enablement requires independent
security review, owner-approved privacy and deletion/retention terms, an
approved support/recovery process, encrypted backup and tested restore for
credentials, and verified HTTPS, cookies, storage permissions, isolation, and
hosting configuration. Recovery codes are currently the only recovery method;
there is no verified email provider. Full account/learner-record deletion is
not implemented, and backup/log retention remains owner/provider-dependent.

Anonymous-to-account data linking remains disabled. A future design needs
explicit informed consent, verified session ownership, transaction-safe
idempotent linking, conflict handling, audit/recovery behavior, and regression
evidence before any route exists. Password breach screening also remains an
owner/security decision. Public account creation remains disabled regardless
of this preview's test outcome.
