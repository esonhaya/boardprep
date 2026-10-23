# BOARDPREP — QUESTION INTEGRITY REMEDIATION

**Scope:** Post-audit corrective pass on the existing Batch 707 branch. No question-bank expansion, canonical answer-key rewrite, push, merge, or deployment.

## Checkpoint

HEAD_BEFORE=6d73cd82df144afc854efa0c983cfbb4075c623a
AUDITED_HEAD=917a098c87bc12f22e4ea246c4f20d6418503300
IMPLEMENTATION_AND_TESTED_HEAD=23589931ae1b623966a6924c3b533619bf54afa5
BRANCH=batch-707-alpha-finish-line
WORKTREE=/data/data/com.termux/files/home/boardprep-batch-706
HEAD_AFTER=implementation checkpoint above; the separate report-only commit is the final branch descendant
PUSH_STATUS=NOT_PUSHED
DEPLOY_STATUS=NOT_DEPLOYED

The starting point was the verified Batch 707 documentation tip. Five small commits contain the remediation and test work. The repository remained on the existing worktree; no additional checkout was created. Available storage fell to approximately 118,564 KiB, so Full Doctor was not attempted.

## Decisions and results

| Finding | Status | Result |
|---|---|---|
| Q6403 punctuation/key contradiction | **HELD_FOR_AUTHORIZED_REVIEW — P0 remains** | The stem quotes “AB-0072.”, choice 3 preserves the period, and the rationale says to preserve punctuation. The stored key is “AB-0072” (choice 2). The canonical key was not changed. The existing civil-service-only exclusion prevents new CSE practice use while the item owner decides whether to correct the key or stem. Canonical exam eligibility was not altered. There are no historical attempts for Q6403. The regression confirms the stored scoring key remains unchanged and the item stays excluded from new CSE practice. |
| Q1883 and Q1919 missing reachable citation | **FIXED_REFERENCE; HELD_FOR_AUTHORIZED_REVIEW** | Both now resolve through the registered Act No. 2031 source at [Lawphil](https://lawphil.net/statutes/acts/act1911/act_2031_1911.html), HTTP 200 at bounded verification. The statutory text covers negotiable-instrument form and related transfer/presentment requirements. This fixes citation reachability and identity; it does not approve each question’s legal application. |
| Q1925, Q1926, Q1927 invalid direct source ID | **FIXED_REFERENCE; HELD_FOR_AUTHORIZED_REVIEW** | The invalid direct ID now resolves to Republic Act No. 11976 at [Lawphil](https://lawphil.net/statutes/repacts/ra2024/ra_11976_2024.html). The pre-existing BIR RR No. 7-2024 study-source relationship remains intact because it is a distinct document. The tax evidence propositions remain pending item-level review. |
| 14 question-referenced confirmed-404 source records | **FIXED_REFERENCE; CLAIM_REVIEW_HELD** | Replaced centrally in the source registry with identity-matched official/authoritative landing pages. The original audit retains the exact affected question IDs; the remediation JSON records old/new URL, source, version, scope, affected count, replacement check, latest status and audit pointer for each source. |
| Three remaining confirmed-404 source records | **CONFIRMED_UNRESOLVED; no current question/material references** | AASC PSA 500, DepEd MATATAG framework and PRC March 2026 LEPT program remain 404 in the persisted snapshot. Each has zero current question/material relationships. They are visible as review findings rather than broken required-question links. |
| Legacy study material pointers | **NOT_REPRODUCED_AS_BROKEN** | All 15 referenced material records exist and each question already has a current link-table material destination. No duplicate links were created. The published LET Filipino material is a board-scoped standalone resource, not a missing exact-question mapping. |
| Exact mapping review state | **PRESERVED; not approved** | The exact-link file was not changed. There are 1,049 global exact section links: 87 explicitly pending independent review, 4 marked review-required, and 958 without an explicit review status. Unknown does not mean approved; recorded independent approvals remain zero. |

### Source replacement record

The complete machine-readable table, including old and new URLs, document titles, publishers, versions, jurisdiction/currentness notes, checks and historically affected question counts, is in [batch-707-question-remediation-status.json](batch-707-question-remediation-status.json). The original audit’s per-URL question-ID arrays remain the source for exact historical affected IDs.

The 14 repaired IDs are:

- Philippine sources: philippines-negotiable-instruments-law (160 historical question references), ra-9173-philippine-nursing-act (82), republic-act-7836 (48), prc-lept-alignment-guidance-2025 (260).
- International accounting sources: ifrs-ias-21-foreign-exchange (169), iaasb-isa-230-documentation (170), iaasb-isa-240-fraud (36), iaasb-isa-265-control-deficiencies (36), iaasb-isa-315-risk-assessment (170), iaasb-isa-450-misstatements (36), iaasb-isa-500-audit-evidence (170), iaasb-isa-520-analytical-procedures (171), iaasb-isa-530-audit-sampling (170), iaasb-isa-700-audit-opinion (170).

The 890 figure is the unique-question union in the original audit, not the sum of per-source references. All 14 replacements were HTTP 200 at initial bounded replacement verification. A later bounded check had 13 HTTP 200 results and an inconclusive DNS error for IAS 21. That source is **not** classified as dead. The latest snapshot preserves its DNS_ERROR, null HTTP status and explanation. The [IAASB 2026 Handbook page](https://www.iaasb.org/publications/2026-handbook-international-quality-management-auditing-review-sustainability-and-other-assurance) is accessible metadata only; its full standards require purchase, so six replacement records are explicitly METADATA_ONLY_FULL_TEXT_PURCHASE_REQUIRED. No claim-level verification or Philippine adoption is inferred from the landing page.

## Before and after

| Measure | Before remediation | After remediation |
|---|---:|---:|
| Direct question source IDs that do not resolve | 3 | 0 |
| Confirmed-404 URL records | 17 | 3 |
| Unique questions with any confirmed-404 reference | 890 | 0 for the repaired source set |
| Current question/material links to the three remaining 404 records | — | 0 |
| Bounded URL snapshot | Prior audit: 314 distinct URLs | 75 of 319 URL source records checked; 244 not checked |
| Restricted source records / distinct URLs | 44 / 44 in the original audit snapshot | 45 / 44 in current persisted records |
| Current inconclusive URL records | — | 13 |
| Exact question-section links | 1,049 | 1,049 |
| Independently approved exact mappings | 0 | 0 |
| Q1536/Q1537 exact section links | 0 | 0 |

Reachability is not claim support. The 75/319 source snapshot is partial. The 45 restricted records include duplicate URL records; they represent 44 distinct URLs. The 13 inconclusive results include request, DNS, non-200 HTTP and redirect-limit outcomes. The dashboard shows these as restrictions or inconclusive review evidence, not confirmed dead links.

## Developer dashboard

The existing QuestionQualityService → RepositoryHealthEngine → Developer Question Repository path was extended. It consumes the persisted, explicitly triggered URL snapshot and performs no network requests during page rendering.

At the tested checkpoint the rendered dashboard reported 816 issue rows: 0 errors, 798 warnings, 18 review items. It showed:
- zero unresolved direct question source IDs;
- three confirmed 404 source records as review items, with zero affected current questions/materials;
- restricted and inconclusive references as nonfatal, separately identified findings;
- source-text access limitations separately from reachability;
- exact section links separately from material/source-only relationships;
- explicit known-pending versus unknown/unreviewed exact-link states; no missing review status is presented as approval;
- question-ID drill-down for affected records.

The dashboard test fixture distinguishes a confirmed 404, restricted URL, invalid direct source ID, valid source, absent optional exact section link, and intentionally rejected relationship.

## Data preservation

Canonical question SHA-256 before/after:
73cdc775cd7240b57898868d67902d833db1171f947c8dcc89081ddaf46fb4be

Answer-key fingerprint before/after:
593db53b0ecfb92b9417785f6e6f926ef673aeaa3469f2ee9a89c945736058ed

Question-section link table before/after:
fa9daec05391e575e5029ac717a28969414e4c6df87c540bf1319d612bd59de6

Learner attempts, weakness/progress, question statistics and flashcard exclusions retained their recorded hashes. The untracked question-statistics file was never staged. The dirty Batch 695 checkout, its review cohort, all existing refs/stashes, and HayaDoctor reference were preserved. No question ID, answer key, board eligibility, learner record, PDF, or study-section mapping was changed. Sources registry, dashboard code, source-check snapshot, CSE-only Q6403 override and focused tests are the intentional implementation-owned changes.

## Validation evidence

All commands below were executed; exit status is recorded literally.

| Command | Exit | Tested SHA | Result |
|---|---:|---|---|
| php -d memory_limit=1G tests/batch707-question-remediation-test.php && php -d memory_limit=1G tools/Tests/QuestionQualityDashboardRemediationIntegrationTest.php && php -d memory_limit=1G tools/Tests/QuestionQualityDashboardContractTest.php && php -d memory_limit=1G tools/Tests/SourceStudyLibraryTest.php && php -d memory_limit=1G tools/Tests/QuestionStudyReferenceTest.php && php -d memory_limit=1G tools/Tests/QuestionPoolFilterTest.php | 0 | 23589931ae1b623966a6924c3b533619bf54afa5 | Newly tested pass: remediation disposition; source IDs; URL snapshot and dashboard classifications; mapping/material integrity; study references; question pool behavior. |
| Bounded source checker for the 14 repaired source IDs (full argument list in status JSON) | 2 | 4cbabd0207e1a07b08d69a242bf146a0f96941f3 | Inconclusive external-network result: 13 HTTP 200, IAS 21 DNS_ERROR. Not a dead-link finding. The result was persisted and not retried. |
| PHP syntax checks for changed PHP/service/view/test files; Python AST check for URL checker; JSON parse checks for sources, snapshot and overrides; git diff --check | 0 | 23589931ae1b623966a6924c3b533619bf54afa5 | Passed. |
| Full Doctor | NOT RUN | — | Blocked by local free space; no preflight bypass. |
| Browser acceptance | NOT RUN | — | No supported Android/Termux browser runtime. |
| PDF generation/hash checks | NOT RUN | — | No lesson, exact-section link, PDF or manifest changes. |

Additional mapped study/reference coverage and current dashboard totals are asserted by the focused tests; no full Doctor or browser acceptance is claimed.

## Final reconciliation

HEAD_BEFORE=6d73cd82df144afc854efa0c983cfbb4075c623a
HEAD_AFTER_IMPLEMENTATION=23589931ae1b623966a6924c3b533619bf54afa5
Q6403_STATUS=HELD_FOR_AUTHORIZED_ITEM_OWNER_DECISION; excluded from new CSE practice
Q6403_KEY_CHANGED=NO
Q6403_REVIEW_REQUIRED=YES
Q1883_SOURCE_STATUS=Act No. 2031 registered, reachable, full text checked; item-level legal interpretation held
Q1919_SOURCE_STATUS=Act No. 2031 registered, reachable, full text checked; item-level legal interpretation held
INVALID_SOURCE_IDS_BEFORE_AFTER=3 → 0
CONFIRMED_404_URLS_BEFORE_AFTER=17 → 3
QUESTION_AFFECTED_URLS_BEFORE_AFTER=890 unique with ≥1 confirmed-404 reference → 0 current references to a confirmed-404 URL
RESTRICTED_URLS=45 source records / 44 distinct URLs
LEGACY_POINTERS_RECONCILED=15/15; no duplicate links created
ORPHAN_MATERIAL_STATUS=Published, LET-scoped standalone; no defect reproduced
DEVELOPER_DASHBOARD_STATUS=Updated and focused fixture-tested
EXACT_LINK_REVIEW_STATUS=1,049 retained; 958 unknown/unreviewed; 0 independently approved
ACADEMIC_APPROVALS=0
P0_REMAINING=Q6403 owner/item decision
P1_REMAINING=No current required question/material link points to a confirmed 404; claim-level source reviews remain
P2_REMAINING=Three unreferenced 404s, restricted references, 13 inconclusive URL records and unreviewed claims
CANONICAL_HASH_BEFORE_AFTER=unchanged
LEARNER_STATE_PRESERVED=verified unchanged
PUSH_STATUS=NOT_PUSHED

No source-reachability check can independently approve a legal, accounting, clinical, or pedagogical claim. Public release remains not approved. The smallest next action is an authorized item-owner disposition for Q6403, followed by independent CPA/legal review of the source-linked items.
