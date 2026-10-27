# BoardPrep Batch 721 — Adaptive Learning, Readiness & Progress Intelligence

**Outcome: COMPLETE.** Adaptive learning and readiness implementation `ef58ceaf653c0543518320e5285712a12bb1f0c7`, final LET-variant readiness guard `460b145b51ae171984cb172286f3c6e8320f41b3`, and recovered-session adaptive-helper autoload repair `933d83fe9ad6419dc1cb14b2a253c4cfdcbb1b5c` are committed on `batch-721-adaptive-learning-readiness`. The current Full Doctor release gate passed with P0=0 and P1=0. Human academic review remains deferred; no pending proposal was approved or imported.

## Baseline and recovery

- Worktree: `/data/data/com.termux/files/home/boardprep-batch-706`.
- B720 implementation: `4ae65f299af48895591af677446076e5a45a552b`.
- B720 final report/documentation tip and B721 base: `f65de54404678d07a2a0fb09665f5ceff1808220`. Git history and `batch-720-verification.json` confirm the final release gate PASS, P0=0 and P1=0.
- The existing B721 branch was recovered at `460b145b51ae171984cb172286f3c6e8320f41b3`; the adaptive implementation and final readiness guard were already committed. The only recovery edit at entry was the in-progress checkpoint; it was preserved and completed. No implementation was restarted and no second worktree was created.
- During this continuation, two adaptive helper tests exposed missing legacy autoloader entries. Five mappings were added in `933d83f`; all 12 targeted adaptive, analytics, mobile, recommendation, eligibility and attempt-placement tests passed with PHP assertions enabled.
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
- The five global adaptive helper classes now resolve through the legacy autoloader map. Their isolated orderer/partitioner tests and the adaptive extraction contract had failed before assertions because those entries were missing; the repaired tests pass with assertions enabled.

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

The recovered implementation passed 55 focused and integration test commands with zero assertion failures, plus the prior Full Doctor release suite. This continuation then ran 12 targeted tests after the autoloader repair; all 12 passed with assertions enabled. Coverage includes 12-profile adaptive evaluation; multi-board mapping and learner journeys; subject/topic/readiness calculations; cold starts, invalid and incomplete variants, thin pools, invalid routes, deterministic selection and no duplicates; simulations and blueprint allocation; results, Mistake Notebook, Notes, Flashcards, Study Library, CSRF, privacy, Developer tools, academic import authorization, the B717 276-proposal gate and Q6403/Q1536/Q1537 safeguards. Current-HEAD PHP syntax and report consistency checks are recorded in the verification report.

One early `LearnerJourneyHttpTest` destination assertion failed after eligibility IDs replaced a matching human-readable label. The code now preserves the display label only when both values resolve to the same configured subject; the retest passed and confirmed exact-once persistence. A separate test-loop filename typo was corrected by running the actual `Batch712MistakeRecoveryTest.php`; no assertion failure remained.

Full Doctor command:

```sh
APP_ENV=production APP_DEBUG=false DB_DRIVER=json APP_STORAGE_PATH=/data/data/com.termux/files/home/boardprep-batch-706/storage php -d memory_limit=1G tools/doctor.php --release --json
```

- Current tested HEAD: `4a05da3c1da64f74ca170aca583b168ae0b19743`; application-source commit `933d83fe9ad6419dc1cb14b2a253c4cfdcbb1b5c`; exit code `0`.
- Current JSON artifact: `/data/data/com.termux/files/usr/tmp/batch721-full-doctor-933d83f.json`, 2,353,200 bytes, SHA-256 `59b2f0bde8c7dd34715eff6675aebc89deb9f0f8d5ae05fed377ee622f44f7ad`; generated at `2026-10-27T22:12:07Z`. Release decision `RELEASE_READY_WITH_NONBLOCKING_DEBT`; gate `PASS`; P0=0, P1=0, P2=1, P3=2. Health is `REVIEW` with 835 review/warning signals and zero fail entities. 25 of 26 release domains passed; Source Traceability remains `REVIEW` with eight review signals and zero hard source defects.
- The earlier successful Doctor artifact on `460b145` remains historical evidence; this current-HEAD run is the release result for the continuation.
- Storage flow PASS: required available 1,837,243,096 bytes; available before Doctor fixtures 3,237,502,976 bytes; minimum observed 2,973,507,584 bytes; cleanup PASS. Existing fixture and session directories remained untouched.
- Remaining Doctor queue is one nonblocking P2 Source Traceability item and P3 Content and Feature items, all post-release. Academic review remains independently deferred.

Exact command groups, tests, exit codes, metrics and integrity values are in `batch-721-verification.json` and the related JSON reports.

## Delivery

- Implementation commit: `ef58ceaf653c0543518320e5285712a12bb1f0c7`.
- Recovered-session autoloader testability fix: `933d83fe9ad6419dc1cb14b2a253c4cfdcbb1b5c`.
- Documentation is committed separately. To avoid a self-referential report, the final documentation commit SHA is returned outside the report after that commit.
- Final Git status is expected to retain only the three named pre-existing untracked storage files; no unrelated file was staged.
- Push, merge and deployment were not performed. Batch 722 was not started.
- The final report is delivered through Termux clipboard only if full-content SHA-256 readback matches. Clipboard outcome and the final documentation commit SHA are recorded in the execution fields and final delivery message.

## Known limits and remaining external work

No unresolved in-scope P0/P1/security/integrity defect remains. Legacy mixed-board answer events without saved placements cannot be safely assigned to a subject and are disclosed instead of guessed. The current readiness eligible-pool scan measured about 2.20 seconds cold and 107 ms warm on this device; that is a measured optimization candidate, not a release blocker. Browser-rendered acceptance remains unavailable on this Android/Termux runtime. Qualified human academic review of the 276 pending B695 proposals remains deferred. No Batch 722 work was started.

See also: `batch-721-adaptive-evaluation.json`, `batch-721-readiness-analytics.json`, `batch-721-recommendations.json`, `batch-721-performance.json`, `batch-721-mobile-ux.json`, `batch-721-verification.json`, and `batch-721-recovery-checkpoint.md`.

## Final execution fields

```text
START_TIME=2026-10-28 05:41:45 PHT (active continuation; original Batch 721 active start time is not recorded)
RECOVERY_WINDOW_START=2026-10-28 05:41:45 PHT
END_TIME=2026-10-28 06:18 PHT (final documentation commit and clipboard readback; minute-resolution time)
ACTUAL_ELAPSED_TIME=Approximately 37 minutes for this continuation (05:41:45–06:18 PHT); cumulative original B721 duration is not measurable
PRODUCTIVE_RUNTIME_ESTIMATE_IF_MEASURABLE=Approximately 37 minutes in this continuation, including the autoloader repair, 12 focused tests, Full Doctor and finalization; prior batch time is unmeasurable. No runtime padding was used.
TERMUX_WAKE_LOCK_REQUEST_STATUS=termux-wake-lock available; command exit status 0 during this continuation; Android wake-lock state not independently verified
TMUX_SESSION_STATUS=tmux installed; existing boardprep721 session available and attached with active Codex; session not recreated or terminated
INTERRUPTIONS=At least one prior recovered/interrupted B721 state; exact count unknown. No interruption during this continuation.
RECOVERY_STATUS=Recovered the completed B721 branch, fixed the adaptive helper autoload test gap, reran targeted tests and current-HEAD Full Doctor; no duplicate worktree created
HEAD_BEFORE=3294b8d584f42cb46bfcaf2ec7927b4b108c8018
HEAD_AFTER_IMPLEMENTATION=933d83fe9ad6419dc1cb14b2a253c4cfdcbb1b5c
HEAD_AFTER_FINAL=Supplied in final delivery message; not embedded to avoid self-reference
WORKSTREAMS_COMPLETED=A-H; mobile static, responsive-contract, and HTTP checks passed
WORKSTREAMS_PARTIAL=Real-browser viewport and device accessibility acceptance NOT_EXECUTED
FEATURES_IMPLEMENTED=Adaptive topic selection; answer-event and scoped topic/subject analytics; transparent readiness coverage; actionable recommendations; ten-board practice/simulation consistency; mobile readiness views; request-local bank-read reduction
ISSUES_FIXED=LET readiness now withholds exact subject pool counts until level and specialization validate; current metrics and recommendations preserve saved board/variant attribution; adaptive helper tests now resolve through the legacy autoloader
TESTS_EXECUTED=55 prior focused regression commands plus 12 current-implementation tests and current-HEAD Full Doctor; PHP lint, JSON parsing, hashes and git diff checks
TESTS_PASSED=55 prior focused commands; 12/12 current targeted commands with assertions enabled; current-HEAD Full Doctor release gate PASS
TESTS_FAILED=0 final assertion failures. Two initial helper-test invocations failed before assertions due missing autoload mappings; repaired code and all 12 current tests passed. Prior incomplete Doctor attempt is recorded separately and was followed by a successful run.
FULL_DOCTOR_STATUS=PASS on 4a05da3c1da64f74ca170aca583b168ae0b19743; exit 0; JSON artifact /data/data/com.termux/files/usr/tmp/batch721-full-doctor-933d83f.json; 2,353,200 bytes; SHA-256 59b2f0bde8c7dd34715eff6675aebc89deb9f0f8d5ae05fed377ee622f44f7ad; generated 2026-10-27T22:12:07Z
P0=0
P1=0
P2=1
P3=2
BROWSER_STATUS=NOT_EXECUTED; Chromium unavailable and Playwright import rejects Android
STORAGE_BEFORE=3,162,260 KiB immediately before current Full Doctor; required available floor 1,837,243,096 bytes
STORAGE_AFTER=3,155,208 KiB immediately after current Full Doctor; minimum during Doctor 2,973,507,584 bytes
INTEGRITY_STATUS=PASS; 9,123 canonical questions; all protected hashes match before/after; zero production learner mutations
B695_PROPOSALS=276 pending and noncanonical
ACADEMIC_IMPORTS=0; approvals=0
PUSH_STATUS=NOT_PERFORMED
DEPLOY_STATUS=NOT_PERFORMED
CLIPBOARD_STATUS=UNVERIFIED; set/get exited 0, but readback was empty; final post-commit byte count and hashes are in the verification JSON
```

This continuation's clipboard attempt used the report SHA-256 `7765bd05cd721f44b1248353864426c3812b9b07475853eb2735628a21d7aba9`; `termux-clipboard-set` and `termux-clipboard-get` both exited 0, but readback was 0 bytes with SHA-256 `e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855`. Status is `UNVERIFIED`. A final post-commit retry and exact hashes will be recorded in the verification JSON. The report is printed in full in the delivery message.
