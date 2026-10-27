# BoardPrep Batch 721 — Adaptive Learning, Readiness & Progress Intelligence

**Outcome: COMPLETE.** Adaptive learning and readiness implementation `ef58ceaf653c0543518320e5285712a12bb1f0c7`, delivery documentation `817dd96`, and the final LET-variant readiness guard `460b145b51ae171984cb172286f3c6e8320f41b3` are committed on `batch-721-adaptive-learning-readiness`. The final-HEAD Full Doctor gate passed with P0=0 and P1=0. Human academic review remains deferred; no pending proposal was approved or imported.

## Baseline and recovery

- Worktree: `/data/data/com.termux/files/home/boardprep-batch-706`.
- B720 implementation: `4ae65f299af48895591af677446076e5a45a552b`.
- B720 final report/documentation tip and B721 base: `f65de54404678d07a2a0fb09665f5ceff1808220`. Git history and `batch-720-verification.json` confirm the final release gate PASS, P0=0 and P1=0.
- The existing B721 branch was recovered at `460b145b51ae171984cb172286f3c6e8320f41b3`; the adaptive implementation and final readiness guard were already committed. The only recovery edit at entry was the in-progress checkpoint; it was preserved and completed. No implementation was restarted and no second worktree was created.
- Existing worktrees, both stashes, the dirty B695 checkout, HayaDoctor gitlink and unrelated project data were retained.
- Free space immediately before Doctor was about 3.07 GiB. Doctor measured 3,296,985,088 bytes available before fixtures, a 1,837,243,096-byte required floor (including its reserve), and 3,037,720,576 bytes minimum observed.
- The earlier B721 session did not maintain an active-work timer. Its cumulative productive duration and start time cannot be reconstructed from commit timestamps. The resumed verification window began about 2026-10-28 01:01 PHT and continued through final report delivery; its wall time is recorded in the final execution fields below. No eight-hour duration is claimed and no runtime was padded.

## Adaptive learning

The existing adaptive path ranked topic weakness and exposure but could repeatedly concentrate on one topic and its ranking overhead was measurable. The selector now combines recent and overall topic performance, repeated mistakes, question exposure and topic coverage while retaining the original request's already-filtered eligibility pool and difficulty slots.

- Weakness signal: 40% overall accuracy gap and 60% recent accuracy gap when there are at least two topic observations; otherwise use overall accuracy only. Repeated mistakes add a bounded bonus of at most 20 points.
- Recent exposure penalty is bounded at 48 points. Topic concentration penalty is bounded at 36 points. Previously seen questions are still eligible and may return when the pool requires it.
- Candidate ordering is deterministic for an equivalent request/history. Canonical question IDs are deduplicated; board/subject/variant eligibility, quarantine, blueprint count and difficulty allocation remain authoritative. A too-small difficulty pool preserves the existing safe fallback instead of silently substituting an ineligible question.
- Practice explanations describe the deterministic evidence and limits; they do not claim AI reasoning or mastery.
- The isolated evaluator covers 12 profiles: cold start, one attempt, repeated mistakes, weak subject, multiple weak topics, high performance, uneven coverage, recent exposure, stale history, tiny pool, removed eligibility and cross-board exposure. It checks deterministic ranking, uniqueness, fallback and scoped weakness. Synthetic rankings do not prove learning outcomes. Production eligibility was separately tested against all 10 board configurations.

The session-start snapshot now stores the exact resolved board, subject and candidate variant beside each attempt question. This fixes ambiguous cross-board question attribution for new history without modifying canonical questions. Legacy mixed-board answers that lack this placement are retained in overall history, omitted from unsafe subject/topic comparisons and disclosed as unattributed.

## Learning analytics and preparation overview

`SubjectPerformanceService` now derives board/variant-scoped answer-event counts, quiz-score averages, observed accuracy, recent accuracy, earlier-window comparisons and last-practice information. Completed records are normalized and duplicate session/attempt identities are collapsed. Unanswered questions affect quiz-score denominator but not answer-accuracy denominator. Unique-question totals are not inferred from old summaries that lack question IDs.

- Recent subject accuracy uses the five most recent deduplicated completed attempts. It is shown only after at least five answer events and two subject attempts. Trend compares the latest five with the preceding five and uses a five-percentage-point threshold.
- A single correct answer does not imply proficiency. Unattempted subjects are `UNASSESSED`; a configured subject with no current eligible questions is `NO_ELIGIBLE_PRACTICE_POOL`, not a learner weakness.
- Topic and subject summaries use saved session-time placements where available. Missing legacy mixed-board placements are not guessed from the question's canonical source-board taxonomy.
- The dashboard and Progress page now show an internal preparation coverage snapshot, per-subject evidence states, repeated-mistake context, current pool availability, caveats and practical next actions. No pass probability, mastery score or unsupported official threshold is generated.
- CPA stays without invented official weights. Existing BoardPrep configurations remain distinct from sourced official blueprint context. CSE and LET require the applicable candidate variant before exact pool coverage and targeted starts are offered. Final HEAD additionally requires a valid LET level and specialization before showing eligible subject pool counts; missing or incomplete variants return `VARIANT_REQUIRED`. Architecture's registered content-depth caveat remains visible.

The mixed-board fixture has two CSE sessions with five Verbal Ability answer events (three correct, 60% accuracy) and two General Information events (both correct). One legacy cross-board answer is disclosed as unattributed. These are deterministic synthetic test values, not learner data.

## Recommendations and continuity

Recommendations are deterministic, bounded and tied to current available actions: review the Mistake Notebook, retry an eligible weak topic or unassessed subject, start variant-aware balanced practice, or open the published Study Library. Existing exact source-linked study relationships remain the only basis for exact lesson destinations. If a pool or exact relation is unavailable, the learner receives an honest Study Library fallback rather than a fabricated link.

The ranking gives less weight to a stale weakness after recent recovery, reduces repeated exposure when same-difficulty alternatives exist, and spreads multi-question sessions across similarly ranked topics. It does not permanently exclude old questions. Cross-board exposure to a shared canonical question reduces repetition; weakness evidence remains board/subject/variant scoped.

Existing result review, Mistake Notebook, Notes and Flashcards routes and persistence were preserved and exercised by isolated HTTP/service regressions. No historical attempt or recovery state was rewritten.

## Mobile

Dashboard and Progress readiness components now wrap long board/variant labels and explanations, use a compact one-column narrow-screen layout, retain semantic headings/status text, expose visible focus and touch-size affordances, and label empty, variant-required and unavailable-pool states.

Static/mobile contracts cover 320, 360, 390 and 430 px assumptions and passed. PHP and HTTP view contracts also passed. Chromium is not installed. The repository Playwright package is present but its import fails on Android with `Unsupported platform: android`; real browser rendering, screenshots, screen-reader output and device visual acceptance are therefore **NOT EXECUTED**. No browser acceptance is claimed.

## Performance

All values are single-device sequential measurements; the OS filesystem cache was not cleared.

| Path | Measured workload/result | Interpretation |
| --- | --- | --- |
| Adaptive ranking | 1,200 synthetic candidates; 30 attempts/600 answer events; select 20; 1 warm-up + 7 runs. Median 70.029 ms before topic-queue refinement; final-HEAD median 51.297 ms (50.861–51.959 ms), 6 MiB peak allocation. | 26.7% lower ranking time in this controlled synthetic harness. No production latency or learning-outcome claim. |
| Subject analytics | 30 attempts/600 events; final-HEAD cold 55.334 ms, warm median 38.467 ms. | Seven measured warm runs; synthetic in-memory history. |
| Topic analytics | Same fixture; final-HEAD cold 35.154 ms, warm median 31.906 ms. | Same-process warm indexes. |
| Full study dashboard | Same fixture; final-HEAD cold 4,051.090 ms, warm median 265.174 ms; peak process allocation 180,690,944 bytes. | Cold clears application request-local indexes only; first-page readiness and study-link construction remain measurable optimization candidates. |
| Readiness | Same fixture; final-HEAD cold 2,201.340 ms, warm median 107.069 ms. | Exact eligible-pool count is retained; improve only with comparable profiling and identical filters. |
| Recommendation bank reads | Final-HEAD ten-board reproduction: prior per-board topic-index pattern 10 canonical-bank reads/10,933.717 ms; shared request-local index 1 read/2,857.294 ms, 0 extra warm reads. | Exactly 90% fewer question-bank reads; same-process wall time is cache-sensitive. No persistent or learner-crossing cache. |
| Integrated readiness/study indexes | Final-HEAD: one canonical-bank read for readiness plus ten study contexts; cold 3,848.321 ms, warm 9.759 ms, no extra warm reads. | Instrumented read-only wrapper; no storage mutations. |
| Ten-board selector | 9,123 canonical records, all 10 active boards, one canonical-bank read, zero storage mutations, 168,833,024 bytes peak. | Eligibility/unique-selection contract, not a multi-user load test. |
| Flashcards | Three HTTP/controller renders read the canonical bank once each; 2,509.9/1,839.5/1,883.4 ms, median 1,883.4 ms, peak 207.5 MiB. | B720's one-read-per-request optimization remains; no new B721 latency improvement claimed. |

Detailed inputs and limitations are in `batch-721-performance.json`.

## Security, academic boundaries and data integrity

No new public endpoint, database migration or persistent derived learner cache was added. Existing authentication, ownership and CSRF boundaries remain in place. Privacy, CSRF, manipulated-route, attempt ownership, Notes, Mistake Notebook, Flashcard session/owner and academic import controls passed their isolated regressions. Read-only benchmark storage wrappers throw on mutation; all synthetic learner fixtures used isolated storage.

The B718–B720 Developer review classifications were not altered. The 276 B695 proposals remain noncanonical, pending qualified academic review and excluded from learner selection. There were no real approvals, reviewer grants, assignments or imports. Q6403 and Q1536/Q1537 quarantine/source-only safeguards remain intact.

Before and after Full Doctor, the following storage hashes matched exactly. The three pre-existing untracked files remain byte-for-byte unchanged and untracked.

| Protected data | SHA-256 |
| --- | --- |
| Canonical question file; count 9,123 | `73cdc775cd7240b57898868d67902d833db1171f947c8dcc89081ddaf46fb4be` |
| Answer-key map | `d4db069ce60c026e214ea68068b8206178782879c61b92271d4b1abe7dc7aad5` |
| Eligibility | `611f01ca29421c0c890c351c503ab262e87bc551269d78427b9a0889260933a4` |
| Source registry | `7c640636dcd3ddd0f865106ba4506a5af5dcd4a02a2074c065f695da5ef2c271` |
| Study links | `0e0aebad84afd09e38e477f277bb8086e52eb9b4a2e591c6de41118060a5663d` |
| Attempts | `e968fd3e8a391065ac2314ab9bcff932fc572cbe6024e16b40569b694bbed84b` |
| Weakness | `37517e5f3dc66819f61f5a7bb8ace1921282415f10551d2defa5c3eb0985b570` |
| Academic decisions | `a2a6a868c613be6d09c3e8607599779eb87a302e90207410d99328a4dbba343d` |
| Source reviews | `ae9341dc97f54bc96b75a49bd06619c5bdbdf4abd1d5b9885851e1e76c9aa656` |
| Flashcard exclusions | `236975ba94ef72c223ebe7f78a4725088ca53b7c20395edb52f39e5d5e2f7717` |
| `question-statistics.json` (pre-existing untracked) | `4f53cda18c2baa0c0354bb5f9a3ecbe5ed12ab4d8e11ba873c2f11161202b945` |
| `flashcard_state.json` (pre-existing untracked) | `4f53cda18c2baa0c0354bb5f9a3ecbe5ed12ab4d8e11ba873c2f11161202b945` |
| `academic-proposal-workflow.json` (pre-existing untracked) | `4f53cda18c2baa0c0354bb5f9a3ecbe5ed12ab4d8e11ba873c2f11161202b945` |

The final-HEAD Doctor's simulator-state isolation and release-storage checks passed; pre-existing fixture/session directories were left untouched. The complete recovery before/after hash maps are `/data/data/com.termux/files/usr/tmp/batch721-recovery-20261028/integrity-before.json` and `integrity-after.json`; both maps match the saved protected baseline.

## Verification and Full Doctor

The recovered final HEAD passed 55 fresh focused and integration test commands with zero failures, plus the Full Doctor release suite. Coverage includes 12-profile adaptive evaluation; multi-board mapping and learner journeys; subject/topic/readiness calculations; cold starts, invalid and incomplete variants, thin pools, invalid routes, deterministic selection and no duplicates; simulations and blueprint allocation; results, Mistake Notebook, Notes, Flashcards, Study Library, CSRF, privacy, Developer tools, academic import authorization, the B717 276-proposal gate and Q6403/Q1536/Q1537 safeguards. Current-HEAD PHP syntax and report consistency checks are recorded in the verification report.

One early `LearnerJourneyHttpTest` destination assertion failed after eligibility IDs replaced a matching human-readable label. The code now preserves the display label only when both values resolve to the same configured subject; the retest passed and confirmed exact-once persistence. A separate test-loop filename typo was corrected by running the actual `Batch712MistakeRecoveryTest.php`; no assertion failure remained.

Full Doctor command:

```sh
APP_ENV=production APP_DEBUG=false DB_DRIVER=json APP_STORAGE_PATH=/data/data/com.termux/files/home/boardprep-batch-706/storage php -d memory_limit=1G tools/doctor.php --release --json
```

- Final tested SHA: `460b145b51ae171984cb172286f3c6e8320f41b3`; exit code `0`. An earlier same-SHA attempt returned exit 1 with empty stdout and only a Termux linker warning, so it was treated as incomplete and not reused.
- Final JSON artifact: `/data/data/com.termux/files/usr/tmp/batch721-full-doctor-460b145-recovery.json`, 2,353,205 bytes, SHA-256 `5b02bf3ad71f3ed746d0ea24184ca8a7cc808861997c5fb77ff6da8d25f2815d`; generated at `2026-10-27T17:43:53Z`. The earlier `ef58cea` PASS artifact is retained as historical evidence.
- Release decision `RELEASE_READY_WITH_NONBLOCKING_DEBT`; gate `PASS`; P0=0, P1=0, P2=1, P3=2. Health is `REVIEW` with 835 warning/review signals and zero fail entities. 25 of 26 release domains passed; Source Traceability remains `REVIEW` with eight review signals and zero hard source defects.
- Storage flow PASS: required available 1,837,243,096 bytes; available before Doctor fixtures 2,779,484,160 bytes; minimum observed 2,406,301,696 bytes; cleanup PASS. Existing fixture and session directories remained untouched.
- Remaining Doctor queue is one nonblocking P2 Source Traceability item and P3 Content and Feature items, all post-release. Academic review remains independently deferred.

Exact command groups, tests, exit codes, metrics and integrity values are in `batch-721-verification.json` and the related JSON reports.

## Delivery

- Implementation commit: `ef58ceaf653c0543518320e5285712a12bb1f0c7`.
- Documentation is committed separately. To avoid a self-referential report, the final documentation commit SHA is returned outside the report after that commit.
- Final Git status is expected to retain only the three named pre-existing untracked storage files; no unrelated file was staged.
- Push, merge and deployment were not performed. Batch 722 was not started.
- The final report is delivered through Termux clipboard only if full-content SHA-256 readback matches. Clipboard outcome and the final documentation commit SHA are recorded in the execution fields and final delivery message.

## Known limits and remaining external work

No unresolved in-scope P0/P1/security/integrity defect remains. Legacy mixed-board answer events without saved placements cannot be safely assigned to a subject and are disclosed instead of guessed. The current readiness eligible-pool scan measured about 2.20 seconds cold and 107 ms warm on this device; that is a measured optimization candidate, not a release blocker. Browser-rendered acceptance remains unavailable on this Android/Termux runtime. Qualified human academic review of the 276 pending B695 proposals remains deferred. No Batch 722 work was started.

See also: `batch-721-adaptive-evaluation.json`, `batch-721-readiness-analytics.json`, `batch-721-recommendations.json`, `batch-721-performance.json`, `batch-721-mobile-ux.json`, `batch-721-verification.json`, and `batch-721-recovery-checkpoint.md`.

## Final execution fields

```text
START_TIME=Not recorded; B721 branch checkout was 2026-10-27 04:05 PHT and first implementation commit was 05:03:03 PHT
RECOVERY_WINDOW_START=2026-10-28 01:01 PHT
END_TIME=2026-10-28 01:59 PHT (after final clipboard readback and report finalization)
ACTUAL_ELAPSED_TIME=Cumulative B721 duration not measurable; resumed verification segment was approximately 58 minutes wall time (01:01–01:59 PHT)
PRODUCTIVE_RUNTIME_ESTIMATE_IF_MEASURABLE=Not measurable across the interrupted batch; the earlier session had no active-work timer. No runtime padding was used.
TERMUX_WAKE_LOCK_REQUEST_STATUS=termux-wake-lock available; command exit status 0; Android wake-lock state not independently verified
TMUX_SESSION_STATUS=tmux installed; no active tmux session; existing Codex session left running
INTERRUPTIONS=At least one prior recovered/interrupted state; exact count unknown. No interruption during this verification segment.
RECOVERY_STATUS=Recovered existing B721 branch and completed final-HEAD verification; no duplicate worktree created
HEAD_BEFORE=460b145b51ae171984cb172286f3c6e8320f41b3
HEAD_AFTER_IMPLEMENTATION=460b145b51ae171984cb172286f3c6e8320f41b3
HEAD_AFTER_FINAL=Supplied in final delivery message; not embedded to avoid self-reference
WORKSTREAMS_COMPLETED=A-H; mobile static, responsive-contract, and HTTP checks passed
WORKSTREAMS_PARTIAL=Real-browser viewport and device accessibility acceptance NOT_EXECUTED
FEATURES_IMPLEMENTED=Adaptive topic selection; answer-event and scoped topic/subject analytics; transparent readiness coverage; actionable recommendations; ten-board practice/simulation consistency; mobile readiness views; request-local bank-read reduction
ISSUES_FIXED=LET readiness now withholds exact subject pool counts until level and specialization validate; current metrics and recommendations preserve saved board/variant attribution
TESTS_EXECUTED=55 focused/relevant regression commands plus final Full Doctor; four PHP lint checks, six modified-JSON validations, and git diff --check
TESTS_PASSED=55 focused commands; final Full Doctor release gate PASS; all finalization checks PASS
TESTS_FAILED=0 focused assertions. First final-HEAD Doctor attempt exited 1 with empty output and was classified incomplete; full rerun passed.
FULL_DOCTOR_STATUS=PASS on 460b145b51ae171984cb172286f3c6e8320f41b3; exit 0; artifact SHA-256 5b02bf3ad71f3ed746d0ea24184ca8a7cc808861997c5fb77ff6da8d25f2815d
P0=0
P1=0
P2=1
P3=2
BROWSER_STATUS=NOT_EXECUTED; Chromium unavailable and Playwright import rejects Android
STORAGE_BEFORE=About 2.6 GiB at initial recovery inspection; 2,780,061,696 bytes immediately before Full Doctor
STORAGE_AFTER=2,813,063,168 bytes immediately after Full Doctor; minimum during Doctor 2,406,301,696 bytes
INTEGRITY_STATUS=PASS; 9,123 canonical questions; all protected hashes match before/after; zero production learner mutations
B695_PROPOSALS=276 pending and noncanonical
ACADEMIC_IMPORTS=0; approvals=0
PUSH_STATUS=NOT_PERFORMED
DEPLOY_STATUS=NOT_PERFORMED
CLIPBOARD_STATUS=UNVERIFIED; two attempts returned 0-byte readback despite set/get exit status 0
```

Two attempts to set the full report (stdin and argument forms) returned exit 0, but `termux-clipboard-get` also returned exit 0 with 0 bytes each time; neither SHA-256 matched the report. The clipboard status remains `UNVERIFIED`. The verification JSON records the final post-commit readback attempt and exact hashes. Because complete content could not be verified, this report is printed in full in the delivery message.
