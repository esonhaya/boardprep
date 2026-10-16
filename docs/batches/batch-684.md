# BoardPrep Batch 684 — Product audit, source-first resources, and educational integrity

## Batch record

```text
START_TIME=2026-10-16 12:50:02 Asia/Manila (protected-state capture)
END_TIME=2026-10-16 15:53:06 Asia/Manila
ELAPSED_MINUTES=183.1
HEAD_BEFORE=19cce18250f3eba2839174898481a5c33640211c
HEAD_AFTER=79833730889e97077898db787b4fcbde22647188 (final application commit; report/evidence commit is documentation-only)
HEAD_AFTER_APPLICATION=79833730889e97077898db787b4fcbde22647188
ORIGIN_MAIN_BEFORE=19cce18250f3eba2839174898481a5c33640211c
ORIGIN_MAIN_AFTER_APPLICATION=79833730889e97077898db787b4fcbde22647188
ORIGIN_MAIN_BEFORE_AFTER=19cce18250f3eba2839174898481a5c33640211c -> 79833730889e97077898db787b4fcbde22647188; report/evidence pushed as documentation-only descendant
BATCH684_STATUS=IMPLEMENTED_AND_VALIDATED_WITH_OPEN_PRODUCT_BLOCKERS
COMMITS=
  b01539d6bd9cb1ecdc2b5e740035670eea7494c3 Batch 684: build source-first lessons and integrity readiness
  79833730889e97077898db787b4fcbde22647188 Batch 684: report board readiness accurately
PUSH_STATUS=Both application commits pushed to main by ordinary fast-forward
GIT_INTEGRATION=No rebase, force push, reset, clone, or new worktree; main was not diverged
HAYADOCTOR_PIN=dc4fcda730d9a922f726593746e34ca0c9c0e890 (unchanged)
CLIPBOARD_STATUS=UNVERIFIED (set exited 0; readback returned zero bytes)
```

Clipboard verification: `termux-clipboard-set` returned exit 0, but `termux-clipboard-get` returned zero bytes after stdin and argument-based attempts. The full report is preserved here and `CLIPBOARD_STATUS=UNVERIFIED`.

The report and its evidence are committed separately after the application commits. `HEAD_AFTER_APPLICATION` is the exact commit covered by the final local Doctor run and browser workflow. The report-only commit does not change application code.

## Outcome

Batch 684 added a reusable source-to-lesson-to-PDF pipeline, three original exam-focused lessons, an educational-integrity readiness view, a bounded numerical audit, question-editor pagination, and targeted content fixes. The product now exposes coverage gaps and review actions more clearly, but broad teaching coverage remains incomplete: only 12 of 8,962 canonical questions link to the new complete lessons and downloadable PDFs.

The final full Doctor release passed its release gate with non-blocking debt. The exact-commit Chromium acceptance workflow failed twice, including on the final application commit. The formal product-readiness command reports `product_launch_ready=false`; retain `NOT_READY`.

## Recovery, repository, and learner-state safety

- The starting branch was `main`; `HEAD`, `origin/main`, and the protected-state capture were all `19cce18250f3eba2839174898481a5c33640211c`. The repository had one worktree and two existing stashes.
- Both stash commits and trees still match the captured values. Existing branches and recovery refs were preserved. No Batch 683 work was recovered or represented as having existed.
- The protected manifest at [batch-684-protected-state.sha256](../batches/batch-684-protected-state.sha256) captured 167 paths. At final comparison, 161 hashes matched and none were missing. The six changed paths are application/content files: `storage/questions.json`, `storage/sources.json`, `storage/study-materials.json`, and the three synchronized topic metadata files.
- All 36 protected learner/recovery/user-file paths matched their captured hashes, including attempts, weakness, statistics, flashcard state, recovery files, `.commandcode` records, untracked learner SVGs, and `tools/Batch596Author.php`.
- `storage/questions.json` has an intentional question-content change for four cognitive-intent labels and a byte-level file-hash change. Its current working-tree diff against the final application commit contains only the pre-existing usage fields for 19 questions; a semantic comparison found no other worktree question changes. The captured baseline recorded the same 19 rows and aggregate delta: `timesUsed +26`, `timesCorrect +2`, `timesIncorrect +24`. IDs: `46, 126, 226, 287, 361, 362, 364, 366, 367, 368, 372, 373, 375, 376, 377, 378, 379, 380, 501`. This learner-usage delta remains uncommitted.
- The baseline manifest recorded usage-projection SHA-256 `3c0e39fb362e1b9c5e38042b9690909b5c77153fd5ba05c26c63b43c1ffc99cf`; the final raw question-file SHA-256 is `6b771b06fc34be83c2821a4601e2565e8f1a55a00753bba686c14e5fd8f97f47`. Because the full file also contains committed content labels and formatting changes, the whole-file hashes are not equivalent; usage values and aggregate counters were checked semantically.
- HayaDoctor release fixtures and per-test sessions were isolated and cleaned by the runner. Its minimum observed free storage was `1,886,760,960` bytes (about 1.76 GiB), above the enforced 1.5 GiB floor (`1,610,612,736` bytes). Free storage was `2,510,788 KiB` at baseline capture and `2,084,156 KiB` at the final check. Pre-existing temporary fixture/session directories and `/usr/tmp/boardprep-import-3134` were left untouched. No broad temporary-file cleanup was performed.

## Application exploration

The isolated local development workflow served 44 learner and developer routes by GET; all returned HTTP 200. The route evidence is in [batch-684-http-audit.json](../release-evidence/batch-684-http-audit.json). This was a route and rendering sweep, not a claim that every control was manually operated in a browser.

Inspected learner surfaces included landing and board selection, dashboard, quiz settings and practice, history/results, study discovery and board filtering, direct material detail, PDF response, profile/progress, flashcards, missed/review cards, and privacy export. Inspected developer/admin surfaces included board and subject pages, question create/edit, imports, content QA/intelligence, study library, taxonomy, metadata repair, coverage/blueprints, Doctor, and export.

Functional coverage was supplied by targeted regressions and the full Doctor suite: ten-board learner journeys; exam answer, result/history, timer/interruption/recovery and state-isolation flows; adaptive recommendations and progress; flashcard reveal/rating; privacy/export and CSRF boundaries; imports; question authoring; study navigation and links; and production package integrity. Empty/error handling is covered where named by those tests, but was not exhaustively explored visually.

### Confirmed functional and diagnostic defects repaired

1. A direct study-material detail page reused a short library preview and hid the full lesson/source notes. It now renders the complete lesson and all 11 notes for the inspected CSE material; the page exposes its sources, PDF, and practice action.
2. The question editor rendered all 8,962 questions in an approximately 8 MB response. It now renders 50 questions per page, keeps filters, and supplies adjacent-page links. HTTP evidence fell to 116,457 bytes on page 1 and 117,730 on page 2; the regression confirms distinct IDs and bounded output.
3. Board readiness incorrectly read `status` where the integrity snapshot supplies `readiness_status`, so every board appeared `UNKNOWN`. The corrected view reports structural integrity accurately and marks all ten boards `PARTIAL` while lesson/PDF coverage is incomplete. A regression test covers the real field name.
4. Sixteen reviewed standalone published materials were incorrectly counted as orphaned. They are now visible as justified standalone exclusions; there are zero unreviewed published orphans.
5. Eighteen justified flashcard exclusions were omitted from one structural check and produced a false failure. The view now reports `PASS_WITH_JUSTIFIED_EXCLUSIONS`, 8,944 derivable cards, 18 justified exclusions, and zero unexpected derivation failures.
6. A study-library summary label failed its mobile/accessibility view contract. The accessible label was corrected and all 13 structural mobile-view assertions pass.
7. Thirty-five generic topic learning objectives were replaced with topic-specific learning prompts in the three synchronized topic catalogs.

## Source-first lessons and study PDFs

The implemented hierarchy is: authoritative examination scope and instructional references → verified-scope BoardPrep lesson → generated study PDF → linked practice questions → derivable flashcards. Official requirements are labeled separately from instructional recommendations. Lessons do not assert that a topic is frequently tested without supporting evidence.

Three complete representative lessons were authored across materially different boards and subject types:

- Civil Service: percentage and ratio reasoning.
- Registered Electrical Engineer: DC circuit analysis.
- LET professional education: assessment evidence and feedback.

Each lesson has objectives, explanations, worked examples, common misconceptions, question patterns, practice IDs, scope notes, and source references. Source records include the relevant CSC/PRC exam scope and instructional sources such as OpenStax; lessons and PDFs contain original BoardPrep instruction rather than repackaged source documents.

`StudyPdfGenerator` and `tools/generate-study-pdfs.php` generate searchable, two-page PDFs from the reviewed lesson records. `--check` verifies output and SHA manifest without rewriting files. `StudyPdfPipelineTest` and `StudyLibraryNavigationTest` pass. PDFs are downloadable at `public/study-resources/`; generated file hashes are in `public/study-resources/manifest.json`.

The breadth limit is explicit: 12 questions currently link to complete lessons and 12 to downloadable PDFs—4 LET, 5 Civil Service, and 3 REE. The other seven boards currently have zero such linked questions. All ten board structural-integrity checks pass, with the LET board carrying justified exclusions, but all ten board readiness rows correctly remain `PARTIAL` until study coverage expands. Sixteen reviewed standalone resources remain intentionally unlinked and visible.

## Question, metadata, and resource audit

The audit scanned all 8,962 canonical questions and exposes record IDs, severity, reason, and suggested action. It does not rewrite questions on heuristic evidence.

- 8,962 questions have registered source traceability, but semantic relevance is **UNKNOWN** unless a reviewed question-level fit record exists. The developer report counts zero as semantically verified; a URL alone is not proof.
- 6,559 questions have valid core metadata. 2,880 questions remain in the academic/editorial review queue, including 2,403 confirmed missing-cognitive-intent metadata gaps.
- Content QA reports 3,085 issue entries: 85 exact-duplicate findings, 85 normalized-duplicate findings, 140 rationales too short to support the key, 2,403 missing cognitive-intent findings, 179 near-duplicate risks, 179 number-swap-clone risks, 10 stem-template-overuse findings, 3 low-archetype-diversity findings, and 1 learner-overexposure signal. These include suspected/review findings; duplicates and wording were not automatically merged or rewritten.
- The readiness view classifies 2,403 confirmed metadata gaps and 390 suspected/review findings. Numerical disagreements remain review items, not verified wrong keys.
- The bounded numerical audit found 708 structured payloads: 232 recomputed (32.77%), 476 unsupported by a registered formula, 4 unreadable answers, and 57 unresolved units. Nineteen answer-versus-computation differences remain `REVIEW_REQUIRED`; zero supported calculations are classified as a hard numerical mismatch. Unsupported formulas are not counted as verified.
- No invalid taxonomy references, missing/invalid eligibility, broken source references, broken assets, invalid study links, or unreviewed orphan materials were detected in the final readiness snapshot. Sixteen reviewed standalone resources remain listed as justified exclusions.
- Four verified cognitive-intent labels were added to questions 62, 63, 65, and 152. No answer key was changed in this batch.

## Validation and evidence

- Focused tests passed: `StudyResourceReadinessTest`, `StudyPdfPipelineTest`, `ContentQaServiceTest`, `NumericalAnswerConsistencyAuditTest`, `QuestionEditorPaginationHttpTest`, `LearnerMobileAccessibilityViewTest` (13 assertions), `StudyLibraryNavigationTest`, `Batch675StaticDoctorPrecisionTest`, `ReleasePackageTest`, and `BrowserWorkflowEvidenceTest`.
- Final `php tools/doctor.php --release --json` on application commit `7983373…` exited 0. The release gate is `PASS`, with `RELEASE_READY_WITH_NONBLOCKING_DEBT`; all 26 release domains pass except `SOURCE_TRACEABILITY=REVIEW` (eight review signals; zero hard source defects). Doctor health is `WARN`: 13 health passes, 2 warning checks, 752 inventory/planning warnings, and 0 failures. The full evidence is [batch-684-doctor-release-final.json](../release-evidence/batch-684-doctor-release-final.json). A pre-commit failed run is preserved separately; its mobile-label, stale assertion-count, and not-yet-tracked package-file causes were corrected before the final full run.
- Final production preflight passed 22/22 checks with explicit `APP_ENV=production APP_DEBUG=false`; production smoke passed application boot, 10 boards, 8,962 questions, 2,705 eligibility records, and learner/progress service loading. The initial preflight under the local development environment failed only its two expected production-mode/debug checks. Final evidence: [preflight](../release-evidence/batch-684-production-preflight.json) and [smoke](../release-evidence/batch-684-production-smoke.json).
- `php tools/generate-study-pdfs.php --check`, JSON parsing for all changed JSON files, PHP syntax checks for 25 changed PHP files, and Git whitespace checks passed.
- The exact-commit browser workflow failed on both application commits. For final application commit `79833730889e97077898db787b4fcbde22647188`, run [38866456485](https://github.com/esonhaya/boardprep/actions/runs/38866456485) completed with the `Run browser acceptance` step failed; PHP/Node setup, dependency installation, Chromium installation, and artifact upload succeeded. The 93 MB artifact returns HTTP 401 without GitHub authorization; workflow logs return HTTP 403. No desktop/mobile screenshots were locally inspected. Do not report Chromium as PASS.
- Formal `php tools/product-readiness.php --json` on the final application commit reports `product_feature_complete=false` and `product_launch_ready=false`. Critical gaps include failed current-commit browser acceptance, unapproved deployment-specific privacy terms, 12/12 human acceptance cases `NOT_RUN`, real-browser mobile/accessibility evidence, and live HTTPS deployment evidence. The captured result is [batch-684-product-readiness.json](../release-evidence/batch-684-product-readiness.json).

## Remaining gaps and next safe action

Product readiness is **NOT_READY** despite the technical Doctor release gate passing. The next batch should first retrieve the exact Chromium failure artifact/logs with authorized access, reproduce the acceptance failure, and complete desktop/mobile human acceptance; then approve and configure site-specific privacy terms. In parallel, academics should review the 2,403 cognitive-intent gaps, 140 weak rationales, duplicate/near-duplicate and numerical review queues, and expand complete lessons/PDF practice coverage to the seven boards with none. Do not auto-label or rewrite solely from heuristics, and do not promote `UNKNOWN`, `REVIEW`, `PENDING`, or `NOT_RUN` to `PASS`.

```text
LEARNER_STATE_HASHES=36/36 protected learner, recovery, stash-adjacent and user-asset paths match; six content-file hash changes are listed above
FILES_LEFT_UNTOUCHED=Attempts, weakness, statistics, flashcard state, recovery files, .commandcode logs, learner SVGs, tools/Batch596Author.php, existing stashes and recovery refs
QUESTION_USAGE_DELTA=19 baseline rows preserved in worktree; +26 used, +2 correct, +24 incorrect; not committed
STORAGE_BEFORE_AFTER=2,510,788 KiB baseline; minimum observed 1,886,760,960 bytes during final Doctor; 2,084,156 KiB at final captured check; always above 1.5 GiB
TEMP_ARTIFACTS=Doctor JSON, product/preflight/smoke JSON, HTTP audit and protected manifest preserved under docs/release-evidence or /usr/tmp; pre-existing temporary data left untouched
APPLICATION_CHANGES=Two application commits pushed by ordinary fast-forward; no learner usage values included in either commit
VALIDATION_COMPLETED=Focused regressions; 44-route GET sweep; ten-board learner journey; full Doctor release; production preflight/smoke; PDF, JSON, PHP syntax and Git whitespace checks
VALIDATION_NOT_RUN=Local interactive desktop/mobile screenshot inspection; 12 human acceptance cases; live HTTPS deployment checks; semantic question-level source review beyond recorded audit
DOCTOR_RELEASE=PASS; release gate PASS; source traceability REVIEW (8 signals); health WARN with 752 planning/inventory warnings and zero failures
CHROMIUM=FAIL on exact final application commit; screenshot artifact upload succeeded but artifact/log access was unauthorized
PRODUCT_READINESS=NOT_READY
GIT_INTEGRATION=Application commits fast-forwarded origin/main; report/evidence commit follows as documentation-only
PUSH_STATUS=Application commits pushed by ordinary fast-forward; this report and evidence are pushed as a documentation-only fast-forward
UNRESOLVED_GAPS=Browser acceptance failure; no visual screenshot inspection; unapproved privacy terms; 2,880 academic/editorial review questions; only 12 question-to-lesson/PDF links
NEXT_SAFE_ACTION=Investigate the exact Chromium failure with authorized artifact access, complete human/mobile acceptance and privacy approval, then expand evidence-backed lessons and academic review
CLIPBOARD_STATUS=UNVERIFIED (set exited 0; readback returned zero bytes)
```
