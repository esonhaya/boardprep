# BoardPrep Batch 698 — October alpha convergence

**Target:** restricted alpha candidate for 2026-10-31  
**Branch:** `batch-698-october-alpha`  
**Application source checkpoint exercised:** `6550ef1cc39d4d7d13d44f0b413442bb36df7a07`  
**Full Doctor report:** [`docs/reports/batch-698-doctor-release.json`](../reports/batch-698-doctor-release.json)

This is local evidence for a technically testable candidate. It is not public
launch approval, human acceptance, independent academic approval, or live-host
verification. A later documentation/evidence-only checkpoint does not change
the application source identified above; use the SHA printed by the Acceptance
Center for any later human or hosted run.

## Readiness decision

- **ALPHA_CODE_READY: YES.** No code-owned P0/P1 was found. Local Full Doctor,
  core learner journeys, ten-board HTTP, privacy/isolation, preflight, and the
  isolated human-test handoff passed.
- **ALPHA_TESTER_ACCESS_READY: LOCAL ONLY.** The same-device loopback preview is
  operational. Internet-facing tester access is **NO** until owner privacy,
  hosting, and HTTPS decisions are evidenced.
- **PRODUCT_LAUNCH_READY: NO.** Product Readiness returned NO with one blocking
  capability and four required evidence items unresolved. Do not infer launch
  readiness from the Doctor gate.
- **HUMAN_ACCEPTANCE: NOT_RUN.** All twelve canonical human outcomes remain
  `NOT_RUN`.
- **ACADEMIC_REVIEW: PENDING.** Instruction remains partial; Batch 695's 276
  pending questions remain on its separate review branch. The source
  traceability finding below remains a review item.

## Gap and evidence matrix

| Capability | Local implementation / automated evidence | Human or external evidence | Severity / next action |
|---|---|---|---|
| Entry, board/subject selection, practice, answer/review, history/progress, continuation | Ten-board HTTP journey and Guided Learning HTTP journey passed; Doctor learner, attempt, result, history, progress, study, and eligibility domains passed | Human comprehension and device use not run | No known code P0/P1; run MVP-01–10 with a tester |
| MVP-01–10 recovery and edge cases | Bounded HTTP/lifecycle tests passed, including retries, stale actions, exact-once completion, timed/untimed simulation, invalid scope, empty pools, and anonymous isolation | Human outcomes remain `NOT_RUN` | Automated prerequisite met for tested source SHA |
| MVP-11 viewport/touch and MVP-12 interactive keyboard | 18 static view contracts passed; these do not test rendered viewport or interactive keyboard behavior | No local Chromium/Playwright; physical Android and screen-reader use not run | `BLOCKED` automation; run same-device Android cases, then supported desktop/CI browser checks |
| Study lessons, sections, source references, and PDFs | Study-reference, resource readiness, source/library, and PDF pipeline tests passed; PDF check verified 47 files and hashes | Academic suitability and completeness not independently approved | P2 source-traceability review is nonblocking; retain partial-coverage wording and queue expert review |
| Flashcards and learner state | Flashcard HTTP/scheduling and anonymous export/deletion/isolation tests passed; Doctor flashcard and learner-state domains passed | No hosted browser run | No known code P0/P1 |
| Security/privacy boundary | Production route, session, CSRF, security, and privacy tests passed; local preview returned 404 for developer, private storage, and `.env` probes | Owner notice/contact/retention approval and provider evidence absent | Do not expose to internet testers until owner/provider checklist is complete |
| Acceptance Center | Canonical 12 cases, isolated session, recovery, honest outcomes, notes, export, exact build identity, loopback binding, production-mode learner preview, and clean shutdown verified | Real tester session not run | Ready for a supervised local Android test; preserve exported human outcomes |
| Deployment and rollback | Production preflight 22/22; smoke passed; package/release tests passed; isolated backup, dry-run, restore, and byte comparison passed | No live provider, HTTPS, permissions, or rollback against a deployed host | Owner/provider must complete live checks before internet access |
| Full release gate | Full Doctor completed with process exit 0, release gate PASS, P0=0, P1=0; 25/26 domains PASS and `SOURCE_TRACEABILITY=REVIEW` | Hosted exact-commit CI not run | Technical local gate passes; hosted verification remains pending |

## Diagnosis and implementation

No production learner-path defect was reproduced in the exercised flows. Three
existing recovery tests were failing because their simulated POST requests
omitted the application's required CSRF token. The production routes correctly
rejected those requests. The fixtures now obtain and submit valid tokens while
retaining their recovery, timer, scoring, and exact-once assertions. The scoped
test correction is committed as `6550ef1` (`test(quiz): use CSRF tokens in
recovery scenarios`). No production code, canonical questions, study material,
or learner records were changed.

The focused tests passed after correction:

- `QuizExperienceResilienceTest.php`
- `ExamSimulationHttpTest.php`
- `QuizStartFailureHttpTest.php`
- `QuizSessionResilienceTest.php`, `QuizCompletionHttpTest.php`, and
  `QuizStartServiceProductionContractTest.php`
- `GuidedLearningJourneyHttpTest.php` and
  `TenBoardLearnerJourneyHttpTest.php` (LET, CSE, CLE, NLE, PLE, CE, CPA, ME,
  REE, ARCH)
- `QuestionSelectionScopeRegressionTest.php`, `QuizResultActionProductionTest.php`,
  `ProductionQuizCsrfTest.php`
- `FlashcardHttpTest.php`, `FlashcardSchedulingTest.php`,
  `QuestionStudyReferenceTest.php`, `StudyPdfPipelineTest.php`,
  `StudyResourceReadinessTest.php`, `SourceStudyLibraryTest.php`
- `AnonymousLearnerPrivacyHttpTest.php`, `SecurityTrustBoundaryTest.php`,
  `SessionConfigurationTest.php`, `LearnerMobileAccessibilityViewTest.php`
- `AcceptanceCenterTest.php`, `DeploymentPreflightIsolationTest.php`,
  `ReleaseReadinessContractTest.php`, `ReleasePackageTest.php`,
  `ReleaseFlowStorageIsolationTest.php`
- `AccountHttpTest.php`, `AccountSecurityTest.php`, and
  `AccountBackupCliTest.php`

The encrypted account-backup test reported `DEPENDENCY_MISSING` because
`ext-sodium` is unavailable in this Termux PHP. Its CLI/security checks fail
closed. Production account registration remains disabled; do not use account
backup as an alpha requirement unless that extension is available and verified.

## Full Doctor evidence

The complete command was run once with direct file capture, separate from the
terminal/clipboard pipeline:

```sh
php tools/doctor.php --release --json > docs/reports/batch-698-doctor-release.json 2> docs/reports/batch-698-doctor-release.stderr.txt
```

- PHP process exit: **0**
- Release gate: **PASS**; release decision `RELEASE_READY_WITH_NONBLOCKING_DEBT`
- Domains: **25 PASS / 1 REVIEW of 26**
- P0 / P1: **0 / 0**
- Health: `REVIEW`, zero failed entities; this does not override the release
  gate. It reports coverage/content review signals.
- Remaining queue: P2 `SOURCE_TRACEABILITY` (nonblocking); P3 thin-leaf
  planning signal; P3 architecture visual backlog.
- Release flow storage: PASS; 109 fixture files, minimum observed free space
  about 4.26 GB, cleanup passed. Existing fixture/session directories were
  left untouched.
- Runtime: 1,965 seconds. The captured stderr contains only the Termux linker
  warning; the JSON report is complete.

The source-traceability review does not claim that source-only placements are
lesson-linked. Keep the 8,097 source-only placements distinct from verified
lesson references. No generic lesson mapping or academic approval was added.

## Twelve-case Android handoff

The canonical checklist and full case steps are in
[`docs/HUMAN_TESTER_READINESS.md`](../HUMAN_TESTER_READINESS.md). MVP-01–10 have
local automated evidence for the source checkpoint above. MVP-11–12 automated
browser status is `BLOCKED` because Chromium/Playwright is unavailable in this
Termux environment. Static view assertions are not a visual or keyboard pass.
All twelve `HUMAN_STATUS` values remain `NOT_RUN`.

For a real same-device Android run:

1. On the phone that contains this worktree, run `./tools/acceptance/start.sh`.
2. Open the printed `OPEN_THIS_URL` in Android Chrome on that phone. Confirm
   the build SHA, device/browser, and actual viewport in the Acceptance Center.
3. Follow MVP-01 through MVP-12 in the existing guide. Use a fresh isolated
   session for privacy export/deletion; never use real learner records.
4. For a defect, record case ID, build, device/browser/viewport, starting URL,
   steps, expected and actual behavior, severity/category, and a sanitized
   screenshot reference. The existing note and export flow is sufficient; do
   not send cookies, credentials, names, or learner records.
5. Review the exported Markdown/JSON, keep human outcomes truthful, and stop
   both local servers with Ctrl-C. The preview binds to `127.0.0.1`; it is not
   accessible to remote testers and does not verify HTTPS or hosting.

The current environment completed an isolated launcher smoke: the exact build
identity appeared, both listeners bound to loopback, the production learner
preview loaded, developer/private-file probes returned 404, and Ctrl-C shut
both servers down. The local Playwright/Chromium visual run, hosted browser,
site explorer, and all human testing remain **NOT RUN**.

## October 31 owner and release checklist

- [ ] Name the operator/legal entity and monitored learner-support contact.
- [ ] Approve the exact deployed privacy notice and record reviewer/date.
- [ ] Decide application/session retention and explain deletion limits for
  provider logs, backups, and external copies; do not invent a duration.
- [ ] Select a provider/region and verify PHP 8.1+, required extensions,
  private writable paths, storage driver, quotas, and backup access.
- [ ] Verify live HTTPS/renewal, trusted proxy handling, and `Secure`,
  `HttpOnly`, `SameSite=Lax` cookies.
- [ ] Verify provider logs/third parties, session isolation, filesystem
  permissions, and provider backup/deletion behavior; update disclosures only
  with approved evidence.
- [ ] Run the Android handoff with actual testers; keep human case results and
  sanitized evidence separate from automation.
- [ ] Publish the exact intended checkpoint deliberately, then collect hosted
  Doctor, preflight/smoke, ten-board, security/privacy, browser, and explorer
  results for that exact SHA. This batch did not publish or run hosted CI.
- [ ] Before opening access, repeat production preflight/smoke on the selected
  host, verify private-file denial and backup/restore, and document rollback.
- [ ] Keep Batch 695 academic review independent. Obtain subject-expert review
  before presenting the lessons as comprehensive or the pending questions as
  approved.

Use [`OWNER_PRIVACY_AND_HOSTING_DECISIONS.md`](../OWNER_PRIVACY_AND_HOSTING_DECISIONS.md),
[`PRODUCT_READINESS.md`](../PRODUCT_READINESS.md), and
[`RELEASE_RUNBOOK_V1.md`](../RELEASE_RUNBOOK_V1.md) as the canonical decision
records. The local isolated backup/restore rehearsal does not verify a provider
or live deployment. Product/public launch, human acceptance, HTTPS, owner
approval, hosted exact-commit evidence, and independent academic review remain
separate open gates.

## Data and protected state

The candidate retained 9,123 canonical questions and their IDs/answers,
question-use/statistics data, 47 study lessons and 47 PDFs, source/study
relationships, and learner/anonymous persistence. Tests used isolated fixtures;
no real learner records were edited. The B695 review branch, B696, B697, main,
HayaDoctor pin, two existing stashes, and unrelated dirty/untracked work were
preserved. A zero-byte-content (`[]`) untracked `storage/question-statistics.json`
was left untouched and excluded from all commits.

## Defect triage

- **Confirmed and fixed:** three recovery-test fixtures omitted valid CSRF
  tokens; corrected in the scoped test checkpoint above.
- **Code-owned P0/P1 remaining:** none observed in the executed local gates.
- **Nonblocking review:** source traceability (P2), thin-leaf planning and
  architecture visual backlog (P3).
- **Owner/external:** privacy approval, identity/contact, retention, provider,
  HTTPS, hosted exact-commit evidence, and real tester evidence.
- **Academic:** independent review remains pending; Batch 695's 276 questions
  remain isolated and unmodified.

**Next safe action:** owner completes the privacy/hosting decision record and
schedules real same-device Android testers on this loopback preview. Do not
provide internet-facing access until the required safeguards and provider
checks are approved and verified.
