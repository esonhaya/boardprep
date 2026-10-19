# Batch 696 — Source-First Study Depth

## Recovery and baseline

Batch 696 did not exist as a local branch, worktree, staged change set, or remote branch when recovery began. The current B696 worktree was created independently from verified `origin/main` at `f657b89629a0262d3ebca0ce99baaf551268acf8`. Batch 695 remains in the original checkout and on `origin/batch-695-academic-review` at `c3d6b217aed480ebda1c81c1900f907a749befe3`; its pending questions were not copied or merged. Existing Git stashes and unrelated original-checkout changes were left untouched.

The production `BoardViewService` and `QuestionEligibilityService` baseline has 10 boards, 43 active board-subject selectors, and 9,123 canonical questions. Twenty-five selectors had no lesson, 22 downloadable study PDFs were registered, and 8,121 effective board-subject question placements had no instructional lesson link. These placement counts can repeat a canonical question across eligible destinations.

See [before coverage inventory](../reports/batch-696-coverage-before.md) and its machine-readable [JSON](../reports/batch-696-coverage-before.json).

## Implemented content

- Added 25 original, partial introductory lessons for the 25 previously lesson-free active selectors: Civil Engineering (3), Civil Service (4), CPA (5), LET (6), Mechanical Engineering (3), Psychometrician (1), and REE (3).
- Each new lesson has learning objectives, three or four topic sections, an applied worked example with reasoning, common misconceptions, practice patterns, a scope note, and registered official-scope and instructional references.
- CPA taxation content avoids fixed rates and deadlines. Engineering examples state their assumptions and do not claim code compliance. The psychological-assessment lesson teaches interpretation limits and is not individual clinical advice. Filipino instruction is authored in Filipino rather than mechanically translated.
- Added 25 searchable PDF study resources generated from the lesson records. The generator and manifest provide SHA-256 checks. Existing published PDFs remain in the manifest.
- Added 24 question-to-lesson mappings. Each mapping identifies an eligible board-subject scope, exact lesson section, sources, and explicit review guidance. Mapping status remains `SECTION_AND_SOURCE_CHECKED_PENDING_INDEPENDENT_ACADEMIC_REVIEW`. One LET Filipino question was not linked because its taxonomy did not match the lesson section.
- Answer review and immediate practice feedback can return to a stable exact section anchor. The reference service checks the active board and effective subject placement before displaying a linked lesson. Flashcard study support uses that same scope-aware reference path. Legacy source-only resources remain source references rather than being presented as lessons.
- Added reproducible before/after portfolio inventories, a 43-selector readiness matrix, source evidence register, and record-level future integration plan.

## Measured outcome

The after inventory records 10 boards, 43 selectors, 9,123 canonical questions, zero selectors without a lesson, 47 PDFs, and 8,097 source-only placements. Lesson-linked placements increased from 1,786 to 1,810. The 24 added mappings each link one question in a new selector lesson; they do not close the remaining 8,097 placement gaps.

The structural text heuristic associates 357 taxonomy concepts with substantive lesson sections, compared with 69 before this batch. It still identifies 289 concepts without an instructional section association. The report contains 852 semantic-review candidates: 828 pre-existing candidates plus the 24 new mappings awaiting independent review. There are no broken lesson-section links in the after inventory.

All 43 selectors now have at least one published lesson and PDF, but this is not full subject coverage. The 25 new lessons are partial modules. Existing lesson depth was audited through structural measures but not expanded in this batch. See [coverage after](../reports/batch-696-coverage-after.md), [instructional readiness](../reports/batch-696-instructional-readiness.md), and [source evidence](../reports/batch-696-source-evidence.md).

## Learner-data and academic safeguards

No question record, answer key, canonical identity, question usage counter, learner history, quiz attempt, flashcard history, account-security state, eligibility record, source registry, or taxonomy record was edited by the content wave. All 276 Batch 695 questions (IDs 9124–9399) remain isolated on their academic-review branch and were not approved.

Current source registry status is not independent academic approval. The source evidence report distinguishes content read during this batch from registry metadata only. The PRC Accountancy page identifies the updated 2026 TOS, but its linked Annex A PDF was not directly read in this pass; the CPA modules are therefore partial and avoid claims of complete TOS alignment. No human learning-effectiveness claim is made.

## Validation executed

Passed PHP syntax checks for modified services, controllers, views, and Batch 696 tools; the production-selector baseline reconciliation; ten-board production HTTP learner journeys for board detail, study discovery, scoped practice, answer, explanation, study follow-through, history, progress, retry, stale-action recovery, and anonymous attempt persistence; source-study integrity; source readiness; PDF deterministic generation and manifest/hash/page/text checks; question-study references; legacy Batch 678 answer-review links; flashcard scope, foundation, and integration; learner navigation UX; HayaDoctor pinned-interface compatibility; anonymous privacy isolation; multi-exam eligibility; and dedicated content-integrity and section-anchor/scope-isolation tests. The new content-integrity test verifies all 25 lessons, 24 exact-section links, current effective eligibility, source resolution, PDF hashes, pending academic status, and absence of Batch 695 IDs. The deterministic PDF test was updated to require every published lesson to have a matching generated PDF, rather than a stale fixed count of 20.

The bounded academic-quality audit completed after its repeated-link scan was indexed: 0 deterministic errors, 6 deterministic subject-depth warnings, and 2,429 heuristic review candidates. Of those, 24 are the new B696 section/source links still pending independent academic review. Heuristic findings are not confirmed content defects. The portfolio coverage report separately records 8,097 source-only placements, 289 concepts without a structural instruction association, and 3 heuristic shallow-lesson candidates.

The production preflight passed with no warnings or failures. The Doctor release gate was run with `--no-tests` because a broad `ContentIntegrityServiceTest` was still using CPU after ten minutes; that test was stopped and is recorded as interrupted, not passed. The no-test Doctor result is `RELEASE_DECISION=NOT_RELEASE_READY`, `GATE=REVIEW`, with P0=0, P1=0, P2=1, P3=2. Product Readiness completed as `PRODUCT_FEATURE_COMPLETE=NO` and `PRODUCT_LAUNCH_READY=NO` because real-browser/mobile evidence, 12 human acceptance cases, owner-approved privacy terms, and deployment evidence remain open. Hosted exact-commit CI and the separate bounded site explorer were not run in this checkpoint. Their status is not inferred from focused tests.

## Current state and B697 handoff

The B696 branch is based on `f657b89629a0262d3ebca0ce99baaf551268acf8`, separate from Batch 695, and contains a validated partial content checkpoint. It is not the complete portfolio-wide instructional objective: 8,097 placements remain source-only, 289 concepts lack a structural instruction association, existing thin lessons still need subject-by-subject expansion, and 852 question/lesson semantic candidates remain pending. Batch 697 must not treat the zero-lesson metric as proof of a complete educational baseline. This execution does not start Batch 697.

Future branch reconciliation requirements are in [the integration plan](../reports/batch-696-branch-integration-plan.md). Main remains unchanged until a separately authorized integration.
