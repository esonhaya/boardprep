# BOARDPREP — FULL QUESTION INTEGRITY AUDIT

**Audited HEAD:** `917a098c87bc12f22e4ea246c4f20d6418503300`
**Branch / worktree:** `batch-707-alpha-finish-line` / `/data/data/com.termux/files/home/boardprep-batch-706`
**Generated:** `2026-10-22T23:59:41Z`

## Finding

The canonical repository contains **9,123 active questions**. Production structural validation passes all 9,123 records; production eligibility resolves all 9,123 across the ten supported boards; and the study-link validator reports no broken question, material, source, or section anchors. That does not mean every citation is usable or every keyed answer is correct.

**One confirmed scoring/key defect:** Civil Service Q6403 asks for an exact transcription of `AB-0072.`; the key is `AB-0072`, while choice 3 preserves the final period and the explanation says punctuation is preserved. The item is active. No key was changed.

**Confirmed source access failure:** 2 CPA questions, Q1883 and Q1919, have only registered source references whose URLs returned HTTP 404 in the bounded check. Across the bank, 890 questions reference at least one 404 URL, but 888 have another registered reference that returned HTTP-reachable. The 404 source links still need repair or explicit source limitation.

**Other concrete reference defects:** 3 direct question-level source IDs are unregistered (Q1925–Q1927); 15 legacy `study_material_id` pointers have no matching current link-table row; and one published material is orphaned. These do not alter canonical scoring today, but should be reviewed.

The complete issue list, IDs, source URLs, affected question IDs, board-level exact-link gaps, duplicate clusters, and compact full-bank quality flags are in [batch-707-question-integrity-issues.json](batch-707-question-integrity-issues.json). It contains identifiers and findings, not a second copy of question stems or answer choices.

## Recovery and preservation

- `HEAD=917a098c87bc12f22e4ea246c4f20d6418503300`; branch `batch-707-alpha-finish-line`; reused worktree `/data/data/com.termux/files/home/boardprep-batch-706`. No extra worktree was created.
- The preexisting untracked `storage/question-statistics.json` (empty JSON array) was preserved and not staged.
- Canonical question, source registry, question-link, attempt, weakness, question-statistics, and flashcard-exclusion hashes match the recorded audit-entry hashes. No question, answer, taxonomy, eligibility, link, learner-state, or study asset was modified.
- B695 and all refs/stashes were left untouched. No push or deployment was performed.

## Inventory and board reconciliation

`TOTAL_CANONICAL=9123` · `ACTIVE=9123` · `DRAFT=0` · `ARCHIVED=0` · `INVALID_RECORDS=0` · `DUPLICATE_IDS=0`.

Canonical primary board classification uses `taxonomy.board_id`; eligibility is separately resolved by `QuestionEligibilityService`. Board eligibility totals overlap and must not be summed as canonical identities.

| Board | Primary canonical questions | Eligible placements | Exact section links | Eligible placements without exact section |
|---|---:|---:|---:|---:|
| let | 1889 | 1987 | 337 | 1650 |
| civil-service | 1553 | 2116 | 9 | 2107 |
| criminologist | 451 | 554 | 4 | 550 |
| nursing | 878 | 878 | 47 | 831 |
| psychometrician | 461 | 461 | 301 | 160 |
| civil-engineering | 922 | 922 | 3 | 919 |
| cpa | 1132 | 1132 | 8 | 1124 |
| mechanical-engineering | 511 | 511 | 3 | 508 |
| registered-electrical-engineering | 842 | 842 | 3 | 839 |
| architecture | 484 | 484 | 334 | 150 |
| **Total** | **9123** | **9887** | **1049** | **8838** |

Production eligibility resolves **9123 unique questions** into **9887 placements**, with **676 multi-board questions** and 40 concept-derived placements. A separate `storage/questions/questions.json` contains 12 legacy-format sample records; it is not loaded by `QuestionRepository` and is excluded from the canonical count.

The **8,838** missing exact section relationships are eligible board-question placements, not confirmed defects: every canonical question has at least one valid source/material relationship, and absence of an optional exact section is not a broken link. Full missing-placement question IDs by board are in the JSON artifact.

## Source links

The registry has 319 records, 318 with URLs, and 314 unique public URLs checked at `2026-10-22T22:45:50Z`. The bounded probe used HEAD with bounded Range-GET fallback, safe public-host checks, per-host serialization, 6-second timeout, max six concurrent hosts, and at most five redirects.

| URL result | Unique URLs | Interpretation |
|---|---:|---|
| CONFIRMED_404 | 17 | Confirmed 404 after bounded fallback; check whether official material moved. |
| ACCESS_RESTRICTED | 44 | 403/access restricted; not classified dead. |
| DNS_ERROR | 1 | DNS failure at check time. |
| HTTP_OTHER | 1 | Other response; inspect final destination. |
| HTTP_REACHABLE | 242 | Reachable response; this does not prove claim support. |
| REDIRECT_LIMIT | 1 | Redirect chain exceeded probe limit. |
| REQUEST_ERROR | 8 | TLS/request error; reachability unresolved. |

**14 question-referenced source records** have confirmed 404 URLs; their union touches **890 questions**. Only **Q1883 and Q1919** have no other URL that returned `HTTP_REACHABLE`; this is the strongest confirmed missing-usable-source case. There are **3 invalid direct question source IDs**, Q1925–Q1927, each with a separate valid EOPT source relationship already attached through its study link. The 44 restricted URLs are not called dead. Semantic source-to-claim fit is not exhaustively determined.

Verified replacement candidates found in official/authoritative site results (candidates only; none applied):

- Negotiable Instruments Law (Act No. 2031): [Lawphil Act No. 2031](https://lawphil.net/statutes/acts/act1911/act_2031_1911.html).
- Nursing Act RA 9173: [Lawphil RA 9173](https://lawphil.net/statutes/repacts/ra2002/ra_9173_2002.html).
- Teachers Professionalization Act RA 7836: [Lawphil RA 7836](https://lawphil.net/statutes/repacts/ra1994/ra_7836_1994.html).
- 2025 LEPT alignment guidance: [Professional Regulation Commission advisory](https://prc.gov.ph/article/guidelines-implementation-prc-ched-jmc-alignment-lept-teacher-education-curriculum).
- IAS 21: [IFRS IAS 21 landing page](https://www.ifrs.org/issued-standards/list-of-standards/ias-21-the-effects-of-changes-in-foreign-exchange-rates/); landing page does not prove full-text access, Philippine adoption, or claim fit.

For the other ISA 230/240/265/315/450/500/520/530/700 404s and other sources, no exact replacement was asserted without a verified equivalent. The 17 dead URL records and complete affected question IDs are in the JSON file. The web lookup only established candidate destinations for the sources named above; it did not update the registry.

## Study and answer-review links

Production validation: 10025 link rows; 9123 distinct linked questions; 1049 exact question-section rows and 1049 unique question-section pairs. The production StudyLibrary validator returns `PASS`, 0 invalid links, 0 missing question/material/source references, 0 invalid anchors, 0 exact links outside practice pools, and 0 links ineligible for the lesson board.

There are 8976 source/material-only rows. All **1049 exact links** are technically anchored and in a practice pool; structural validation does not establish semantic fit. The exact-link review state is 87 pending independent academic review, 4 review-required, and 958 with no explicit review status. Independent academic approvals: **0**.

No question link points to a nonexistent lesson/section; no invalid answer-review anchor was found. One published material, `let-filipino-pagbasa-wika-at-pagsulat`, is an orphan; 15 published materials are explicitly standalone. Fifteen canonical records retain a legacy `study_material_id` with no matching current link-table row, although the material exists; exact IDs and current relationship targets are recorded in JSON.

Q1536/Q1537 exact-section quarantine remains intact; prior demotions for Q4004/Q4005/Q4006 remain without `section_id`. Q1018 remains an exact section relationship pending independent review, not approved.

## Content, assets, taxonomy, and duplicates

- Production `QuestionValidationService`: 9123/9123 valid. Empty stems 0; missing/empty choices 0; invalid structural answer keys 0; missing explanations 0; duplicate IDs 0; duplicate choices under the production case/whitespace-preserving-punctuation rule 0.
- Figure blocks: 56; missing asset files 0; missing alt text 0. No confirmed missing image/passage. Twenty-four table-dependent flags have no separate table block but provide data/graph content in the stem; 89 passage-dependent flags have inline stem context rather than a separate text block. These IDs are retained as context review records in JSON.
- Nested taxonomy/eligibility path errors: 0. Forty-three records omit the redundant top-level `board` field but have a valid nested board ID plus explicit eligibility; 0 top-level/nested board mismatches. 1,109 top-level domain/topic/concept display-field occurrences on 516 questions differ from both the nested taxonomy ID and node name. These are legacy mirror metadata review flags, not invalid canonical taxonomy or eligibility.
- Exact/normalized duplicate stems: 85 clusters / 309 records. Every group has identical choices, answer, and explanation in this scan; no same-stem conflicting-key cluster was found. These remain suspected redundant items, not an instruction to merge or delete.
- Number-neutral near-duplicate templates: 179 groups / 781 records. Exact normalized explanation text also repeats in 219 clusters / 1364 records. These are batch-family and pedagogy review cues, not automatic defects.
- ContentQa full-bank screen: 5498 overlapping issues across 4976 questions. Main flags include 2442 missing cognitive-intent declarations, 129 short-rationale reviews, 702 near-duplicate option wording flags, 905 option-length outliers, and 769 repeated-distractor flags. These counts overlap and require human review.

### Previously flagged questions

| Question | Current audit disposition |
|---|---|
| Q967 | GI/ileostomy stem versus NP IV endocrine concept: taxonomy review; no answer/key change. |
| Q1004, Q1035, Q1008 | Psychiatric safety/taxonomy or exact-section support remains independent review; no generic exact link inferred. |
| Q1018 | Exact self-harm safety section remains review-pending; it is not independently approved. |
| Q896 | Newborn transition context remains clinically review-required; no threshold/protocol inferred. |
| Q4014, Q5025 | Source-limited immunosuppression context; source URL/access and claim fit remain review-required. |
| Q4090 | Medication-associated gait-change item remains coordination/clinical review; no exact section inferred. |
| Q1536, Q1537 | Dose-basis ambiguity remains; exact lesson-section quarantine confirmed intact. |
| Q4004–Q4006 | Prior weak exact links remain demoted; current records are not exact-section linked. |
| Q6403 | Confirmed answer/explanation contradiction; P0 correction proposal above. |

## Developer repository dashboard

The QuestionQualityService count matches canonical storage: 9123 questions. It currently reports 748 issue rows: 0 errors, 747 warnings, 1 review. Its legacy issue grouping classifies all 748 as unclassified; six older structural buckets show zero. The current service validates linked material/source relationships but does not scan canonical question-level `source_ids` or external URL reachability. Thus it does not surface Q1925–Q1927 or the 404 set. The dashboard count is right, but these source checks are an observability gap.

The production view delegates to `QuestionQualityService` → `RepositoryHealthEngine`; the relevant source-study validator loops over material and study-link source IDs, not question `source_ids`. See code references in the JSON artifact. No dashboard code was changed.

## TOP_20_ACTIONABLE_FINDINGS

The detailed QID arrays and evidence for every row are in the machine-readable issue file.

| # | Priority | Finding | Exact scope | Recommended action |
|---:|---|---|---|---|
| 1 | P0 | Active wrong answer relative to exact transcription | Q6403 | Correct key or stem only after authorized item-owner review; no mutation in this audit. |
| 2 | P1 | No reachable registered source URL | Q1883, Q1919 | Verify a current authoritative Act No. 2031 source or mark source-limited. |
| 3 | P2 | Unregistered direct source ID | Q1925, Q1926, Q1927 | Reconcile to existing registered EOPT source ID only after identity check. |
| 4 | P2 | Referenced source URL returned 404 | iaasb-isa-230-documentation (170 questions) | Check moved official document; update only after exact document and claim verification. |
| 5 | P2 | Referenced source URL returned 404 | iaasb-isa-240-fraud (36 questions) | Check moved official document; update only after exact document and claim verification. |
| 6 | P2 | Referenced source URL returned 404 | iaasb-isa-265-control-deficiencies (36 questions) | Check moved official document; update only after exact document and claim verification. |
| 7 | P2 | Referenced source URL returned 404 | iaasb-isa-315-risk-assessment (170 questions) | Check moved official document; update only after exact document and claim verification. |
| 8 | P2 | Referenced source URL returned 404 | iaasb-isa-450-misstatements (36 questions) | Check moved official document; update only after exact document and claim verification. |
| 9 | P2 | Referenced source URL returned 404 | iaasb-isa-500-audit-evidence (170 questions) | Check moved official document; update only after exact document and claim verification. |
| 10 | P2 | Referenced source URL returned 404 | iaasb-isa-520-analytical-procedures (171 questions) | Check moved official document; update only after exact document and claim verification. |
| 11 | P2 | Referenced source URL returned 404 | iaasb-isa-530-audit-sampling (170 questions) | Check moved official document; update only after exact document and claim verification. |
| 12 | P2 | Referenced source URL returned 404 | iaasb-isa-700-audit-opinion (170 questions) | Check moved official document; update only after exact document and claim verification. |
| 13 | P2 | Referenced source URL returned 404 | ifrs-ias-21-foreign-exchange (169 questions) | Check moved official document; update only after exact document and claim verification. |
| 14 | P2 | Referenced source URL returned 404 | philippines-negotiable-instruments-law (160 questions) | Check moved official document; update only after exact document and claim verification. |
| 15 | P2 | Referenced source URL returned 404 | prc-lept-alignment-guidance-2025 (260 questions) | Check moved official document; update only after exact document and claim verification. |
| 16 | P2 | Referenced source URL returned 404 | ra-9173-philippine-nursing-act (82 questions) | Check moved official document; update only after exact document and claim verification. |
| 17 | P2 | Referenced source URL returned 404 | republic-act-7836 (48 questions) | Check moved official document; update only after exact document and claim verification. |
| 18 | P2 | Stale legacy material pointers | Q5206, Q5462–Q5468, Q5487–Q5492, Q6602 | Confirm whether each pointer should become a current relationship. |
| 19 | P2 | Published orphan material | let-filipino-pagbasa-wika-at-pagsulat | Confirm standalone intent or restore a reviewed discovery path. |
| 20 | P2 review | Exact duplicate stem clusters | 85 clusters / 309 records | Review intentional repetition; do not merge/delete automatically. |

The 14 referenced 404 source rows touch 890 unique questions; 888 of those also have at least one other URL that returned HTTP-reachable. Their full per-source QID lists, all remaining non-reachable URLs, 1,109 taxonomy mirror occurrences, and all duplicate clusters are in the issue file. The remaining 3 of the 17 confirmed 404 registry URLs are currently unused by question references.

## Final counts

```text
HEAD=917a098c87bc12f22e4ea246c4f20d6418503300
TOTAL_CANONICAL=9123
ACTIVE=9123
ARCHIVED=0
BOARD_COUNTS={"let":1987,"civil-service":2116,"criminologist":554,"nursing":878,"psychometrician":461,"civil-engineering":922,"cpa":1132,"mechanical-engineering":511,"registered-electrical-engineering":842,"architecture":484}
MULTI_BOARD_COUNT=676
SOURCE_LINKS_TOTAL=35136 unique question/source pairs
VALID_SOURCE_LINKS=35133 registered question/source pairs
MISSING_REQUIRED_SOURCES=0 structural; NO_REACHABLE_URL=2 questions (Q1883,Q1919)
MISSING_SOURCE_URLS=0 on referenced registered source records; 1 unused internal editorial record has no URL
BROKEN_SOURCE_IDS=3 (Q1925,Q1926,Q1927)
CONFIRMED_DEAD_URLS=17 unique; referenced by 14 source records and 890 questions
ACCESS_RESTRICTED_URLS=44
OUTDATED_SOURCE_FLAGS=15 source metadata/access review records; see JSON
SOURCE_CLAIM_MISMATCHES=NOT_EXHAUSTIVELY_DETERMINED
EXACT_STUDY_LINKS=1049
MISSING_OPTIONAL_STUDY_LINKS=8838 eligible placements
BROKEN_REQUIRED_STUDY_LINKS=0
ORPHANED_LESSON_LINKS=0; ORPHAN_PUBLISHED_MATERIALS=1
INVALID_ANSWER_REVIEW_ANCHORS=0
WRONG_SECTION_MAPPINGS=0 structurally confirmed; semantic fit of all mappings is not fully adjudicated
MISSING_EXPLANATIONS=0
INVALID_ANSWER_KEYS=0 structurally; 1 confirmed semantic key defect Q6403
DUPLICATE_IDS=0
POSSIBLE_DUPLICATE_QUESTIONS=85 clusters / 309 records; all exact group keys match
MISSING_IMAGES_OR_PASSAGES=0 confirmed; 24 table-dependent and 89 passage-dependent inline-context review cases
TAXONOMY_ERRORS=0 nested canonical paths
CROSS_BOARD_ELIGIBILITY_ERRORS=0 confirmed
P0_CONFIRMED=1 (Q6403)
P1_CONFIRMED=2 questions without reachable registered source URL (Q1883,Q1919)
P2_CONFIRMED_REFERENCE_DEFECTS=3 invalid direct source IDs; 14 referenced 404 URL records; 15 stale legacy study pointers; 1 orphan published material
P2_QUALITY_REVIEW_GROUPS=85 exact-stem clusters; 219 repeated-explanation groups; other QA flags overlap
P3_FLAGS=8,838 optional exact-link placements plus heuristic authoring improvements
EXPERT_REVIEW_REQUIRED=4976 questions touched by overlapping QA flags plus independent item/source review; this is not an approval count
CANONICAL_HASH_BEFORE=73cdc775cd7240b57898868d67902d833db1171f947c8dcc89081ddaf46fb4be
CANONICAL_HASH_AFTER=73cdc775cd7240b57898868d67902d833db1171f947c8dcc89081ddaf46fb4be
LEARNER_DATA_PRESERVED=TRUE
CURRENT_BROWSER_STATUS=BLOCKED_ON_TERMUX; external acceptance not executed at this audit
ACADEMIC_APPROVALS=0
PUBLIC_RELEASE=NOT_APPROVED
```

## Final recommendation

Hold Q6403 for answer correction and Q1883/Q1919 for a reachable authoritative citation. Repair the three malformed direct source IDs and assess the 15 stale legacy material pointers. Replace the 14 used dead URLs only after matching the replacement document to each claim. Treat the remaining exact-link, duplicate, rationale, cognitive-intent, taxonomy-display, and professional-currentness findings as bounded review work—not permission to bulk-edit the bank. The bank is structurally valid, but it has one verified active scoring error and two questions with no reachable registered source; do not call it fully integrity-clean until those cases are dispositioned.

## Audit validation and evidence

- Production validation command: `php -d memory_limit=1G /data/data/com.termux/files/usr/tmp/boardprep-707-audit-production-validate.php` — exit 0; tested SHA `917a098c87bc12f22e4ea246c4f20d6418503300`.
- Current dashboard audit: `php -d memory_limit=1G /data/data/com.termux/files/usr/tmp/boardprep-707-audit-dashboard.php` — exit 0; tested SHA `917a098c87bc12f22e4ea246c4f20d6418503300`.
- Full ContentQa invocation: `php -d memory_limit=1G /data/data/com.termux/files/usr/tmp/boardprep-707-audit-contentqa.php` — exit 0; tested SHA `917a098c87bc12f22e4ea246c4f20d6418503300`; output `/data/data/com.termux/files/usr/tmp/boardprep-707-contentqa.json`.
- URL evidence: `/data/data/com.termux/files/usr/tmp/boardprep-707-source-url-audit.json`, checked `2026-10-22T22:45:50Z`; a point-in-time reachability test, not claim verification.
- Audit-artifact reconciliation: `python /data/data/com.termux/files/usr/tmp/boardprep-707-build-question-integrity.py` — exit 0; tested SHA `917a098c87bc12f22e4ea246c4f20d6418503300`; JSON parses and the inventory, board, exact-link, URL, Q6403, quarantine, and protected-hash assertions pass.
- Full Doctor and browser tests were not run; neither is necessary for this data-only read-only audit, and local disk remains below the prior Full Doctor requirement.

**Machine-readable complete issue file:** `/data/data/com.termux/files/home/boardprep-batch-706/docs/reports/batch-707-question-integrity-issues.json`
