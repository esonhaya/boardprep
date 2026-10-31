# Batch 733 targeted gap assessment

Assessment baseline: `50dd1aeee0bd8b8fa2808e5599be86bc5d4b89f5` on
`batch-733-adaptive-planner-readiness-dashboard`. The existing Batch 732
working tree was clean for tracked files before this report; two stashes and
twelve registered worktrees were preserved. The 15-file protected manifest
matched before source work. Free storage was approximately 3.12 GiB.

No application implementation began before this assessment. Evidence was
read from the current source and tests, not inferred from the old Batch 390
archive. This assessment is a source-contract review; rendered browser behavior
remains unverified on Termux Android/arm64.

## Inventory and decisions

| Capability | Existing evidence and contract | Class | Decision and verification |
|---|---|---|---|
| Learner study recommendations | `GET /study` renders `StudyDashboardController` from `LearningHistoryService`, `StudyDashboardService`, `StudyPlanService`, and learner preferences. The plan is bounded to five actionable recommendations; topic actions route through `StudyActionService`. Contracts: `tools/Tests/StudyDashboard*Test.php`, `StudyPlanContractDoctorTest.php`, `StudyPlanSubjectRoutingTest.php`, `StudyDashboardActionabilityTest.php`. | PARTIAL | Preserve the recommendation API and add a distinct, editable calendar capability. Derive subjects from `BoardViewService::all()` and only use recorded performance as evidence. |
| Board and subject metadata | `BoardViewService::all()` supplies active board choices and each board's `practice_subjects`; `LearnerPreferenceService` validates board/subject combinations. | COMPLETE | Reuse and validate against these current records; an empty catalog produces an explicit no-plan state. |
| Learning performance and weak areas | `StudyDashboardService` aggregates ordered owned attempts; `LearningReadinessService`, `TopicPerformanceService`, and `StudyRecommendationService` distinguish actual performance and sample-size limits. `LearningHistoryService::all()` already applies learner scoping. | COMPLETE | Use the existing dashboard and only label a focus item as a demonstrated weak area if its evidence/sample metadata supports that claim. No mastery or pass prediction will be synthesized. |
| Mistake and skipped-question recovery | `/mistake-notebook`, `/flashcards/missed` and `WeaknessService` separate mistakes and skips; account/session ownership and eligibility are enforced by existing services. | COMPLETE | Integrate existing recovery routes as actions only when there is actual recovery evidence. |
| Flashcard scheduling | `FlashcardService`, `FlashcardScheduler`, and `FlashcardStateRepository` implement owned, persisted due scheduling and eligibility checks. Tests: `FlashcardSchedulingTest.php`, `FlashcardIntegrationTest.php`, `FlashcardHttpTest.php`, `FlashcardControllerReadPathTest.php`. | COMPLETE | Reuse due selection with bounded results; do not duplicate scheduler state or mutate cards. |
| Learner-specific persistence | `StorageInterface` supports CRUD and atomic `updateBatch`; `StorageRouter` selects JSON/SQLite backends. Existing learner records use `user_id` and `LearnerIdentityService`; writable account mutations use `AccountLifecycleLock::withWritableLearner`. | COMPLETE | Store one versioned learner-owned plan in a new `learner-study-plans` collection. Keep generation deterministic and edits revision-aware/idempotent. Cover anonymous export/clear and authenticated inventory, encrypted backup, isolated restore, and deletion registries. |
| Account export, deletion, backup, and restore lifecycle | `AccountDataInventoryService::COLLECTIONS`, `AccountDeletionService`, `AccountBackupService`, `AccountRestoreService::COLLECTIONS`, and `AnonymousLearnerDataService::COLLECTIONS` are explicit allowlists. Current archive payload supports schema 1 and encrypted collection exports. Existing tests: `AccountBackupTest.php`, `AccountLifecycleTest.php`, `AnonymousLearnerPrivacyHttpTest.php`. | PARTIAL for any new planner record | Extend every explicit registry consistently, preserve older archives that omit the optional planner collection, validate ownership on export/restore, and verify deletion/anonymous privacy. No schema-version change is needed for an optional collection. |
| Unified developer readiness evidence | `/developer/doctor` reads persisted Doctor/health/release snapshots through `RepositoryHealthEvidenceService::classifyPersistedSnapshot`; `/developer` shows content integrity. Routes exist only outside production. `DoctorDashboardController` validates stored snapshots but does not summarize the latest batch Full Doctor artifact, academic queue, browser status, and public-release authorization as independent states. | PARTIAL | Add a read-only `/developer/readiness` surface. Accept only bounded JSON and metadata sidecars beneath `docs/reports`; display PASS only for valid, fresh evidence explicitly tied to current HEAD. Missing provenance is UNKNOWN/STALE. Never run Doctor during a page request or imply public authorization. |
| Academic review/proposal counts | `AcademicReviewProposalService` reads the separate read-only Batch 695 disposition inventory; reviewer authorization and decisions are separate services/workflows. `SourceRegistryService` reads canonical source metadata; `QuestionIssueReportService` stores learner concerns separately from academic decisions. | PARTIAL | Show counts/statuses only, with provenance and links to current review routes. Keep pending proposals and source limitations untouched; learner reports remain unvalidated concerns. |
| Developer authorization boundary | Developer routes are mounted only when `APP_ENV !== production` (`routes/web.php`); README documents the environment restriction. Reviewer capabilities require account grants in academic services. There is no general developer-role service in this checkout. | UNVERIFIED | The new page will be read-only, remain inside the established non-production route gate, and disclose that the route is not a reviewer grant. Do not expose raw private storage, secrets, or learner contents. Existing route-policy tests remain a final gate. |
| Encrypted backup cryptography | `AccountBackupCrypto` requires ext-sodium, Argon2id, and XChaCha20-Poly1305, and fails closed if unavailable. The archive authenticates a versioned header and payload. `AccountBackupService` and `AccountRestoreService` own archive validation, account ownership and isolated JSON restore. | UNVERIFIED at runtime | The only discovered PHP executable is Termux PHP 8.5.1 with PDO SQLite but without sodium. Do not install or weaken crypto. Run the crypto test suite (including its unavailable-runtime behavior); mark real round-trip environment-blocked unless a safe local runtime is found. |
| Browser acceptance | Existing Playwright spec/config and pinned runner are documented in the Batch 732 audit; Android/arm64 cannot run the pinned driver and no supported local browser is available. | UNVERIFIED | Run browser contract/static tests; report `REAL_BROWSER_ACCEPTANCE=NOT_EXECUTED`. Do not use a remote runner. |

## Implementation gates and planned acceptance evidence

1. **Adaptive calendar:** validate canonical board, selected subjects, dates,
   weekdays, rest dates, duration, intensity, and priorities server-side. Save
   a single owner-scoped revision with bounded future sessions. Regeneration
   preserves completed and user-rescheduled work, reports conflicts rather
   than silently overwriting edits, and is request-idempotent. Tasks will link
   to existing quiz, flashcard, or mistake routes. Tests will cover generation,
   date/day changes, rest days, completion, rescheduling, regeneration,
   idempotency, owner isolation, invalid/empty catalog, missing attempts,
   review evidence, JSON/SQLite, and privacy lifecycle.
2. **Readiness dashboard:** parse one bounded, explicitly named latest Full
   Doctor artifact and sidecar; require a valid JSON schema, successful exit,
   current tested commit, and freshness window before showing technical PASS.
   Keep browser, academic, and public authorization separate. Test missing,
   corrupt, stale/wrong-commit, failure counts, academic pending, browser
   NOT_EXECUTED, access gate, path traversal, empty/partial state, and bounded
   artifact reads.
3. **Backup:** run the real round-trip only if a local PHP binary with the
   exact required ext-sodium capabilities is available. Existing crypto
   fail-closed tests remain required either way. No fixtures may touch real
   learner records.

## Scope decisions

- Confirmed defects: no new P0/P1 defect established by this targeted audit.
- Confirmed incomplete capability to complete: learner study recommendations
  are not a dated, editable schedule.
- Existing capability to improve: developer diagnostics exist but lack a single
  provenance-aware overview separating technical, browser, academic, and
  public-release states.
- New capability planned: learner-owned adaptive study calendar and read-only
  unified readiness overview.
- Human academic review remains a separate gate. No proposal approval/import,
  source approval, learner record edit, or public release is authorized.
- Browser acceptance and encrypted backup round-trip remain unverified unless
  this environment gains an already-installed supported runner/runtime.

## Implementation and verification outcome

The calendar is now a learner-owned, versioned record in
`learner-study-plans`. `StudyCalendarPlanner` validates board subjects, dates,
available weekdays, rest dates, daily time, review intensity, and subject
priorities against the current board catalog. Generation is deterministic and
bounded to 42 days and 28 active sessions. It uses board-matched attempt
evidence only after three completed attempts, topic-level evidence only after
two attempts, the existing due flashcard queue, and saved mistake counts.
Insufficient data is disclosed. No official exam prediction or unsupported
mastery value is produced.

Students can mark, skip, defer, and reschedule sessions. Revision checks and
request hashes make updates retry-safe; collision checks prevent two active
tasks on one day. Regeneration preserves completed/skipped history and future
sessions the learner edited. The learner dashboard shows the next session or
an overdue state. New calendar records are covered by JSON and SQLite storage,
account export/deletion/restore inventories, anonymous export/clear, and the
mutable-data backup helper. Synthetic-fixture tests exercised the full HTTP
generation, CSRF rejection, completion, retry, dashboard continuation, and
backup/restore paths.

The read-only `/developer/readiness` page now validates bounded Full Doctor
JSON and provenance, including its command, SHA-256, exit code, tested commit,
freshness, and timestamps. It summarizes list-shaped Doctor flow/security
evidence accurately, limits artifact size and report-directory scans, rejects
unsafe paths, and marks stored PASS values stale when provenance is invalid or
does not match this checkout. Academic backlog, learner concern counts,
browser execution, technical health, and public authorization remain separate.
It is absent from production routes and never runs Doctor during a page load.

### Classification after implementation

- **Confirmed defects fixed:** readiness evidence parsing now accepts the
  hexadecimal `ARTIFACT_SHA256` metadata key and rejects path traversal without
  a type error; stale browser/security evidence cannot display as current PASS;
  Doctor test totals work with both list-shaped and numeric evidence.
- **Confirmed defects remaining:** none identified by the targeted local
  checks. This is not a claim of complete browser or academic acceptance.
- **Existing features improved:** recommendations now connect to an editable
  calendar; the learner dashboard surfaces the next session; readiness
  diagnostics now show provenance, freshness, counts, and independent gates.
- **New features implemented:** learner-specific adaptive study calendar and
  the read-only unified developer readiness overview.
- **Proposed features deferred:** none added. The planner remains deliberately
  deterministic rather than using an unvalidated prediction model.
- **Unverified features:** real rendered browser/accessibility behavior; the
  encrypted backup round-trip on this runtime; identity-based developer roles
  (the existing authorization boundary is the non-production route gate).
- **Academic issues requiring human review:** the separate proposal inventory
  and source traceability review signals remain pending; no review status,
  source approval, question, answer key, eligibility, or learner record was
  changed. Learner reports remain unvalidated concerns.

### Verification evidence

- Planner unit/acceptance tests passed for deterministic generation, changing
  exam dates and weekdays, rest days, board-specific evidence, due cards,
  mistakes, JSON/SQLite persistence, edits, completion, collision rejection,
  idempotency, regeneration, and backup-restore schema compatibility.
- Simulated production HTTP tests passed for calendar generation, invalid
  CSRF rejection, session completion/retry, dashboard continuity, account
  ownership/lifecycle, anonymous export/clear, and JSON/SQLite deletion.
- Readiness tests passed for current-shaped, missing, corrupt, oversized,
  stale, wrong-commit, failed-gate, invalid-command, path-traversal, and
  bounded-directory evidence; they also check academic counts, browser
  `NOT_EXECUTED`, and private learner-report aggregation.
- Mobile/accessibility source-contract tests and the pinned browser CI contract
  suite passed. They are static/HTTP checks; **real browser acceptance was not
  executed** on Termux Android/arm64.
- `AccountBackupTest.php` passed its fail-closed assertion. The only local PHP
  runtime found is Termux PHP 8.5.1 without ext-sodium, and the bounded search
  found no alternate PHP binary or sodium extension. Do not install or weaken
  crypto for this verification. To perform the real round-trip on a supported
  host, install/use an already-supported PHP CLI with ext-sodium enabled, verify
  `php -r 'exit(extension_loaded("sodium") ? 0 : 1);'`, then run
  `php tools/Tests/AccountBackupTest.php`. That suite creates synthetic
  accounts and covers authenticated encryption/decryption, wrong passphrase,
  ciphertext/header tampering, truncation, ownership, isolated restore, and
  cleanup; it does not use real learner accounts.
