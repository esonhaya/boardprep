# BoardPrep — Batch 685 Final Report

## Execution and repository

- `START_TIME=2026-10-16T15:53:26+08:00` (protected-state baseline timestamp; prompt receipt was not separately logged)
- `END_TIME=2026-10-16T21:09:46+08:00`
- `ELAPSED_MINUTES=316` from protected-state capture; exact implementation elapsed time is unknown
- `HEAD_BEFORE=0c9a62fd3a82ad601a8e06cfc7ab028de44f584b`
- `HEAD_AFTER_APPLICATION=5f3fada7c89266a66f1107e6350f1e99fb82d84c`
- `BRANCH=main`; origin/main matched HEAD before work. The application commits were pushed by ordinary fast-forward. This report/evidence is a documentation-only descendant.
- `APPLICATION_COMMITS=49e7a60586cff647273a2fba9a3d916dc18a0a2e; 5f3fada7c89266a66f1107e6350f1e99fb82d84c`
- No reset, force push, new worktree, or learner-data commit was made. Existing branches, stashes, recovery refs, and the HayaDoctor pin `dc4fcda730d9a922f726593746e34ca0c9c0e890` were preserved.

## Browser acceptance recovery

Batch 684 run [38866456485](https://github.com/esonhaya/boardprep/actions/runs/38866456485) ran on `79833730889e97077898db787b4fcbde22647188` and had 19 passed, 3 failed, 2 skipped, and 0 console errors. The failed checks expected the obsolete study-library H1 “Source-backed study materials”; the application correctly rendered “Study resources.” This was a test-expectation defect, not an application regression. The heading expectations were corrected without changing application behavior.

The first rerun, [38903271903](https://github.com/esonhaya/boardprep/actions/runs/38903271903), tested `49e7a60586cff647273a2fba9a3d916dc18a0a2e`. It passed 20 tests and skipped 2, but desktop and mobile visual checks timed out on another stale expectation: the test looked for the accessible name “Search titles and study notes,” while the live search control is labeled “Search lesson titles, notes, and concepts.” The application control was correct. The visual test now checks that exact accessible label.

The final run [38904638327](https://github.com/esonhaya/boardprep/actions/runs/38904638327) passed on exact application commit `5f3fada7c89266a66f1107e6350f1e99fb82d84c`: **22 passed, 0 failed, 2 intentional project skips**, 3.8 minutes, and 0 console errors. It exercised desktop `1366×768` and mobile `390×844`, including board journeys, quiz setup and submission, timed exam resume/review, result explanations, history/progress, flashcards, recovery, privacy export/deletion/isolation, study-library search/empty state/detail, keyboard focus, and responsive checks.

The run generated 87 screenshots and 24 diagnostic records. Artifact `12048440096` (“boardprep-chromium-visual-qa”, 94,627,302 bytes, SHA-256 `360cad27f718c7acfb691dea437df8c2c9baaebe4363d02bf937abc3bf989b43`) is available from the run. It was not downloaded or inspected locally because local free space was near the 1.5 GiB floor. Automated screenshot assertions passed; manual visual review, screen-reader acceptance, and WCAG conformance were not established. Evidence is registered in `docs/release-evidence/browser-workflows.json` and `docs/release-evidence/batch-685-browser-ci.json`.

## Study lessons and PDFs

Seven original, source-checked lessons and searchable two-page PDFs were added using the existing `StudyPdfGenerator`. The lessons distinguish official examination scope from instructional references, include objectives, explanations, worked examples, misconceptions, practice patterns, and concept-linked questions, and do not make unsupported frequency claims. The existing three lessons remain in place. Question-to-complete-lesson and question-to-PDF coverage grew from 12 to 39 questions (+27); every lesson mapping is checked against the existing question/material relationship. This is linkage coverage, not a claim that every registered source has passed question-level semantic review.

| Board | Complete lesson | Linked questions | Generated PDF |
|---|---|---:|---|
| LET | Assessment evidence and feedback | 4 | `let-assessment-evidence-and-feedback.pdf` |
| Civil Service | Percent and ratio reasoning | 5 | `cse-percent-and-ratio-reasoning.pdf` |
| Criminologist | Scene documentation and evidence custody | 4 | `cle-scene-documentation-and-evidence-custody.pdf` |
| Nursing | Standard and transmission precautions | 4 | `nursing-standard-and-transmission-precautions.pdf` |
| Psychometrician | Reliability and score precision | 4 | `psychometrician-reliability-and-score-precision.pdf` |
| Civil Engineering | Beam equilibrium and response | 3 | `ce-beam-equilibrium-and-response.pdf` |
| CPA | Audit evidence and assertion testing | 4 | `cpa-audit-evidence-and-assertion-testing.pdf` |
| Mechanical Engineering | Ideal-gas process and cycle accounting | 4 | `me-ideal-gas-process-and-cycle-accounting.pdf` |
| Registered Electrical Engineering | DC circuit analysis | 3 | `ree-dc-circuit-analysis.pdf` |
| Architecture | Site analysis and design response | 4 | `architecture-site-analysis-and-design-response.pdf` |

The new source records cover NIJ chain-of-custody guidance, CDC safe-injection guidance, and the August 2026 Mechanical Engineering program. The existing June 2026 Architecture program source was reused and clarified; a duplicate source ID discovered by the integrity test was removed. Structural source IDs are now unique. Scope percentages are identified as cycle-specific area weights, not topic-frequency evidence.

`php tools/generate-study-pdfs.php --check` passed for all 10 PDFs; each is two pages, searchable, and matches the deterministic SHA manifest. `StudyPdfPipelineTest` and `StudyLibraryNavigationTest` passed, including the lesson and download-link contract. The actual browser PDF viewer/download gesture was not separately exercised in this run.

## Question quality and academic review

Two evidence-backed content corrections were made, both with unchanged answer keys and trace entries in `storage/academic-review-decisions.json`:

- **Question 616:** expanded the correct `18% × 250 = 45` rationale to explain percent conversion, base selection, and an independent `10% + 8%` check.
- **Question 937:** set missing cognitive intent to `understand`, matching its “why” prompt, keyed answer, and infection-prevention explanation.

Both decisions remain `REVIEW_REQUIRED` / `APPLIED_PENDING_HUMAN_SIGNOFF`; no human academic approval is claimed. The bounded audit found no independently supported wrong answer key to change, and no duplicate was merged based on similarity alone.

The full QA scan reported 8,962 canonical questions and 3,083 findings: 85 exact-duplicate errors; 224 warnings (139 weak rationales and 85 normalized-duplicate findings); 2,773 review findings (2,402 missing cognitive-intent records, 179 near-duplicate risks, 179 number-swap clone risks, 10 stem-template overuse signals, and 3 low-archetype-diversity signals); and 1 informational overexposure signal. The academic/editorial queue is 2,879, down from the historical 2,880. Valid metadata increased 6,559 → 6,560; missing cognitive intent decreased 2,403 → 2,402; weak-rationale findings decreased 140 → 139.

No duplicate findings were resolved this batch: exact duplicates remain 85, normalized duplicates 85, and near-duplicate/number-swap risks 179 each. These remain review signals. The QA CLI exits 1 while the corpus contains ERROR findings; that is its fail-on-errors contract, not a passing result. Registered URLs are not counted as semantically verified question sources: verified question-level source support remains `0 / UNKNOWN`. Supported numerical recomputation remains 232/708 (32.77%), with 476 unsupported cases, 4 unreadable answers, and 57 unresolved-unit cases; no unsupported formula was inferred.

## Developer integrity tooling

Content Intelligence now accepts concept filtering, bounds HTML QA results to 25 per page (selectable up to 100), preserves full filtered totals, exposes issue IDs and taxonomy context with direct editor links, and displays correction decisions with source evidence and human-sign-off state. QA service tests cover concept filtering, pagination bounds, full-summary preservation, and the decision ledger. Board integrity statuses are tested within the board matrix while semantic `UNKNOWN` states remain visible instead of being hidden by the display assertion.

Post-repair structural integrity is `PASS_WITH_JUSTIFIED_EXCLUSIONS`: 8,962/8,962 questions eligible and linked; 8,944 derivable flashcards plus 18 justified exclusions; zero duplicate source IDs, broken assets, invalid answer keys, broken question links, missing eligibility, or invalid taxonomy references. Coverage and source fit still require editorial review where marked unknown. The HTML findings list is paginated; the shared report is recomputed per developer request rather than persisted because it includes learner usage/weakness signals and currently has no safe freshness boundary.

## Functions explored and limits

- **Real Chromium inspected by automated interactions:** landing/dashboard and navigation; ten board details and mobile board journeys; quiz setup, answer submission, explanations and result review; timed exam interruption/resume/review; history/progress; flashcard reveal/review; learner recovery; privacy export, deletion, and isolation; study-library search, empty results, material detail; mobile navigation, keyboard focus, and responsive checks.
- **HTTP/integration or unit coverage:** ten-board journeys; study-resource links and source/question provenance; PDF generation and manifest integrity; Content Intelligence route rendering; QA concept filters and pagination; academic correction traceability; numerical-audit fixtures; structural content integrity; production developer-route boundary.
- **Not established by this batch:** manual human acceptance, browser opening/downloading a PDF file, visual inspection of the remote screenshots, screen-reader/WCAG acceptance, browser-based question-editor/import/export workflows, and live hosted HTTPS/provider behavior. Those are not reported as passed.

## Validation

**PASS:** changed PHP syntax checks; changed JSON integrity including the full question bank; PDF generation `--check`; `StudyPdfPipelineTest`; `StudyLibraryNavigationTest`; `QuestionStudyReferenceTest`; `QuestionBankContentIntegrityTest`; `NumericalAnswerConsistencyAuditTest`; `TenBoardLearnerJourneyHttpTest`; `ContentQaServiceTest`; `ContentPriorityServiceTest`; `Batch685AcademicRepairTest`; `ContentIntegrityServiceTest` after the duplicate-source repair; `git diff --check`; production preflight; production smoke; and final exact-commit Chromium.

**NOT_RUN:** full Doctor release, full security/privacy Doctor suite, and local Chromium. Termux had no local Playwright/Chromium installation. The full Doctor release was skipped because free space was about 1.6 GiB while prior release evidence required about 1.76 GiB of headroom; running it risked crossing the explicit 1.5 GiB floor. Browser CI did exercise privacy export/deletion and isolation, but that does not substitute for the full security/privacy release suite.

Formal Product Readiness ran on `5f3fada7c89266a66f1107e6350f1e99fb82d84c`. Result: **`PRODUCT_READINESS=NOT_READY`**; exact-commit Chromium is recognized as `BROWSER_EXECUTED_PASS`. Remaining launch blockers are human acceptance cases MVP-01–MVP-12 (`NOT_RUN`), approved site-specific privacy/operator/retention/deletion terms, and live HTTPS/provider/session/filesystem verification. Capability `BP662-CAP-001` remains open. See `docs/release-evidence/batch-685-product-readiness.json`.

## Learner state, storage, and Git

- The protected-state manifest is `docs/batches/batch-685-protected-state.sha256` and remains local/uncommitted because it contains hashes and paths for private state.
- 164 protected manifest entries outside the four intentionally edited canonical-content files matched their starting SHA-256 values. Attempts, weakness, progress/recovery files, question statistics, flashcard state, existing stashes, recovery assets, `.commandcode`, Mechanical Engineering SVG assets, and `tools/Batch596Author.php` remain unchanged and uncommitted.
- The historical 19-question usage delta remains unchanged: `timesUsed +26`, `timesCorrect +2`, `timesIncorrect +24`. The staged/committed question-bank diff contains only the two educational corrections; learner usage fields remain unstaged.
- Storage was 1.6 GiB free at this turn’s first check (an earlier baseline capture showed about 1.7 GiB). During the broad integrity simulator run it briefly measured 1.4 GiB. Three task-created simulator fixtures (about 49 MiB each) were inventoried, checked for symlinks and non-empty learner-state copies, then removed using their exact temporary paths. Their SHA-256 inventory manifests remain in `$TMPDIR` with hashes `76735074324fae331e1d8357991b039d906cb748cf9e39a201c491be2f34b814`, `3def424a735d9c31512e3858119b0bb596453e1d889e18ca587dcf1dae96fec4`, and `36fed71444a769a96ab2d640621238607b8317eaa49492a51d326cfb8dbf880f`. Free space returned to 1.6 GiB; no further storage-heavy validation ran.
- `PUSH_STATUS=application commits pushed to origin/main by ordinary fast-forward; no force push.` Unrelated learner/user changes remain outside the commits.
- `CLIPBOARD_STATUS=UNVERIFIED`: `termux-clipboard-set` exited successfully, but `termux-clipboard-get` returned 0 bytes on both stdin and argument-based attempts. The complete report is preserved at `docs/batches/batch-685.md` and is printed below.

## Unresolved work and next safe batch

Batch 685 delivers a passing exact-commit Chromium run, seven source-checked lessons/PDFs to complete board coverage, two traceable question corrections, and more actionable paginated QA. It does not close the overall academic review backlog or product launch gates.

Recommended next batch: obtain human academic sign-off for Questions 616 and 937; review duplicate/clone findings without automatic merging; adjudicate the 2,402 missing cognitive-intent records in bounded, evidence-based groups; extend question-level semantic source review; and manually verify PDF download/viewing plus the outstanding human mobile/accessibility cases.
