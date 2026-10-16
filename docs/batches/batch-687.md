# BoardPrep Batch 687 — storage recovery, source remediation, duplicate safety, and release gates

## Report fields

```text
HEAD_BEFORE=113a7b826865e96bd788e95d8e02e334de5c5a20
HEAD_AFTER_APPLICATION=e314a99956b846950a5cb97d56f1b9f49f3999ef
HEAD_AFTER_DOCUMENTATION=Final report-correction commit after cf7bf3e; its SHA is reported in the batch completion message because a commit cannot embed its own hash.
START_TIME=2026-10-16 16:01:33 UTC
END_TIME=2026-10-16 16:38:26 UTC (final storage snapshot; report/clipboard finalization follows)
ELAPSED_MINUTES=37 (rounded; report/clipboard finalization follows)
STORAGE_FREE_BEFORE=1,423,576 KiB (df -Pk; 1.36 GiB)
STORAGE_FREE_AFTER=633,368 KiB at 2026-10-16 16:38:26 UTC (df -Pk; 0.60 GiB)
STORAGE_RECOVERY_ACTIONS=Inventory only; no deletion or cache cleanup was proven safe.
STORAGE_FLOOR_STATUS=FAIL; 1.5 GiB floor is 1,572,864 KiB, so this measurement is 939,496 KiB below it.
CANONICAL_QUESTIONS=8,962 before and after
SOURCE_PREVIOUSLY_REVIEWED_RECHECKED=38
SOURCE_NEWLY_REVIEWED=0
SOURCE_DIRECT_PARTIAL_UNKNOWN=39 / 33 / 28; CONTRADICTED=0
HUMAN_CONFIRMED_SOURCE_COUNT=0
CHANGED_QUESTION_IDS=No canonical question stem, option, key, rationale, taxonomy, or eligibility edits. Source-review classifications changed for 955, 1338, 3958, 4011, 4617, 4622, 4623, 4625, 5013, 5017.
COGNITIVE_INTENT_BEFORE_AFTER=2,377 missing -> 2,377 missing
WEAK_RATIONALES_BEFORE_AFTER=129 -> 129
DUPLICATE_FINDINGS_BEFORE_AFTER=Last measured 85 exact / 85 normalized / 179 clone signals; no new full-corpus scan (NOT_RUN). Canonical question content was not changed.
DUPLICATE_EXPOSURE_TEST=PASS; deterministic practice/exam selection regressions cover duplicate-heavy pools, short pools, shared questions, and distinct-content fallback.
NUMERICAL_AUDIT_BEFORE_AFTER=245/714 supported recomputations -> 245/714; prior 19 precision findings -> 0 answer-metadata mismatches and 19 explicit rounded-display matches; unreadable 4 -> 3; unresolved units 60 -> 49; unsupported 469 -> 469.
DOCTOR_STATUS=FAIL on hosted run 38931866048 at e314a99956b846950a5cb97d56f1b9f49f3999ef; gate annotation reports 4 P0 across four domains. Detailed P0 records inaccessible.
SECURITY_PRIVACY_STATUS=PASS; 6/6 separately reported jobs passed on hosted run 38931866048.
TEN_BOARD_HTTP_STATUS=PASS on hosted run 38931866048.
PRODUCTION_PREFLIGHT_STATUS=PASS (combined preflight/smoke job) on hosted run 38931866048.
BROWSER_RUN_ID=38931866113
BROWSER_EXACT_COMMIT=e314a99956b846950a5cb97d56f1b9f49f3999ef
BROWSER_PASS_FAIL_SKIP=PASS; 4m 50s; per-scenario counts and screenshots not inspected; remote artifact not downloaded.
PRODUCT_READINESS=NOT_READY; formal job failed with 2 incomplete features and 4 mandatory evidence items not passed; privacy terms absent.
HUMAN_SIGNOFF_STATUS=HUMAN_SIGNOFF_PENDING; 37 educational proposals and 85 duplicate clusters remain unapproved.
PROTECTED_STATE_STATUS=PASS; 59/59 protected SHA-256 entries verified; learner usage delta remains the same 19 rows.
UNRELATED_WORK_PRESERVED=PASS; dirty learner statistics, flashcard state, user assets, .commandcode, Batch 596 authoring tool, stashes, and HayaDoctor pin left untouched.
PUSH_STATUS=PASS by ordinary fast-forward; application commits plus evidence commit cf7bf3e were pushed. Final report correction commit is to follow through ordinary fast-forward.
CLIPBOARD_STATUS=To be verified against the final report bytes; outcome will be recorded in this file and in the completion message.
UNRESOLVED_BLOCKERS=Storage below the mandatory floor; Doctor P0 records not retrievable; independent academic sign-off pending; owner privacy/production evidence pending; full-corpus duplicate scan not rerun.
NEXT_SAFE_BATCH=Restore at least 1.5 GiB free using only positively identified disposable artifacts; obtain independent academic review; resolve Doctor P0 records from authenticated CI evidence; then rerun the release gates on the resulting application SHA.
```

## Storage recovery and data protection

The filesystem was already below its 1.5 GiB minimum at the start of this batch. The recorded free-space snapshot fell from `1,423,576 KiB` to `633,368 KiB` at 16:38:26 UTC, a decrease of `790,208 KiB` (~772 MiB). In the final minute, measured free space fell by about 290 MiB while no local tests, artifact downloads, cleanup, cache operations, or other storage-heavy batch command ran. A process listing showed no local PHP, Node test runner, Chromium, Doctor, npm, Composer, or Git maintenance process; only the Codex session host was present among the filtered processes. The cause is unresolved and is not attributed to a specific process.

| Inventory item | Measured size | Owner | Safety decision |
|---|---:|---|---|
| `/data/data/com.termux/files/usr/tmp/boardprep-batch684-explore-20261016125630` | 93,327 KiB | `u0_a664` (UID 10664) | Preserved; contains learner attempts, weaknesses, statistics, flashcard and recovery snapshots. |
| `/data/data/com.termux/files/usr/tmp/boardprep-batch684-web-20261016-ky3tc6p2` | 93,335 KiB | `u0_a664` (UID 10664) | Preserved; contains the same class of learner/recovery evidence. |
| `/data/data/com.termux/files/home/.npm` | 43,189 KiB | `u0_a664` (UID 10664) | Preserved; user-wide cache without proof that all entries are disposable or task-owned. |
| `/data/data/com.termux/files/home/.composer/cache` | 48,374 KiB | `u0_a664` (UID 10664) | Preserved for the same ownership/provenance reason. |
| `.git/objects/pack` | 25,344 KiB | `u0_a664` (UID 10664) | Preserved; historical repository objects; garbage-object count was zero. No GC/repack. |
| `docs/batches` at inventory time | 1,448 KiB | `u0_a664` | Historical batch reports/evidence preserved. |
| `docs/release-evidence` at inventory time | 4,324 KiB | `u0_a664` | Historical release evidence preserved. |
| GitHub browser artifact `boardprep-chromium-visual-qa` | 124 MB, remote | GitHub Actions | Not downloaded; no local copy exists. |
| GitHub release-gate artifacts | 10 remote artifacts | GitHub Actions | Not downloaded; raw Doctor evidence was unavailable through public access. |

No file was deleted. The two ~93 MiB temporary directories are not safe cleanup targets because they include unique learner/recovery evidence. The caches are user-wide and not proven task-owned. Existing reports and repository packs are historical state. No candidate was positively proven disposable, so the 2 GiB recovery target was not reached and the mandatory floor remains violated. Further storage-heavy local validation stopped; hosted CI was used.

Protected manifest: `/data/data/com.termux/files/usr/tmp/boardprep-batch687-protected.sha256`. It covers 59 protected files and passed `sha256sum -c` (`59/59`). `storage/questions.json` remained at SHA-256 `26abd43caaefa06f5536ae93b3cc09ed9f22b0fc76803cfc7afc63b4e005d237`. The historical usage-only delta is exactly 19 records: `46, 126, 226, 287, 361, 362, 364, 366, 367, 368, 372, 373, 375, 376, 377, 378, 379, 380, 501`; no content, answer key, attempts, or learner history was changed. The HayaDoctor submodule remains pinned to `dc4fcda730d9a922f726593746e34ca0c9c0e890`; both stashes and existing recovery references remain present.

Unrelated work left untouched includes the dirty `storage/questions.json` usage delta; untracked `.commandcode/`, `docs/batches/batch-685-protected-state.sha256`, `public/assets/me/{fluid-elbow,gear-train,shaft-guard}.svg`, `storage/flashcard_state.json`, `storage/question-statistics.json`, and `tools/Batch596Author.php`. None was staged or included in the application commits.

## Source review remediation

The existing source ledger covered 100 distinct questions, initially 10 per supported board. Thirty-eight previously reviewed records were reopened; no new question was added to the sample. The review inspected the cited source material against stem, choices, key, and explanation, and recorded exact locators, authority/date/jurisdiction limits, classification transitions, and AI-review status. The method does not treat a resolving URL or topic-level similarity as proof.

| Classification | Before | After |
|---|---:|---:|
| Direct support (AI-assisted) | 34 | 39 |
| Partial | 32 | 33 |
| Contradicted | 0 | 0 |
| Unknown | 34 | 28 |
| Human-confirmed | 0 | 0 |

Question-level classification transitions are stored in [the source remediation ledger](batch-687-source-remediation.json). Six moved to direct support: `4011, 5013, 4617, 4622, 4623, 4625`. Three moved from unknown to partial: `955, 5017, 3958`. Q1338 moved from direct support to partial after its registered AASC PDF returned 404 and the publisher overview failed to establish the specific paragraph-level claim. All other rechecked items retain partial/unknown status. Every row remains pending human confirmation; `DIRECT_SUPPORT` here means an AI-assisted editorial assessment, never human verification.

The recheck concentrated on CPA, Architecture, Psychometrician, and Nursing gaps, with one additional AASC source recheck. New references include CDC isolation guidance for transport/precautions and OpenStax statistics sections. CDC content is U.S. guidance (the hosted isolation guideline reproduces the 2007 guideline); organism-specific/current Philippine policy remains unverified. The AASC PSA 500 PDF remains unavailable and current adoption/version is unverified. Related source/material references were corrected where the reviewed evidence justified it. The ledger records the exact source IDs, locators, question snapshots, limitations, and before/after state. No new lesson was needed to resolve these demonstrated source-review gaps.

Coverage remains a selected 100-question sample; 8,862 canonical questions have no semantic source review in this ledger. The 39 direct-support rows do not constitute human-confirmed coverage, whole-corpus verification, or a claim that registered URL coverage equals academic support.

## Duplicate safety and triage

The latest full-corpus scan remains 85 exact duplicate errors, 85 normalized duplicate warnings, and 179 heuristic clone signals. A Batch 687 full scan was **NOT_RUN** because free space was below the required floor; those are carried-forward counts, not a new post-change audit. Question records and the corpus duplicate findings were not merged, retired, or deleted.

Selection now avoids choosing equivalent stem-and-choice content twice within one practice or exam selection when enough distinct eligible content exists. Selection still respects the requested board/domain/subject/topic/concept constraints; it excludes a repeated identity as before. If the eligible pool is too short, repeated equivalent content can be used as a transparent fallback and the learner sees a status notice. Canonical IDs, attempts, history, statistics, board mappings, study links, and flashcard derivability are unaffected.

`BlueprintDuplicateExposureTest.php` passed with deterministic cases for duplicate-heavy pools, a short pool, cross-board/shared records, and practice/exam requests. It uses the corpus exact pair `6973/7517` and distinct-content control `1137`; with distinct eligible content available the pair is not co-selected. When only clones are available, the fallback is explicitly represented rather than silently substituting unrelated questions. This is a selection safeguard, not an academic adjudication of the 85 corpus findings.

The [duplicate review packet JSON](batch-687-duplicate-review-packet.json) and [review instructions](batch-687-duplicate-review-packet.md) contain all 85 stable cluster candidates and a 15-cluster focused queue. Candidate labels remain pending reviewer confirmation; no cluster was marked human-adjudicated.

## Numerical and academic review

The bounded numerical audit used existing explicit values and supplied formulas. It did not invent missing formulas or alter any question content, answer key, rationale, or units.

| Metric | Previous | Current |
|---|---:|---:|
| Supported structured recomputations | 245/714 | 245/714 |
| Previously flagged precision comparisons | 19 | 0 answer-metadata mismatches; 19 reproduced rounded-display matches |
| Unreadable answers | 4 | 3 |
| Unit unresolved | 60 | 49 |
| Unsupported by a reliable structured formula | 469 | 469 |

The precision result reflects full-precision arithmetic compared to explicitly rounded displayed answers, not 19 corrected answer keys. The audit now recognizes `PHP` currency and common unit aliases (`percent`, `percentage`, `%`, `kg`, `kilogram(s)`). Q8890 is now parseable as a monetary value but its recomputation remains conditional on the question's stated five year-end payments and supplied factor 3.79. No new supported recomputation was claimed. The [numerical ledger](batch-687-numerical-review.json) contains all 19 reproduced calculations and their limitations.

No cognitive-intent or rationale edits were made. The missing-intent queue remains 2,377, including the 25 Batch 686 labels that await human review. Weak rationales remain 129. No duplicate was judged safe to merge/delete.

## Human sign-off handoff

The consolidated [human academic review queue](batch-687-human-review-queue.md) and JSON package contain 37 distinct pending educational proposals: Questions 616 and 937 plus the 35 Batch 686 changes. Entries include choices, current key/explanation, proposed change, before/after, source evidence, ambiguity, reviewer expertise, validation status, and blank reviewer/decision fields. Existing decision ledger entries were retained without duplicate approvals.

The duplicate packet separately provides the 85 cluster candidates, with 15 focused items to help reviewers start with higher-impact cases. Independent reviewers can record `APPROVE`, `REQUEST_CHANGES`, or `REJECT`, their actual identity/credentials and date, notes, and evidence. No reviewer identity, qualification, signature, date, or approval was fabricated. `HUMAN_SIGNOFF_PENDING` remains in force.

## Release gates and exact-commit evidence

Reusable hosted workflows were added rather than running storage-heavy suites locally. Each gate is a separate job on GitHub's fresh runner checkout. Test commands and assertions were retained. The workflow creates short-lived evidence artifacts, records commit/run IDs, uses read-only repository permissions, and does not upload the local Termux workspace or its untracked learner files. Gate details are in [release evidence JSON](../release-evidence/batch-687-release-gates.json).

| Gate | Result | Evidence |
|---|---|---|
| Full Doctor release | **FAIL** | [run 38931866048](https://github.com/esonhaya/boardprep/actions/runs/38931866048), exact SHA `e314a99956b846950a5cb97d56f1b9f49f3999ef`. Annotation: `gate=FAIL`, `NOT_RELEASE_READY`, `p0=4`, `health=WARN`, `health_fail=0`; failed domains are Study Experience, Developer Health, Study Flashcard Experience, and Production Configuration. The individual P0 records are not exposed in public annotations; artifact/log requests returned 401/403. Their cause is therefore unresolved. |
| Security/privacy | **PASS, 6/6 jobs** | Production quiz CSRF, question import CSRF, question import safety, anonymous learner privacy, session configuration, and security trust boundary all passed on the same SHA. |
| Ten-board HTTP journey | **PASS** | Run 38931866048, job completed successfully. |
| Production preflight/smoke | **PASS** | Run 38931866048 combined job completed successfully; raw per-command statuses were not retrievable. |
| Formal Product Readiness | **FAIL / NOT_READY** | `product_feature_complete=no`, `product_launch_ready=no`, `privacy_terms=no`, `incomplete_features=2`, `mandatory_evidence_not_passed=4`. |
| Exact-commit Chromium | **PASS** | [run 38931866113](https://github.com/esonhaya/boardprep/actions/runs/38931866113), exact SHA `e314a99956b846950a5cb97d56f1b9f49f3999ef`; 4m 50s. |

The browser used the repository's desktop and mobile viewports. Its remote `boardprep-chromium-visual-qa` artifact is 124 MB and was not downloaded due to the Termux storage constraint. Individual assertion counts, screenshots, traces, console output, and failed-request lists were not accessible from the public page. The [browser inventory](batch-687-browser-review-inventory.md) records this. Automated browser completion is not manual visual, keyboard, screen-reader, or human acceptance.

During an earlier run, the `question-import-csrf` test incorrectly ran with `APP_ENV=production`, so the developer-only import route was absent. The test setup now explicitly sets `APP_ENV=testing` and `APP_DEBUG=false`; it retains the CSRF assertion. On the final commit that test passed along with the other five security/privacy jobs. This was a test-environment defect, not a weakened assertion.

The exact-commit Doctor did not pass. Its release annotation reports four P0 findings. Since the accessible public run page does not identify those records, this batch cannot assert whether all are unrelated to the code changes or infer their precise remedies. Authenticated artifact access is required to resolve that gap.

## Local validation and limitations

The following bounded tests passed before storage fell further below the minimum: `BlueprintDuplicateExposureTest.php`, `NumericalAnswerConsistencyAuditTest.php`, `Batch687SourceReviewTest.php`, `Batch686ReviewLedgersTest.php`, and `ReleaseReadinessContractTest.php`. Modified PHP files passed syntax checks; changed JSON ledgers parsed; canonical count was 8,962; and `git diff --check` passed. These targeted checks are not a substitute for a full-corpus scan.

Local full Doctor, security/privacy, ten-board HTTP, production preflight, and browser suites were not run because available disk space was below 1.5 GiB. Hosted results above are against the exact final application SHA. QA full-corpus rescan was not run; its fail-on-error contract was not modified. Formal Product Readiness remains NOT_READY until owner-controlled privacy/production evidence and all required human acceptance are independently recorded.

## Git, state, and next action

Application commits, all fast-forward pushed to `origin/main`:

1. `3f2e97efff9191fbaee62f6e620092a03732ec3f` — source review, numerical audit, duplicate selection guard, focused tests, and reusable release-gate workflow.
2. `1e29fa47d2c1708a2b0764351dd457af94983638` — individually reported security/privacy jobs.
3. `7e459db5ad4b99131f0781f2ecbf701624698996` — sanitized gate failure annotations.
4. `e314a99956b846950a5cb97d56f1b9f49f3999ef` — correct testing environment for the developer import CSRF regression.

At the last application check, `HEAD` and `origin/main` both equalled `e314a99956b846950a5cb97d56f1b9f49f3999ef`. The evidence packet was pushed as documentation commit `cf7bf3e827aa302f50197c1d3e1f2543ecdd4d3b`. A narrowly scoped report-correction commit follows it; its SHA will be given in the completion message because a report cannot contain the hash of the commit that contains it. Learner state and unrelated untracked files remain excluded.

The next safe action is to stop local intensive validation until free space is restored above 1.5 GiB through an independently verified disposable artifact, then obtain authenticated access to the Doctor evidence and independent academic sign-off. Rerun only the gates affected by any subsequent changes, against their exact final SHA. Keep Product Readiness `NOT_READY` while owner, privacy, production-host, accessibility, and academic approvals remain incomplete.
