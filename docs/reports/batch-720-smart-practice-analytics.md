# BoardPrep Batch 720 — Smart Practice, Exam Simulation & Learning Analytics

**Result: COMPLETE — technical release gate PASS.** The implementation is committed on `batch-720-smart-practice-analytics`. The B719 academic proposal queue remains separate and pending qualified human review.

## Baseline and recovery

- B719 implementation: `6f3159f2277ed78c70cc177a4c6636afe16639e7`.
- B719 final documentation: `156e2e45f52a7c0dd3f36a725e8d4c8ace19dc14`; the final B719 report and release record were inspected. Release gate PASS, P0=0, P1=0.
- B720 began from that verified documentation tip in the existing worktree `/data/data/com.termux/files/home/boardprep-batch-706`; branch `batch-720-smart-practice-analytics` was created without making another worktree.
- HEAD at final implementation verification: `4ae65f299af48895591af677446076e5a45a552b`.
- Implementation commits: `c4a58f8c614b44f06e36fc8baf7d6f72a89404c4`, `384cc8e9a616cc401a3c44aa157a1133dc187e9d`, `ccb9196d99050b5851eb632076732008c1034841`, `a5472677c239e3b3f5e49830217bdb622ef83902`, `4ae65f299af48895591af677446076e5a45a552b`.
- Approximate free space was 3.5 GiB at preflight and 3,500,992 KiB after the Doctor run. The earliest implementation commit is timestamped 2026-10-27 00:24:16 +08:00; the final Doctor evidence was generated at 02:51:32 +08:00. An active-work timer was not maintained, so no eight-hour duration is claimed.
- The historical dirty `/data/data/com.termux/files/home/BoardPrep`, two existing stashes, all registered worktrees, and HayaDoctor gitlink were preserved. The three pre-existing untracked storage files remain untracked and were not staged.

## Implemented improvements

### Blueprint-aware examination setup and generation

Before B720, the four sourced CE, ME, REE and Architecture board blueprints resolved to no runtime requests because their sourced area IDs did not resolve to the application's taxonomy identities. Existing internal board configurations continued to work. B720 repaired those mappings using existing source-backed configuration; it did not modify question eligibility, question content, answer keys, or passing thresholds.

All 10 active board configurations were inspected. Runtime-valid, deduplicated candidate-pool sizes were LET 1,987 (variant-dependent), CSE 2,115 (Professional 1,930; Subprofessional 2,113), CLE 554, NLE 878, PLE 461, CE 698, CPA 1,132, ME 511, REE 842 and Architecture 484. The pool counts are a configuration audit, not a promise that every selected variant has that many eligible questions.

| Board configuration | Preserved distribution status |
| --- | --- |
| CE | Existing sourced weights 35/35/30 |
| ME | Existing current-program weights 35/35/30 |
| REE | Existing current-program weights 25/30/45 |
| Architecture | Existing source weights 30/30/40 across Areas A/B/C |
| LET, CSE, CLE, NLE, PLE | Existing BoardPrep targets retained; they are not relabeled as official weights |
| CPA | Official weighting unavailable in the registry; no weights were invented |

For Architecture, the mapped Area A allocation remains combined at 30%; the internal split over two mapped sections is 50/50 and is not presented as an official subarea distribution. The configured Area B pool is Utilities and Construction; limited coverage of the broader official area title is recorded as a content-depth caveat. No official blueprint completeness is inferred where source configuration does not establish it.

The existing allocation and eligibility services still control rounding and candidate choice. The setup page now previews the selected board's distribution, updates when board or question count changes, clears an incompatible selected subject, explains focused-subject scope, and wraps into a narrow single-column layout. Exam mode continues to use eligible items and existing quarantine filters. Selection remains random where it was random before; no explicit seed API exists, so this report does not claim seeded determinism. Deterministic largest-remainder allocation and duplicate/eligibility contracts are tested. A requested count is not filled by bypassing eligibility; the existing insufficient-pool behavior remains authoritative.

### Adaptive practice

The practice-mode path now connects the existing adaptive selector instead of treating adaptive mode as a label only. It builds a bounded learner context from up to 30 recent completed attempts, considers repeated question/topic mistakes and board/subject/variant context, and uses exposure in the last 10 attempts to reduce immediate repetition. A bounded top-K ranking prioritizes relevant candidates while retaining the established difficulty-slot mix. Exam-mode selection is unchanged. The selector uses only eligible canonical questions and falls back safely when the available pool is small; pending B695 proposals are not candidates.

The quiz receives a concise rationale such as recent mistakes or weak-topic focus. The mechanism is deterministic statistics and ranking, not AI or a claim of mastery. Repeated mistakes and exposure are bounded; no learner confidence is inferred from a single response.

### Learner analytics and recommendations

Learner progress now distinguishes pooled correct-answer events from average quiz score. Recent answer accuracy uses up to five latest completed, deduplicated attempts and is withheld until five answered events exist. The trend compares recent completed quiz scores; the dashboard graph is labeled “Quiz score over time,” not accuracy. Empty and small-sample states avoid asserting mastery. Legacy attempt summaries do not contain sufficient question identity to infer unique-question counts, so the UI does not manufacture that statistic.

Existing registered subject/topic performance and published study relationships remain the basis for recommendations. Results, Mistake Notebook, retries, study destinations, Notes and Flashcards were exercised through isolated regression journeys. B720 did not add a speculative recovery subsystem or invent exact lesson-to-question mappings.

### Flashcard read-path efficiency

A scoped Flashcards render previously loaded the canonical question bank twice. The controller/service now reuses one request-local question snapshot for filter-option and card derivation, including eligibility context. No persistent cache or cross-account learner-state cache was introduced. In three isolated sequential renders, bank reads fell from 2 to 1 per request; median time fell from 2,912.7 ms to 1,880.7 ms (35.4%). Peak PHP memory stayed 207.5 MiB.

### Mobile and accessibility

The examination setup preview is responsive at the existing narrow-screen breakpoint, uses wrap-safe labels, keeps labeled keyboard-operable select controls, and exposes the focused-subject explanation as a status message. Static/mobile contracts passed. Actual 320/360/390/430 px rendering, screenshots, screen-reader testing and real Chromium acceptance were not executed in this Android/Termux runtime. Browser acceptance is **NOT EXECUTED**; Node and HTTP contracts are not represented as rendered-browser evidence.

## Technical audit, security and review boundaries

The actionable defects demonstrated in this batch were the non-resolving sourced blueprint mappings, the unconnected practice adaptive path, the analytics score/accuracy label ambiguity, redundant Flashcard bank loading, and a final canonical subject identity mismatch. The last issue was exposed by Full Doctor: REE study-library routing and Architecture cross-board mapping disagreed about canonical subject identity. `QuestionEligibilityIndex` and `BlueprintResolverService` now prefer configured `subject_id`, retaining the legacy section-ID fallback. The affected blueprint, cross-board and 10-board HTTP journey tests passed after the repair.

No authentication, authorization, CSRF, timer, scoring or answer-key rule was weakened. B720 changes do not trust client timer values, change score authority or mutate historical attempts. Flashcard snapshots are request-local, not shared between learner identities. All mutation and learner-history tests used isolated fixtures; production learner storage was not used for synthetic writes.

The B718 Developer Question Repository classifications remain intact and distinct from technical eligibility. No developer-only flags are exposed through learner paths by these changes. The 276 B695 proposals remain noncanonical and pending. There were no fabricated reviewer identities, grants, assignments, decisions, approvals or imports. Q6403 remains quarantined; Q1536/Q1537 retain source-only study-link handling.

No canonical question text, answer key, taxonomy, source identity, study link, quality finding, academic decision, or publication state was changed. Technical quality signals are not treated as academic approval. Existing Developer review flags remain visible to authorized Developer users and pending proposal import gates remain enforced.

## Performance evidence

All measurements are device-specific, sequential fixture results, not production load guarantees.

| Path | Before | After | Interpretation |
| --- | ---: | ---: | --- |
| Adaptive ranking overhead above baseline selector | 334.72 ms | 145.29 ms | 56.6% lower added ranking cost after bounded top-K; adaptive selection remains slower than non-adaptive selection |
| Adaptive selector median | 1,212.49 ms | 1,025.43 ms | Five measured selections after one warm-up; the non-adaptive median was 880.14 ms in the after run |
| Flashcard scoped request | 2 bank reads; 2,912.7 ms median | 1 read; 1,880.7 ms median | 50% fewer bank reads; 35.4% lower three-run median; memory unchanged at 207.5 MiB |
| Ten-board setup preview | No valid baseline for previously unresolved sourced boards | 42.25 ms first pass; 20.51 ms warm in-process pass | 32 preview rows, 2 MiB peak; zero question-bank reads by service dependency inspection, not an I/O counter |
| Recent accuracy aggregation | No controlled benchmark | Not measured | No performance claim |

The adaptive candidate benchmark used 9,123 raw canonical records, 8,899 runtime-valid deduplicated candidates, 20 selected items, and synthetic 30-attempt/600-answer history; no learner storage was mutated. PHP peak memory was 132 MiB in the after run. Timing variability and lack of browser, production-history and concurrent-load measurements are documented in `batch-720-performance.json`.

## Verification

Focused and relevant B711–B719 regressions passed in isolated fixtures. The complete named regression inventory, tested revisions, initial failure/repair record, storage values and Doctor artifact are in `batch-720-verification.json`. Key coverage included blueprint setup/allocation, eligible selection and duplicates, adaptive ranking, analytics/recommendations, exam HTTP lifecycle, all 10 board learner journeys, mobile accessibility, Notes, Mistake Notebook, Flashcards and ownership/CSRF, academic import/review gates, Developer tools, source/study relations, and protected eligibility/quarantine behavior.

The first Full Doctor on the pre-repair implementation exposed failures in `CrossBoardCanonicalReuseTest` and `TenBoardLearnerJourneyHttpTest`; its derived P0 findings were not suppressed. The subject identity defect was repaired in `4ae65f299af48895591af677446076e5a45a552b`, affected tests were rerun, and Full Doctor was run again against that exact HEAD.

Final command:

```sh
APP_ENV=production APP_DEBUG=false DB_DRIVER=json APP_STORAGE_PATH=/data/data/com.termux/files/home/boardprep-batch-706/storage php -d memory_limit=1G tools/doctor.php --release --json
```

- Tested SHA: `4ae65f299af48895591af677446076e5a45a552b`.
- Exit code: `0`; valid JSON artifact: `/data/data/com.termux/files/usr/tmp/batch720-full-doctor-4ae65f299af4.json` (SHA-256 `3ef2c8ebb0b7b393fe3cb3874cc828fdbdf77913db86a1e872cd816e69047dc6`).
- Release gate: **PASS**, `RELEASE_READY_WITH_NONBLOCKING_DEBT`; P0=0, P1=0, P2=1, P3=2. No failure entities. Overall repository health is `REVIEW` with 835 informational/review signals (801 warning entities, 34 review entities); this is not a release-gate failure.
- Remaining Doctor queue: one non-blocking P2 source-traceability review and two non-blocking P3 content-depth/Architecture visual-backlog items. The Doctor marks them editorial/planning work with `release_blocking=false` and recommends post-release handling.
- Doctor storage flow: PASS. Available at start 3,588,796,416 bytes; required 1,837,243,096 bytes (Doctor floor plus the requested 512 MiB margin); minimum observed 3,326,885,888 bytes. Test fixture/session cleanup passed and pre-existing fixture directories were left untouched.

## Protected integrity

All hashes below were recalculated after Full Doctor and match the protected B719 baseline.

| Data | SHA-256 / result |
| --- | --- |
| Canonical questions, 9,123 | `73cdc775cd7240b57898868d67902d833db1171f947c8dcc89081ddaf46fb4be` |
| Sorted question-ID → answer-key map | `d4db069ce60c026e214ea68068b8206178782879c61b92271d4b1abe7dc7aad5` |
| Eligibility | `611f01ca29421c0c890c351c503ab262e87bc551269d78427b9a0889260933a4` |
| Source registry | `7c640636dcd3ddd0f865106ba4506a5af5dcd4a02a2074c065f695da5ef2c271` |
| Study-question links | `0e0aebad84afd09e38e477f277bb8086e52eb9b4a2e591c6de41118060a5663d` |
| Attempts | `e968fd3e8a391065ac2314ab9bcff932fc572cbe6024e16b40569b694bbed84b` |
| Weakness state | `37517e5f3dc66819f61f5a7bb8ace1921282415f10551d2defa5c3eb0985b570` |
| Academic decisions | `a2a6a868c613be6d09c3e8607599779eb87a302e90207410d99328a4dbba343d` |
| Academic source reviews | `ae9341dc97f54bc96b75a49bd06619c5bdbdf4abd1d5b9885851e1e76c9aa656` |
| Flashcard exclusions | `236975ba94ef72c223ebe7f78a4725088ca53b7c20395edb52f39e5d5e2f7717` |
| Untracked question statistics | `4f53cda18c2baa0c0354bb5f9a3ecbe5ed12ab4d8e11ba873c2f11161202b945` |
| Pre-existing flashcard state and workflow JSON | Both remained empty and byte-identical (`4f53cda18c2baa0c0354bb5f9a3ecbe5ed12ab4d8e11ba873c2f11161202b945`) |

Notes, attempts, weakness state, review records and Flashcards were not changed by synthetic tests. Quarantine and study-only behavior passed its targeted regressions. No push, merge or deployment occurred.

## Delivery and remaining work

- Implementation HEAD: `4ae65f299af48895591af677446076e5a45a552b`.
- Final documentation commit SHA is reported in the delivery response; it is intentionally not embedded in its own commit.
- Final Git status after documentation commit: no tracked modifications; only the three preserved pre-existing untracked storage files remain: `storage/academic-proposal-workflow.json`, `storage/flashcard_state.json`, and `storage/question-statistics.json`.
- Push/deploy: **NOT PERFORMED**.
- **CLIPBOARD_STATUS=UNVERIFIED.** `termux-clipboard-set` exited 0, but `termux-clipboard-get` also exited 0 and returned zero bytes. The attempted report SHA-256 was `c45d0ff4956a7b9510d02f48bb49ca74c3708b16f304dd07674c9da2e8ea89df`; an empty readback cannot match it. The complete final report is printed as the terminal fallback after this delivery status is committed.
- Remaining non-blocking engineering debt: source traceability review, evidence-thin content planning, and Architecture visual questions; these are not B720 release blockers.
- Deferred academic dependency: qualified human source/factual, answer-key, explanation, taxonomy and study decisions for the 276 pending B695 proposals. They remain noncanonical and excluded from learner selection.
- Browser limitation: actual rendered browser acceptance was not executed. Static, Node and HTTP contracts are not a substitute for it.
- No Batch 721 work was started.
