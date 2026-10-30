# BOARDPREP BATCH 729

## BASELINE

- `B728_FINAL_SHA=f509107a0748a2b9a7119f74007f2b92aa6c1ba2` (B728 documentation close; B728 tested application SHA `525b00858b72ab50b184a45038d3a808586e3ab9`).
- `B728_RELEASE_GATE=PASS`; its complete JSON was valid, Doctor exit was 0, P0=0, P1=0. Artifact SHA-256: `df032739f03de4136e39bac69335d4e68f522d049da82d297c3d044dee25381d`.
- `HEAD_BEFORE=f509107a0748a2b9a7119f74007f2b92aa6c1ba2`.
- `HEAD_AFTER_IMPLEMENTATION=2b83e812e8238094df81f17258be739e98109d41`.
- `HEAD_AFTER_FINAL=printed after the final documentation commit; intentionally not embedded in this report`.
- Branch: `batch-729-learner-acceptance-production-hardening`.
- Worktree: `/data/data/com.termux/files/home/boardprep-batch-706`.
- The predecessor gate was checked before B729 branch creation. The dirty historical `/data/data/com.termux/files/home/BoardPrep`, HayaDoctor, 12 registered worktrees, and two stashes were preserved.

## EXECUTION

- Start: `2026-10-30T21:46:15Z`.
- `END_TIME=2026-10-30T22:59:54Z` (final engineering evidence captured).
- `ELAPSED_TIME=1h13m39s`; this is the actual recorded interval from start through implementation, regression, Doctor, integrity review, and evidence preparation. The 8–12 hour target was not padded.
- `termux-wake-lock` was available and acquired for the work. After Doctor, `termux-wake-unlock` was invoked and exited 0.
- Recovery used the existing worktree and Codex session; `tmux` existed but had no session. No duplicate checkout or competing editor was started.
- Checkpoints: `3bd53e624a85e8faf0e07b328a67914c63216371` (history filters), `50e61c8936797d2dc962b23ba2292b894a2026f5` (checkpoint evidence), and `2b83e812e8238094df81f17258be739e98109d41` (skipped review and variant-scoped recovery).

## FEATURES

- `HISTORY_IMPROVEMENTS`: completed attempts can be filtered by registered board and subject. Unregistered or legacy values are explicit; no board or subject is inferred. Counts, pagination, and the safe result-to-history return link preserve the active filters. Results and pages remain bounded to 20 rows and enforce owner identity.
- `RESULT_REVIEW`: saved attempts preserve review marks where present. Legacy records without review snapshots retain an honest unavailable state. Incorrect, unanswered, and marked review states remain distinct.
- `MISTAKE_RECOVERY`: skipped questions now show their stored explanation and navigable eligible retry or related practice. The skipped list has bounded 20-row pagination. Recovery remains eligibility-filtered, excludes the missed/skipped source items from related practice, and preserves the exact LET/CSE variant context. Finalized attempt context is used; repeated finalization does not duplicate mistakes.
- `SMART_STUDY`: reused existing recommendation and study destinations; related practice retains the shared topic/concept and current board/variant. No second recommendation engine or invented academic material was introduced.
- `MOBILE_ACCESSIBILITY`: the existing mobile styles stack history filters, keep controls at the existing 44px target, wrap long content, and expose history and skipped-page updates through live status. Static view contracts cover narrow layouts and focus/state behavior. There was no rendered browser acceptance.
- `SOURCE_REVIEW_WORKFLOW`: the existing read-only diagnostic was sufficient; no additional concrete software defect was established. Eight Full Doctor source-traceability signals remain visible as human review debt. No citations, source mappings, academic answers, approvals, or imports were changed.
- `OPERATIONAL_HARDENING`: production preflight passed 22 checks; production smoke passed five checks. Full Doctor's release test storage and cleanup checks passed. No deployment or live-service mutation occurred.

## BROWSER

- `RUNNER=Android/Termux ARM64; no supported desktop Chromium and no already authorized Linux runner was available`.
- `REAL_TESTS_EXECUTED=0`; `REAL_TESTS_PASSED=0`; `REAL_TESTS_FAILED=0`.
- `REAL_BROWSER=NOT_EXECUTED`; `LINUX_RUNNER=REQUIRES_AUTHORIZATION`.
- No screenshot or trace was produced. Static Node/browser contracts are not rendered evidence.
- Reproducible path: existing `.github/workflows/browser-acceptance.yml`, Ubuntu 24.04, Node 22.23.3, `npm run test:browser`.
- Minimum owner action: authorize/provide a supported Linux browser runner and run that workflow. No workflow dispatch, push, secrets change, or infrastructure provisioning was performed.

## PERFORMANCE

- Comparable synthetic 500-attempt mixed-board history workload, seven warm samples: B728 median `142.664 ms`; B729 median `151.384 ms` (+6.1%). HTML was `15,749` bytes before and `17,616` after; both views rendered 20 rows. This is local isolated HTTP simulator timing, not production network latency.
- B729 skipped-review route dispatch with 500 persisted synthetic skipped-question attempts: seven warm samples `[203.451, 203.269, 204.066, 205.505, 205.585, 207.650, 206.607] ms`; median `205.505 ms`, range `203.269–207.650 ms`, 20 review links, 13,900 HTML bytes. No comparable before sample was collected.
- Preserved previously measured B727 dashboard results: 100 attempts `1163.781 → 259.055 ms`; 500 attempts `1823.449 → 592.220 ms`. These are prior synthetic measurements and were not re-run in B729.
- Memory use and canonical-bank read counts were not instrumented. No savings are claimed. The primary remaining performance concern is the small (+6.1%) history-render benchmark movement, which remains bounded and is within local run variability absent repeated paired trials.

## VERIFICATION

- `TESTED_SHA=2b83e812e8238094df81f17258be739e98109d41`; no application source changed after the passing Full Doctor.
- PASS: `HistoryResultHttpTest.php` (persisted histories of 0, 1, 20, 100, and 500 attempts; mixed boards/subjects; filters, legacy values, page bounds, ownership and safe return links).
- PASS: `MistakeNotebookSkippedPaginationTest.php` (persisted 0/1/20/100/500 histories; bounded continuation, invalid page clamping and foreign-owner hiding).
- PASS: `Batch712MistakeRecoveryTest.php` (saved explanations, retry and same-topic practice eligibility, LET isolation, ownership/expiry, flags and persistence, exact-once behavior, and Q6403 quarantine).
- PASS: `TenBoardLearnerJourneyHttpTest.php` (LET, CSE, CLE, NLE, PLE, CE, CPA, ME, REE, ARCH).
- PASS: `LearnerJourneyHttpTest.php`, `ExamSimulationHttpTest.php`, and `QuizCompletionHttpTest.php` (resume/refresh, saved answer and review flag, stale requests, timer expiry, finalization replay/race and persisted result).
- PASS: `SmartStudyHubServiceTest.php`, `StudyDashboardPreparedHistoryEquivalenceTest.php` (0/1/25/100/500 histories), `Batch711NotesBasedPracticeTest.php`, `StudyLibraryNavigationTest.php`, and `FlashcardHttpTest.php`.
- PASS: `ProductionQuizCsrfTest.php`; `Batch717AcademicGateClosureTest.php` (276 proposals stay pending, no approvals/imports, unauthorized requests denied); `LearnerMobileAccessibilityViewTest.php` (28 static/source assertions; not rendered).
- PASS: `npm run test:browser:contract` (8/8 non-rendered contracts), changed PHP syntax, production preflight (22), production smoke (5), and `git diff --check`.
- A first recovery-test run used synthetic attempts without normalized score metrics; its fixture was corrected. An earlier 512 MiB test process reloaded the full eligible bank; the harness was changed to reuse already-loaded eligible rows, and the final focused run passed at `memory_limit=1G`. These were test-fixture/harness repairs; expected results were not weakened.

## FULL DOCTOR

- Command: `APP_ENV=production APP_DEBUG=false DB_DRIVER=json APP_STORAGE_PATH=/data/data/com.termux/files/home/boardprep-batch-706/storage php -d memory_limit=1G tools/doctor.php --release --json`.
- Tested application SHA: `2b83e812e8238094df81f17258be739e98109d41`.
- Artifact: `docs/reports/batch-729-full-doctor-20261030T222450Z.json`; valid JSON, 2,367,368 bytes; exit code 0.
- `RELEASE_GATE=PASS`; `P0=0`; `P1=0`; `P2=1`; `P3=2`; health `REVIEW`; 0 failing entities.
- 25 of 26 release domains passed. `SOURCE_TRACEABILITY=REVIEW` remains nonblocking editorial/human-review debt. Security sanity and integrity domains passed; no unresolved security or integrity failure was reported.
- Storage: available before `3,333,312,512` bytes; calculated required available `1,837,243,096`; Doctor minimum observed `3,070,017,536`; runtime reserve `134,217,728`; fixture `92,412,632`; cleanup `PASS`, `sessions_cleaned=true`. The observed minimum exceeded the computed requirement by `1,232,774,440` bytes.
- Artifact SHA-256: `6ab2cb0e886076e0cd45f976139c08bb90a03520c46d8d05c1ef60378fc2086f`. Stderr contains only the Termux linker warning. The exit code is saved beside the JSON artifact.

## INTEGRITY

- Canonical count: `9,123`.
- All protected hashes match: questions `73cdc775cd7240b57898868d67902d833db1171f947c8dcc89081ddaf46fb4be`; answer keys `d4db069ce60c026e214ea68068b8206178782879c61b92271d4b1abe7dc7aad5`; eligibility `611f01ca29421c0c890c351c503ab262e87bc551269d78427b9a0889260933a4`; source registry `7c640636dcd3ddd0f865106ba4506a5af5dcd4a02a2074c065f695da5ef2c271`; study links `0e0aebad84afd09e38e477f277bb8086e52eb9b4a2e591c6de41118060a5663d`; attempts `e968fd3e8a391065ac2314ab9bcff932fc572cbe6024e16b40569b694bbed84b`; weakness `37517e5f3dc66819f61f5a7bb8ace1921282415f10551d2defa5c3eb0985b570`; question statistics `4f53cda18c2baa0c0354bb5f9a3ecbe5ed12ab4d8e11ba873c2f11161202b945`; academic decisions `a2a6a868c613be6d09c3e8607599779eb87a302e90207410d99328a4dbba343d`; source reviews `ae9341dc97f54bc96b75a49bd06619c5bdbdf4abd1d5b9885851e1e76c9aa656`; flashcard exclusions `236975ba94ef72c223ebe7f78a4725088ca53b7c20395edb52f39e5d5e2f7717`.
- The three protected pre-existing untracked storage files remain unchanged and empty at SHA-256 `4f53cda18c2baa0c0354bb5f9a3ecbe5ed12ab4d8e11ba873c2f11161202b945`: `storage/academic-proposal-workflow.json`, `storage/flashcard_state.json`, `storage/question-statistics.json`. They were not staged or committed.
- Learner state, academic state, source data, and canonical data were not modified. B695 proposals remain 276 pending/noncanonical. Q6403 stays quarantined; Q1536/Q1537 stay source-only.
- The four pre-existing untracked B728 diagnostics are preserved: `batch-728-full-doctor-final.json`, `batch-728-full-doctor-final.stderr.txt`, `batch-728-full-doctor.json`, `batch-728-full-doctor.stderr.txt`.

## SOURCE REVIEW

- Full Doctor reports eight source traceability signals: seven structural scope/crosswalk signals that need semantic review, and one Psychometrician currentness/scope item pending (`UNKNOWN`, no established scope fit).
- The evidence review found 1,049 exact-section links with locators and 8,976 material-level associations. No missing exact locator, confirmed ambiguous citation, or confirmed incorrect mapping was established in the eight-signal group. 958 exact links remain unreviewed/unknown; 91 lack approval evidence.
- The source P2 and human review backlog are retained. No reviewer was authorized, no citation or approval was invented, and none of the 276 pending proposals was approved or imported.

## DELIVERY

- Implementation commits: `3bd53e624a85e8faf0e07b328a67914c63216371`, `2b83e812e8238094df81f17258be739e98109d41`.
- Documentation checkpoint commit: `50e61c8936797d2dc962b23ba2292b894a2026f5`.
- Final documentation commit SHA is printed in terminal delivery and is deliberately not embedded in this committed report.
- `GIT_STATUS`: implementation and evidence are committed; expected remaining untracked files are the four preserved B728 diagnostics and the three protected storage files.
- Push: `NOT_PERFORMED`; deploy: `NOT_PERFORMED`; remote CI: `NOT_DISPATCHED`.
- `CLIPBOARD_STATUS=VERIFIED` only if the final saved report and `termux-clipboard-get` readback compare byte-for-byte; otherwise the status is changed to `UNVERIFIED` and the complete report is printed as terminal fallback.

## OUTCOME

- `B729_COMPLETED=YES` for local technical completion.
- `TECHNICAL_GATE=PASS`; no mandatory technical blocker remains.
- `REAL_BROWSER_ACCEPTANCE=OPEN / NOT_EXECUTED`; approved Linux runner required.
- `ACADEMIC_APPROVAL=DEFERRED`; human source/currentness/scope review remains open.
- `PUBLIC_RELEASE=NOT_APPROVED`; local Doctor PASS is not public-release approval.
- `B730_ELIGIBLE_TO_START=YES after the B729 local technical gate`; B730 was not started.
