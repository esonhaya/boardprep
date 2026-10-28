=== BOARDPREP BATCH 722 ===

BASELINE
B721_FINAL_SHA=ea2b1ca48a50de34cdaf957e8980f71a3713d720
HEAD_BEFORE=ea2b1ca48a50de34cdaf957e8980f71a3713d720
HEAD_AFTER_IMPLEMENTATION=1f8b663aaa27662b03ff06cfca9cb7545a613ac1
HEAD_AFTER_FINAL=DOCUMENTATION_COMMIT (exact SHA is printed in final delivery; omitted here to avoid self-reference)
BRANCH=batch-722-performance-experience-hardening
WORKTREE=/data/data/com.termux/files/home/boardprep-batch-706
STORAGE_BEFORE=2,830,168,064 bytes (about 2.63 GiB)
STORAGE_AFTER=About 2.89 GB (2.70 GiB), observed after Doctor and final documentation staging

EXECUTION
START_TIME=2026-10-28 20:17:22 +08:00 (verified B722 branch checkout)
END_TIME=2026-10-28 22:18 +08:00 (final report and delivery attempt)
ELAPSED_TIME=Approximately 2 hours 1 minute from verified branch checkout to final report
ACTIVE_WORK_TIME_IF_MEASURABLE=NOT_MEASURED
WAKE_LOCK_REQUEST_STATUS=termux-wake-lock exited 0; this does not independently guarantee Android background execution
TMUX_STATUS=Installed; no active tmux session
INTERRUPTIONS=None observed during this run
RECOVERY_STATUS=Started from verified B721 documentation tip; no partial B722 implementation existed

IMPLEMENTATION
SOURCE_FILES_CHANGED=Learning history, progress, readiness, dashboard, insight, recommendation, subject/topic performance services; question eligibility and reuse; adaptive ranking and pool counting; exam submission and active-exam view/CSS; focused tests and benchmark scripts
FEATURES_IMPROVED=Cold dashboard/readiness, subject pool counting, eligibility setup, adaptive ranking, active exam save-state feedback and lost-response retries
DEFECTS_FIXED=Removed repeated blueprint collection reads and redundant history/selector scans; exact retries now confirm only an already-persisted answer without advancing twice; conflicting stale retries cannot overwrite the saved answer
WORKSTREAMS_COMPLETED=Cold-path profiling and optimization; adaptive/analytics equivalence; exam/session resilience; security and ownership regressions; browser workflow contracts
WORKSTREAMS_PARTIAL=Learner/mobile interface work addressed the demonstrated exam save-feedback gap; no broad redesign. Browser execution was unavailable. Notes and Flashcards were regression-tested and left unchanged.

PERFORMANCE
DASHBOARD_COLD_BEFORE=2,527.340 ms
DASHBOARD_COLD_AFTER=1,691.940 ms
DASHBOARD_WARM_BEFORE_AFTER=219.152 → 119.003 ms median
READINESS_COLD_BEFORE=1,562.342 ms
READINESS_COLD_AFTER=1,152.505 ms
READINESS_WARM_BEFORE_AFTER=88.380 → 67.120 ms median
CANONICAL_READS_BEFORE_AFTER=Question-bank reads 1 → 1; CSE Professional blueprint collection reads 41 → 1
ADAPTIVE_RANKING_BEFORE_AFTER=14.002/95.053/415.657 → 4.976/22.422/118.254 ms median for 150/1,200/5,000 candidates; selected ordering identical
FLASHCARD_PERFORMANCE=UNCHANGED; B722 did not establish a comparable before/after timing, and the existing one-bank-read optimization remains
PEAK_MEMORY=Dashboard 180,690,944 → 174,399,488 bytes; subject count 145,764,352 → 138,412,032 bytes; synthetic adaptive selector 10 MiB before/after
BENCHMARK_COMPARABILITY=Same device, scripts, fixtures and warm repetition counts before/after. Cold means first service invocation in-process; OS file cache was not cleared. These are service timings, not page-render or concurrency latency.

LEARNER EXPERIENCE
EXAM_SETUP=PASS; timed/untimed setup, exact pool eligibility and blueprint disclosures tested
SESSION_RESUME=PASS; refresh retains authoritative server-session answers and the original deadline
RESULTS=PASS; full/partial results, authoritative scoring and historical context tested
ADAPTIVE_PRACTICE=PASS; deterministic ordered selection equivalence and supported-board eligibility preserved
PROGRESS=PASS; empty state, historical performance and subject/topic attribution regressions passed
READINESS=PASS; exact eligibility and insufficient-history behavior preserved
RECOMMENDATIONS=PASS; recommendations route to eligible practice or exact published study relationships; unavailable resources retain truthful fallbacks
MISTAKE_NOTEBOOK=PASS in existing recovery regressions; historical mistakes were preserved
NOTES=UNCHANGED; isolated create/edit/reopen/CSRF/ownership and note-based practice regressions passed
FLASHCARDS=UNCHANGED; isolated HTTP/integration/selection/scheduling/ownership and quarantine exclusions passed

Exam save behavior is derived from answers currently stored in the active server session. The page announces the recorded count and says an internet connection is required to save or change answers. After a lost successful response, an identical stale request receives confirmation without a second advance. A conflicting stale request is rejected and leaves the stored answer intact. The system does not add offline answer storage or claim offline exam support.

MOBILE AND BROWSER
MOBILE_ISSUES_FOUND=Active exam did not clearly show how many answers were recorded or that saving requires connectivity; a retry after a lost response appeared as a generic stale request
MOBILE_ISSUES_FIXED=Added an accessible, wrapping answer-save status and exact-retry confirmation while preserving the server session as authority
ACCESSIBILITY=Status uses role=status and aria-live=polite. Existing semantic controls, focus and touch-target contracts passed; no rendered accessibility acceptance is claimed.
STATIC_VIEWPORT_TESTS=320, 360, 390 and 430 px contracts passed; CSS contracts are not visual rendering evidence
REAL_BROWSER_STATUS=NOT_EXECUTED; the Android/Termux browser preflight blocks local Chromium and no supported authorized Linux runner was available
CI_PREPARATION=Ubuntu 24.04/Playwright 1.63 workflow contracts passed locally (6/6). No remote workflow was dispatched.

SECURITY
AUTHENTICATION=Authentication, session configuration, learner privacy and synthetic journey checks passed
CSRF=Quiz and academic-import CSRF regressions passed
LEARNER_OWNERSHIP=Attempt/session, notes, flashcard and mistake-history isolation regressions passed
CACHE_ISOLATION=Request-local immutable reuse only; no learner-crossing cache or persistent learner cache added
ACADEMIC_IMPORT_GATE=PASS; pending proposals remained noncanonical and import authorization/safety checks passed
PRODUCTION_MUTATIONS=None; all synthetic learner writes used isolated fixtures

VERIFICATION
FOCUSED_TESTS=Batch722 pool-count equivalence, blueprint-read reuse, analytics reuse and adaptive ranking equivalence; isolated ExamSimulationHttpTest; QuizSessionResilienceTest; six browser workflow contracts
REGRESSION_TESTS=Dashboard/readiness/adaptive selection; ten-board journeys; mixed-board analytics; recommendations; results and mistake recovery; Notes; Flashcards; exam refresh/resume/expiry/concurrent finalization; authentication/CSRF/ownership; Developer authorization; academic import restrictions; Q6403 quarantine; Q1536/Q1537 source-only safeguards
FAILURES_FOUND=Two extra historical snapshot-pinned tests remain mismatched to later canonical/source state: Batch653CseReviewClosureTest's archived semantic hash and Batch704NursingCompletionTest's archived PDF manifest. The archived evidence was not rewritten; current source/readiness checks and Full Doctor pass.
REPAIRS=Updated the stale BlueprintExecutorProductionContractTest parameter-count expectation to its current four-required/six-total signature; added integration assertions for persisted answer count and safe stale retries
RETEST_RESULTS=Focused tests and relevant regressions passed; Full Doctor passed against the exact implementation SHA below
TESTED_SHA=1f8b663aaa27662b03ff06cfca9cb7545a613ac1

FULL DOCTOR
EXIT_CODE=0
RELEASE_GATE=PASS; RELEASE_READY_WITH_NONBLOCKING_DEBT
P0=0
P1=0
P2=1
P3=2
HEALTH_STATUS=REVIEW; 835 health signals (11 pass, 3 warn, 1 review groups), zero fail entities and zero fail count
STORAGE_REQUIRED=1,837,243,096 bytes; plus 512 MiB reserve threshold
MINIMUM_STORAGE_OBSERVED=2,590,318,592 bytes; minimum exceeded required storage plus 512 MiB
FIXTURE_CLEANUP=PASS; four existing fixture directories and four existing session directories were left untouched
RESULT_ARTIFACT=/data/data/com.termux/files/usr/tmp/batch722-full-doctor-1f8b663.json (2,353,216 bytes)
ARTIFACT_SHA256=2f9a4bccbd9b04644a31461afbf1ab2df1554ef3a9d324faec76426e6d69122b

INTEGRITY
CANONICAL_COUNT=9,123
QUESTION_HASH=73cdc775cd7240b57898868d67902d833db1171f947c8dcc89081ddaf46fb4be
ANSWER_KEY_HASH=d4db069ce60c026e214ea68068b8206178782879c61b92271d4b1abe7dc7aad5
ELIGIBILITY_HASH=611f01ca29421c0c890c351c503ab262e87bc551269d78427b9a0889260933a4
SOURCE_REGISTRY_HASH=7c640636dcd3ddd0f865106ba4506a5af5dcd4a02a2074c065f695da5ef2c271
STUDY_LINK_HASH=0e0aebad84afd09e38e477f277bb8086e52eb9b4a2e591c6de41118060a5663d
LEARNER_HASHES=Attempts e968fd3e8a391065ac2314ab9bcff932fc572cbe6024e16b40569b694bbed84b; weakness 37517e5f3dc66819f61f5a7bb8ace1921282415f10551d2defa5c3eb0985b570; question statistics 4f53cda18c2baa0c0354bb5f9a3ecbe5ed12ab4d8e11ba873c2f11161202b945
ACADEMIC_HASHES=Decisions a2a6a868c613be6d09c3e8607599779eb87a302e90207410d99328a4dbba343d; source reviews ae9341dc97f54bc96b75a49bd06619c5bdbdf4abd1d5b9885851e1e76c9aa656; proposal workflow unchanged
UNTRACKED_STORAGE_FILES=storage/academic-proposal-workflow.json, storage/flashcard_state.json, storage/question-statistics.json; all remain byte-identical at 4f53cda18c2baa0c0354bb5f9a3ecbe5ed12ab4d8e11ba873c2f11161202b945
FLASHCARD_EXCLUSIONS_HASH=236975ba94ef72c223ebe7f78a4725088ca53b7c20395edb52f39e5d5e2f7717
ALL_PROTECTED_HASHES=PASS; recomputed after Doctor and byte-equal to the saved B721 integrity map (map SHA-256 58877738fde75cb17e9238fd1d3b608acdff190423bee6a9a3a04777156687b2)
QUARANTINES=Q6403 remains excluded; Q1536/Q1537 remain source-only; tests pass

DELIVERY
IMPLEMENTATION_COMMITS=91db44b167f6921aac473596c7bf0b6bdbda064d; 1f8b663aaa27662b03ff06cfca9cb7545a613ac1
FINAL_DOCUMENTATION_COMMIT_SHA=PRINTED_IN_FINAL_DELIVERY_MESSAGE; OMITTED_FROM_THIS_COMMITTED_REPORT_TO_AVOID_SELF_REFERENCE
GIT_STATUS=Only the three pre-existing untracked storage JSON files remain; 12 worktrees and 2 stashes preserved; HayaDoctor gitlink dc4fcda730d9a922f726593746e34ca0c9c0e890 unchanged; historical dirty checkout left untouched (14 entries currently reported)
PUSH_STATUS=NOT_PUSHED
DEPLOY_STATUS=NOT_DEPLOYED
CLIPBOARD_STATUS=UNVERIFIED; termux-clipboard-set exited 0, but termux-clipboard-get exited 0 and returned 0 bytes (empty SHA-256 e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855); no retry

OUTCOME
B722_COMPLETED=YES; implementation, required verification and documentation are complete
REMAINING_TECHNICAL_BLOCKERS=No P0/P1 or security/integrity failures; historical snapshot-pin mismatches and lack of local Chromium are nonblocking
NONBLOCKING_DEBT=Health REVIEW with 1 P2 and 2 P3; 835 visible review/warning signals remain; no new academic decisions made
BROWSER_ACCEPTANCE=NOT_EXECUTED
DEFERRED_ACADEMIC_REVIEW=All 276 B695 proposals remain pending qualified review; approvals=0; imports=0
B723_ELIGIBLE_TO_START=YES after Batch 722 mandatory technical gates passed; Batch 723 was not started
