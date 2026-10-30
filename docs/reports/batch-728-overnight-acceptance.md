# === BOARDPREP BATCH 728 ===

**Outcome: TECHNICALLY_COMPLETE**
**Final local status:** implementation, focused verification, and the mandatory Full Doctor release gate passed. Real-browser acceptance remains a separate open gate. Academic approval and public release remain deferred.

## BASELINE

- B727 tested SHA: `702e399a3b059edac530d63e2ca7f1a3a7253074`
- B727 final SHA: `2ddb5d5c0c692e94478c1e20ae638469fe7bf07f`
- HEAD at B728 recovery start: `0dbd46d5d2d1e971a0a5b8e446468bb7295bbd66`
- Tested implementation SHA: `525b00858b72ab50b184a45038d3a808586e3ab9`
- Branch/worktree: `batch-728-browser-acceptance-study-handoff` at `/data/data/com.termux/files/home/boardprep-batch-706`
- B727 Doctor baseline was PASS, P0=0, P1=0, P2=1 source-traceability review group (8 signals), P3=2 planning issues, and 78 critical release flows passed. Its evidence remains the baseline only; it is not a B728 Doctor result.
- The original session-wide start time was not recorded. The successful Full Doctor recovery run started at `2026-10-30T21:14:37Z`, generated its complete report at `2026-10-30T21:42:57Z`, and ran for 28m20s. No time was added for padding.
- At preflight, about 1.196 GB RAM was available. Codex Node processes were present; no application Node test, PHP test/Doctor, Playwright, or Chromium process was active. Twelve registered worktrees and two stashes were preserved. The historical dirty `/data/data/com.termux/files/home/BoardPrep` and HayaDoctor were untouched.

## EXECUTION

- `termux-wake-lock` was available and returned exit 0. `tmux` was available but had no existing session, so work stayed in the existing Codex session.
- Tested code commits: `0dbd46d5d2d1e971a0a5b8e446468bb7295bbd66` and `525b00858b72ab50b184a45038d3a808586e3ab9`. The latter is the implementation SHA used for the focused tests and Doctor attempt.
- A documented recovery checkpoint is in `docs/reports/batch-728-recovery-checkpoint.md`. The final evidence bundle is `docs/reports/batch-728-test-evidence.json`.
- At the outset, free space was modest relative to the historical Doctor threshold. After exact path, owner, mode, contents, process-reference, and creator-code inspection, eleven stale BoardPrep test fixtures were removed from Termux's temporary directory. Ten were static-storage fixtures made by `StaticStorageFixture.php` for the Batch 610 eligibility and cross-board tests; one was the isolated editor copy created by `QuestionEditorEligibilityTest.php`, whose `finally` cleanup had not run. No app storage, history, caches, dependencies, git objects, unknown temporary trees, or user files were deleted. The two older Batch 684 trees were retained because they include recovery/session snapshots and their ownership/purpose was not established enough to remove safely.
- Before the successful recovery run, Doctor measured 3,402,850,304 bytes available. Its minimum was 3,132,829,696 bytes, leaving 1,387,999,232 bytes above the 1.5 GiB safety floor plus 128 MiB runtime reserve. The flow runner measured a 270,020,608-byte peak drawdown; the reserve-inclusive requirement derived from that run was 2,014,851,072 bytes. Current free space was 3,389,382,656 bytes at `2026-10-30T21:45:01Z`. Doctor reports isolated fixture cleanup and session cleanup as PASS.

## BROWSER

- `REAL_BROWSER=NOT_EXECUTED`
- `LINUX_RUNNER=REQUIRES_AUTHORIZATION`
- The bounded runner check found no supported desktop Chromium on Termux/Android ARM64 and no authorized Linux runner available to this session. No unsupported browser install or large download was attempted.
- `npm run test:browser:contract` passed 8/8. This suite is Node/client-contract coverage and is **not rendered browser acceptance**.
- Existing workflow: `.github/workflows/browser-acceptance.yml`, Ubuntu 24.04, Node 22.23.3. Reproducible browser command: `npm run test:browser`; the workflow installs Chromium and runs its focused privacy, CSRF, fixture, driver, and browser checks first.
- Minimum owner action: authorize and run that existing workflow on an approved Linux runner, or provide an already authorized Linux browser runner. This session did not trigger CI, push, change secrets, or provision infrastructure.
- No real screenshots or traces were produced. Desktop or 320/360/390/430px rendered journeys remain open.

## FEATURES

- Completed-attempt history now has bounded 20-row pages, previous/next controls with status text and navigation labels, and owned links back to each saved result. An isolated HTTP fixture with 500 mixed-board attempts verified first/last pages, bounds, and foreign-owner denial.
- Exam review flags are captured as an additive version-1 `review_flag_snapshot` on the existing finalized attempt. The snapshot is bounded to the attempt's question IDs; finalization remains exactly once. The result page offers a Marked filter only where persisted snapshot data exists.
- Legacy attempts without a snapshot state that marks are unavailable; no historical marks are invented. Score, answer keys, deadlines, eligibility, and the existing attempts collection format were not changed.
- The result/history path exposes the stored explanation and existing legitimate study destinations: eligible mistake recovery and Mistake Notebook, exact published lesson-section links when present, registered source references otherwise, existing Notes context, missed-question Flashcards, and Smart Study. No fabricated link or explanation and no ineligible-pool fill were introduced.
- Static mobile/accessibility improvements include history pagination `nav` labels and status counts, wrapping 44px controls, and explicit unanswered-result styling/state. The view contract suite checked these structures; without Chromium, these are not visual/rendered claims for 320–430px.
- Implementation files: `app/Controllers/HistoryController.php`, `app/Controllers/QuizFlowController.php`, `app/Services/Quiz/ExamReviewFlagService.php`, `app/Services/Quiz/HistoricalQuizResultService.php`, `app/Services/Quiz/QuizResultService.php`, `app/Services/Quiz/Result/QuizResultAttemptFactory.php`, `app/Services/RepositoryHealth/Release/ReleaseReadinessService.php`, `app/Views/history/index.php`, `app/Views/quiz/result.php`, `public/assets/css/style.css`, and the corresponding history, exam, factory, view, and readiness tests under `tools/Tests/`.

## SOURCE & OPERATIONS

- The B727 source P2 group has eight signals. Recheck classification: seven are structural-scope signals without a concrete software correction; one Psychometrician scope/currentness record is genuinely pending (`UNKNOWN` currentness and no scope fit). The 1,049 exact-section links all contain section locators; 8,976 links are valid material-level associations. No missing exact locator, ambiguous citation state, or genuinely incorrect mapping was confirmed in this group.
- Human review remains open: 958 exact links have unknown/unreviewed status and 91 lack approval evidence. These are not resolved by the new diagnostic. The report now distinguishes structural scope from actionable currentness/review work and includes read-only signal details without changing the source gate or evidence hash.
- Separate source constraints for Q1536/Q1537 remain source-only; Q6403 remains excluded. No citation was invented, no answer or mapping was changed, and no reviewer was granted or approved.
- Production-mode preflight passed all 22 checks with zero warnings or failures. Read-only smoke passed preflight, application boot, content load, learner service load, and progress/history service load in 1,913.72 ms. This did not start a production HTTP server or deploy anything.
- Current local technical regression evidence includes production quiz CSRF, notes and flashcard ownership/persistence, exam/history ownership, stale/expired session, JSON fixture, and B717 anonymous/foreign/CSRF academic-gate denial checks. The successful Full Doctor aggregate passed all critical flow, security, integrity, configuration, and release-storage domains.

## PERFORMANCE

- Equivalent synthetic HTTP history fixture: 500 attempts, seven warm samples for each implementation. Median response was 798.054 ms on B727 final (`2ddb5d5`) and 304.774 ms on B728 implementation (`525b008`), a 61.8% median reduction. Rendered history rows fell from 500 to 20; HTML fell from 233,063 bytes to 15,750 bytes. This is isolated simulator timing, not production network latency.
- B727 returning-dashboard gains remain the comparison baseline: 100 attempts 1,163.781 → 259.055 ms; 500 attempts 1,823.449 → 592.220 ms.
- Memory and bank-read counts were not instrumented and are not claimed. Exam and Smart Study latency were not rebenchmarked because their query paths were not changed in B728.

## TESTS

- PASS: `npm run test:browser:contract` (8/8; non-rendered).
- PASS: `QuizResultAttemptFactoryTest.php`, `QuizResultViewContractTest.php`, `HistoryResultHttpTest.php` (including 500 attempts), `QuizCompletionHttpTest.php`, and `ExamSimulationHttpTest.php` (persisted data, replay, flag/answer/finalize concurrency, timer expiry, resume, and legacy behavior).
- PASS: `Batch711NotesBasedPracticeTest.php`, `Batch712MistakeRecoveryTest.php`, `StudyLibraryNavigationTest.php`, `SmartStudyHubServiceTest.php`, `StudyDashboardPreparedHistoryEquivalenceTest.php`, `TenBoardLearnerJourneyHttpTest.php`, `LearnerJourneyHttpTest.php`, `FlashcardHttpTest.php`, and `ProductionQuizCsrfTest.php`.
- PASS: `LearnerMobileAccessibilityViewTest.php` (26 source/view assertions; static only) and `ReleaseReadinessContractTest.php` (source-review diagnostics remain read-only and human review remains open).
- PASS after implementation commit: `php -d memory_limit=1G tools/Tests/Batch717AcademicGateClosureTest.php` verified all 276 proposals remain pending, no approvals/imports, denial paths, and unchanged protected canonical files. Its isolated fixture was removed on shutdown.
- PASS: PHP syntax for all 14 changed PHP files and `git diff --check`. JavaScript syntax was not applicable because no JavaScript files changed.
- PASS: production preflight and smoke above. Test records and benchmarks are in `docs/reports/batch-728-test-evidence.json`.

## DOCTOR

- The exact production release command was run at HEAD `880c06231f601ee82937ff21319984be130c18e7`: `APP_ENV=production APP_DEBUG=false DB_DRIVER=json APP_STORAGE_PATH=/data/data/com.termux/files/home/boardprep-batch-706/storage php -d memory_limit=1G tools/doctor.php --release --json`. This is the final B728 application code: the commits after implementation SHA `525b00858b72ab50b184a45038d3a808586e3ab9` changed only documentation.
- Exit code was 0. The complete JSON artifact `docs/reports/batch-728-full-doctor-recovery-20261030T211437Z.json` is 2,367,143 bytes, valid JSON, SHA-256 `df032739f03de4136e39bac69335d4e68f522d049da82d297c3d044dee25381d`. Its release decision is `RELEASE_READY_WITH_NONBLOCKING_DEBT`, `RELEASE_GATE=PASS`, P0=0, P1=0, P2=1, P3=2. Twenty-five release domains passed; `SOURCE_TRACEABILITY` remains `REVIEW`. Security, content, taxonomy, eligibility, and flashcard integrity domains all passed with no P0/P1 action.
- Doctor health is `REVIEW` with 11 passing checks, 3 warning groups, 1 review group, 835 findings (801 warning entities, 34 review entities), and zero failing entities. These are the existing source-review and planning signals; they do not block the technical gate.
- Flow-storage evidence records a 1,610,612,736-byte safety floor, a 134,217,728-byte runtime reserve, 92,412,632 fixture bytes, 3,132,829,696 minimum free bytes, `cleanup_status=PASS`, and `sessions_cleaned=true`. The 205-byte stderr artifact contains only the Termux linker warning. The prior zero-byte failed artifacts remain preserved as historical failures.
- The Doctor result is complete and verifies the B728 application code at the current tested SHA. The local B728 technical release gate is PASS. The separate real-browser, academic-approval, and public-release gates remain open/deferred.

## INTEGRITY

- Canonical question count: 9,123.
- Current protected hashes match: questions `73cdc775cd7240b57898868d67902d833db1171f947c8dcc89081ddaf46fb4be`; derived answer keys `d4db069ce60c026e214ea68068b8206178782879c61b92271d4b1abe7dc7aad5`; eligibility `611f01ca29421c0c890c351c503ab262e87bc551269d78427b9a0889260933a4`; source registry `7c640636dcd3ddd0f865106ba4506a5af5dcd4a02a2074c065f695da5ef2c271`; study links `0e0aebad84afd09e38e477f277bb8086e52eb9b4a2e591c6de41118060a5663d`; attempts `e968fd3e8a391065ac2314ab9bcff932fc572cbe6024e16b40569b694bbed84b`; weakness `37517e5f3dc66819f61f5a7bb8ace1921282415f10551d2defa5c3eb0985b570`; question statistics `4f53cda18c2baa0c0354bb5f9a3ecbe5ed12ab4d8e11ba873c2f11161202b945`; academic decisions `a2a6a868c613be6d09c3e8607599779eb87a302e90207410d99328a4dbba343d`; source reviews `ae9341dc97f54bc96b75a49bd06619c5bdbdf4abd1d5b9885851e1e76c9aa656`; Flashcard exclusions `236975ba94ef72c223ebe7f78a4725088ca53b7c20395edb52f39e5d5e2f7717`.
- Protected untracked `storage/academic-proposal-workflow.json`, `storage/flashcard_state.json`, and `storage/question-statistics.json` each remain SHA-256 `4f53cda18c2baa0c0354bb5f9a3ecbe5ed12ab4d8e11ba873c2f11161202b945`; none was edited, staged, or committed.
- B695 pending proposals: 276; approvals=0; imports=0; production learner mutations=0. B653/B704/B705 snapshot evidence, HayaDoctor, other worktrees, and stashes were preserved.

## DELIVERY

- Implementation commits: `0dbd46d5d2d1e971a0a5b8e446468bb7295bbd66`, `525b00858b72ab50b184a45038d3a808586e3ab9`.
- Documentation commit SHA is printed in the delivery message after commit; it is omitted here to avoid embedding a commit's own hash in itself.
- At the successful Doctor checkpoint, tracked application files were unchanged from implementation SHA `525b008`; the passing unique JSON and its stderr are retained as release evidence. Historical zero-byte Doctor artifacts and the three protected untracked storage files remain untouched. No push, merge, remote CI, deployment, reviewer grant, academic approval, or production release occurred.
- `CLIPBOARD_STATUS=UNVERIFIED`: stdin and argument-mode `termux-clipboard-set` returned exit 0, but `termux-clipboard-get` returned 0 bytes. Both complete-byte comparisons failed. The full report is printed as the terminal fallback.

## OUTCOME

- `TECHNICALLY_COMPLETE`: meaningful implementation, focused integration/security tests, operational preflight/smoke, integrity checks, and Full Doctor passed. Current protected data hashes match and the Doctor fixture/session cleanup passed.
- Local technical release gate: **PASS** with complete valid JSON, exit 0, `RELEASE_GATE=PASS`, P0=0, P1=0, and zero failing security/integrity domains.
- Real-browser acceptance: **OPEN / NOT EXECUTED** pending an authorized Linux runner. Academic approval: **DEFERRED**. Public release: **NOT APPROVED**.
- B729 entry gate: **ELIGIBLE** after this verified B728 technical PASS. The B729 branch and implementation had not yet started when this report was prepared.
