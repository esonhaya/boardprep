# BoardPrep Batch 729 Recovery Checkpoint

Recorded `2026-10-30T21:53:32Z` (UTC). B729 started at `2026-10-30T21:46:15Z`; elapsed at this checkpoint is 7m17s. No time was added for padding.

## Baseline and predecessor gate

- Worktree: `/data/data/com.termux/files/home/boardprep-batch-706`
- Branch: `batch-729-learner-acceptance-production-hardening`
- B729 base and B728 final documentation SHA: `f509107a0748a2b9a7119f74007f2b92aa6c1ba2`
- B728 application implementation SHA: `525b00858b72ab50b184a45038d3a808586e3ab9`; B728 documentation commits after it changed no application files.
- B728 Full Doctor artifact: `docs/reports/batch-728-full-doctor-recovery-20261030T211437Z.json`, 2,367,143 bytes, SHA-256 `df032739f03de4136e39bac69335d4e68f522d049da82d297c3d044dee25381d`; complete valid JSON, exit 0, `RELEASE_GATE=PASS`, P0=0, P1=0, P2=1, P3=2. Health is REVIEW with zero failing entities. Security and integrity domains passed; 25 release domains passed and source traceability remains REVIEW.
- The B728 technical gate was rechecked before B729 branch creation. The browser gate remains separate and open.
- At B729 start, the existing historical dirty `/data/data/com.termux/files/home/BoardPrep`, HayaDoctor, 12 worktrees, and 2 stashes were preserved. This worktree has no staged or tracked user changes. Three protected storage JSON files remain untracked and unchanged.

## Runner and resources

- The bounded browser check found Android `aarch64` with no supported desktop Chromium executable. `REAL_BROWSER=NOT_EXECUTED`; `LINUX_RUNNER=REQUIRES_AUTHORIZATION`. The existing `.github/workflows/browser-acceptance.yml` uses Ubuntu 24.04 and Node 22.23.3 with `npm run test:browser`; no remote workflow was triggered.
- `termux-wake-lock` is available and was reacquired before the B728 Full Doctor run; it remains active. `tmux` has no existing session. Work is continuing in the existing Codex session.
- At this checkpoint, free space is 3,391,684,608 bytes; available RAM is approximately 1.12 GB. No application PHP, Node test, or Doctor process is running. The B728 Doctor's measured reserve-inclusive drawdown requirement was 2,014,851,072 bytes; no B729 Full Doctor run has started.

## Verified milestone — completed attempt history

- Commit `3bd53e624a85e8faf0e07b328a67914c63216371` adds registered board and subject filters to the existing completed-attempt history. Unknown or legacy values appear in explicit unregistered buckets; the filter does not infer a board or subject. The summary follows the active filters, and filtered pagination preserves its filters.
- Each owned result link carries its page and filter context. Historical results return to a reconstructed, same-origin `/history` URL; invalid filters, foreign-owned attempts, and external return targets cannot change ownership or create an open redirect.
- Mobile CSS stacks the history filters at narrow widths, controls meet the 44px target, and filter/paging updates expose live status. No browser-rendered acceptance is claimed.
- PASS: `php -d memory_limit=1G tools/Tests/HistoryResultHttpTest.php`. It inspects persisted isolated attempts across 0, 1, 20, 100, and 500 rows, mixed LET/CSE boards, registered and unregistered subjects, foreign-owner denial, result ownership, page bounds, invalid filters, and safe return targets. The fixture cleanup completed.
- PASS: `php tools/Tests/QuizResultViewContractTest.php` (14 result assertions); `php tools/Tests/LearnerMobileAccessibilityViewTest.php` (26 static assertions); PHP syntax for all changed PHP files; `git diff --check`.
- PASS: `npm run test:browser:contract` (8/8 Node contracts). This is non-rendered coverage; no Playwright browser acceptance ran.
- Equivalent 500-attempt mixed-board synthetic HTTP history benchmark, 7 warm samples each: median `142.664 ms` before and `151.384 ms` after (+6.1%); HTML `15,749` → `17,616` bytes; both rendered 20 rows. The route remains bounded; these are local simulator timings, not network measurements. The fixture was removed and verified absent.

## Outstanding work

- Completed the skipped-review continuation work, variant-scoped review context, saved skipped-question explanations, eligible related practice, and bounded skipped-review pagination. Commits: `2b83e812e8238094df81f17258be739e98109d41` (implementation); `50e61c8936797d2dc962b23ba2292b894a2026f5` (checkpoint documentation).
- PASS on implementation SHA `2b83e812e8238094df81f17258be739e98109d41`: `Batch712MistakeRecoveryTest.php`, `MistakeNotebookSkippedPaginationTest.php`, `HistoryResultHttpTest.php`, `TenBoardLearnerJourneyHttpTest.php`, `LearnerJourneyHttpTest.php`, `ExamSimulationHttpTest.php`, `QuizCompletionHttpTest.php`, `SmartStudyHubServiceTest.php`, `StudyDashboardPreparedHistoryEquivalenceTest.php`, `Batch711NotesBasedPracticeTest.php`, `ProductionQuizCsrfTest.php`, `Batch717AcademicGateClosureTest.php`, `StudyLibraryNavigationTest.php`, `FlashcardHttpTest.php`, `LearnerMobileAccessibilityViewTest.php` (static, not rendered), and `npm run test:browser:contract` (8/8, not rendered). Modified PHP syntax and `git diff --check` passed; production preflight (22 checks) and smoke (5 checks) passed.
- Full Doctor ran after the final application change at SHA `2b83e812e8238094df81f17258be739e98109d41`. Artifact `batch-729-full-doctor-20261030T222450Z.json` is valid JSON, 2,367,368 bytes, SHA-256 `6ab2cb0e886076e0cd45f976139c08bb90a03520c46d8d05c1ef60378fc2086f`, exit 0, `RELEASE_GATE=PASS`, P0=0, P1=0, P2=1, P3=2. Health is REVIEW with zero failing entities; 25/26 release domains passed and source traceability remains REVIEW. Doctor measured the minimum free space at 3,070,017,536 bytes against a 1,837,243,096-byte run requirement; fixture cleanup passed.
- B729 report, machine-readable evidence, and Doctor artifact are being finalized for the documentation commit. At the latest resource check, available disk was about 3.33 GB and available RAM about 1.03 GB; the only Node processes were the active Codex host. No fixture directory matching the B729 mistake benchmark remained.
- No further application change is planned after the passing Doctor. Real browser acceptance remains `NOT_EXECUTED`; `LINUX_RUNNER=REQUIRES_AUTHORIZATION`. Academic approval and public release remain separate, deferred gates. B729's local technical gate is PASS; do not start B730 in this session.
