# Batch 672 — Educational Quality and Reconciliation Review

Review branch started from local `main` at `d288b17b78a22e75d661d0b571baf0e9edf2d9b7`. Canonical IDs and question count were unchanged. Findings below separate verified defects from editorial or scope review.

## A1 — CSE civic resource and LET questions

The former `CSE General Information: Civic, Ethical, Rights, and Environmental Decisions` material was already configured for LET, Civil Service, and Criminologist. Its LET/Criminologist focus explicitly described an editorial foundation, not an examination prediction. The CSE-specific title and summary obscured that shared intent, so the material is now titled **Civic, Rights, and Public-Interest Decisions** and says that the CSC source establishes Civil Service scope only.

All 13 existing LET links remain: Q42, Q44, Q46, Q48, Q50, Q2434–Q2438, and Q2441–Q2443. The questions cover public-interest trade-offs, participation, program coverage, accessibility, report interpretation, privacy, government authority, equality, due process, assembly, accountability, and inclusive consultation. Q46 and Q50 now keep their civic context while also linking to exact quantitative resources for LET and CSE learners:

- Q46 uses its own eligible-household denominator (80/100 = 80%; 90/150 = 60%) and links to OpenStax *Prealgebra 2e*, §6.5.
- Q50 describes an agency report and explains how a truncated axis changes visual impression without changing values; it links to OpenStax *Introductory Statistics 2e*, §2.2.
- Both links remain board-scoped through the existing LET mathematics and Civil Service numerical materials. No eligibility mapping or cross-board placement was removed.

Specific source paths were added to the privacy, public-assembly, sign-language-access, and built-environment questions: RA 10173; *Integrated Bar of the Philippines v. Atienza*; RA 11106; and the NCDA 2024 BP 344 rules. Explanations avoid claiming a legal outcome from the hypothetical facts alone.

## A2 — Runtime placement reconciliation

The baseline report of 9,745 runtime placements was reproduced with the production `QuestionPoolFilter`. The loss was CSE Q6298: it shared the exact normalized stem of LET Q86, which is also eligible in CSE. The two records have distinct contexts, answer sets, and explanations, so they are distinct questions, not duplicates. Q6298 now has a records-audit-specific stem. Both canonical IDs and all original answer choices/correct-answer strings remain unchanged.

| Board | Native | Effective declared | Runtime before | Runtime after |
|---|---:|---:|---:|---:|
| Architecture | 484 | 484 | 484 | 484 |
| Civil Engineering | 922 | 922 | 922 | 922 |
| Civil Service | 1,553 | 1,807 | 2,115 | 2,116 |
| CPA | 1,132 | 1,132 | 1,132 | 1,132 |
| Criminologist | 451 | 554 | 554 | 554 |
| LET | 1,788 | 1,871 | 1,886 | 1,886 |
| Mechanical Engineering | 511 | 511 | 511 | 511 |
| Nursing | 878 | 898 | 898 | 898 |
| Psychometrician | 401 | 401 | 401 | 401 |
| Registered Electrical Engineering | 842 | 842 | 842 | 842 |
| **Total** | **8,962** | **9,422** | **9,745** | **9,746** |

`Batch672CountReconciliationTest.php` locks these three separate counts and board values. The check uses the production repository-health path, which includes deduplication and eligibility semantics; question totals alone are not used as evidence.

## A3 — HayaDoctor recovery commits

The active submodule stays pinned at `dc4fcda730d9a922f726593746e34ca0c9c0e890`. Four local commits exist on the preserved recovery ref `recovery/main-before-consolidation-20261013`; the submodule is clean and its pointer was not changed.

| Commit | Classification | Review |
|---|---|---|
| `8ba581516f5dde68cdadc974aa676da91ed5f065` | Useful; separate publication | Adds atomic JSON report publishing, JSON encoding errors, deterministic check ordering, and explicit registry failures. It is self-contained, but should be tested and published in HayaDoctor before repinning. |
| `7181e1865be6712bbe3fac1ec5ff4e25212a4b46` | Useful; separate publication | Adds a portable static UI contract analyzer and report integration. Its markup/style scans are heuristic warnings; calibrate false positives before treating them as release blockers. |
| `33f551e3921d3e83b09887d77f22fed6b6feed52` | Useful refinement | Extends UI density diagnostics and tests. No BoardPrep API conflict found. |
| `0b5001c3385771760223fb07b563522e5ef7ac0f` | Useful refinement | Allows dynamic chart dimensions in UI checks, reducing a likely false positive. No BoardPrep API conflict found. |

These changes remain unpublished submodule work and were not copied into BoardPrep.

## Board coverage and priority

Coverage was compared using native/effective/runtime counts, active readiness leaves, current source/study defects, and stored scope evidence. Counts are inventory signals, not readiness proof.

| Board | Native | Effective | Runtime | Active readiness leaves | Evidence-backed priority |
|---|---:|---:|---:|---:|---|
| LET | 1,788 | 1,871 | 1,886 | 205 | Exact TOS crosswalk remains unresolved for 35 source-scope review leaves; improve source/readiness mapping before new authoring. Q46/Q50 now have math pathways. |
| Civil Service | 1,553 | 1,807 | 2,116 | 56 | Three shared/structural scope rows remain non-actionable; Q46/Q50 have board-scoped numerical study links. |
| Criminologist | 451 | 554 | 554 | 37 | Four shared/general foundation rows remain structurally outside native LEC areas; preserve them as shared context, not new authoring targets. |
| Nursing | 878 | 898 | 898 | 40 | Existing Batch 625 content remains intact; no new unsupported questions added. |
| Psychometrician | 401 | 401 | 401 | 20 | Q4798 now cites RA 11036 through its material; exact scope and current service rules remain deferred. |
| CPA | 1,132 | 1,132 | 1,132 | 113 | Three conservative weak-but-defensible source-fit reviews remain; improve worked reasoning and relevant-cost pathways without changing answers. |
| Civil Engineering | 922 | 922 | 922 | 19 | No confirmed gap selected for authoring in this pass. |
| Mechanical Engineering | 511 | 511 | 511 | 35 | No confirmed gap selected for authoring in this pass. |
| Registered Electrical Engineering | 842 | 842 | 842 | 37 | No confirmed gap selected for authoring in this pass. |
| Architecture | 484 | 484 | 484 | 14 | Existing readiness review already defers expansion where propositions are exhausted or visual evidence is required. |

The work prioritized confirmed study-path, source, and explanation defects over increasing counts. No new questions were authored.

## Review signal disposition

The stored source review queue remains **46 before / 46 after**; none were converted to hard failures by a subjective detector.

- **35 LET scope-fit UNKNOWN:** `bloom-cognitive-processes`, `bronfenbrenner-ecology`, `bruner-discovery`, `bruner-representation`, `erikson-competence`, `erikson-identity`, `gardner-multiple-intelligences`, `inclusive-access`, `inclusive-accommodation`, `inclusive-adaptation`, `inclusive-assessment`, `inclusive-barriers`, `inclusive-classroom`, `inclusive-collaboration`, `inclusive-equity`, `inclusive-family`, `inclusive-modification`, `inclusive-participation`, `inclusive-peer-support`, `inclusive-respect`, `inclusive-universal-design`, `kohlberg-moral-reasoning`, `let-ethical-duties`, `maslow-needs`, `pavlov-classical-conditioning`, `piaget-development`, `piaget-stages`, `prof-development-readiness`, `rogers-humanistic`, `skinner-operant-conditioning`, `thorndike-law-effect`, `vygotsky-scaffolding`, `vygotsky-social-learning`, `vygotsky-zpd`, and `watson-behaviorism`. These are unresolved TOS/leaf crosswalks, not proven source defects; keep authoring at zero until scope is adjudicated.
- **3 LET structural/non-actionable:** `cse-constitutional-principles`, `cse-environmental-public-interest`, `cse-ethical-public-service`.
- **3 Civil Service structural/non-actionable:** the same three shared CSE foundation rows.
- **4 Criminologist structural/non-actionable:** `cse-constitutional-principles`, `cse-ethical-public-service`, `social-civics-public-good`, `social-history-primary-sources`.
- **1 Psychometrician currentness/scope review:** `psych-abnormal-mental-health-law`. RA 11036 is now registered and linked to Q4798; the leaf remains `DEFER` because current program-specific requirements and exact PRC subcompetency mapping still need review.

CPA Q1300, Q1306, and Q1316 remain a separate conservative semantic-fit review set (three weak-but-defensible signals, zero confirmed source defects). Updated calculations and study links do not erase that editorial classification.

## Material and question changes

- **Questions rewritten, IDs preserved:** 42, 44, 46, 48, 50, 2434, 2435, 2436, 2438, 2441, 2442, 2443, 4798, 6298, 1300, 1306, 1316. No correct-answer strings or choice arrays changed.
- **Materials improved:** shared civic foundation; LET mathematics; CSE numerical foundation; CPA CVP; CPA relevant decisions; Psychometrician symptom/functioning review.
- **New sources registered:** RA 11106; RA 11036; Official Gazette RA 10173; *Integrated Bar of the Philippines v. Atienza*; OpenStax Prealgebra 2e §6.5; OpenStax Introductory Statistics 2e §2.2.
- **New study links:** LET math and CSE numerical paths for Q46/Q50; CPA relevant-cost paths for Q1300/Q1316. Existing 13 LET civic links remain.

Question IDs, choices, correct-answer strings, eligibility mappings, and native counts are stable. The 19 pre-existing learner usage rows are kept outside the content commit and verified against the original backup.

## Verification status at this review

- Focused Batch 672 education/source regression: PASS.
- Focused ten-board count regression: PASS; 8,962 / 9,422 / 9,746.
- Content integrity: PASS_WITH_JUSTIFIED_EXCLUSIONS; 8,944 derivable flashcards, 18 justified exclusions, zero accidental non-derivable cards.
- Psychometrician readiness schema after source update: valid; readiness intentionally remains DEFER.
- No browser or live-host claim is made in this review. Final release evidence is reported separately.

## Primary references checked

- [Official Gazette, Republic Act 10173](https://officialgazette.gov.ph/2012/08/15/republic-act-no-10173/)
- [Supreme Court E-Library, Republic Act 11106](https://elibrary.judiciary.gov.ph/thebookshelf/showdocs/2/85265)
- [NCDA, Revised 2024 BP 344 rules](https://ncda.gov.ph/revised-2024-rules-and-regulations-implementing-batas-pambansa-344/)
- [Supreme Court E-Library, Integrated Bar of the Philippines v. Atienza](https://elibrary.judiciary.gov.ph/thebookshelf/showdocs/1/53592)
- [Supreme Court E-Library, Republic Act 11036](https://elibrary.judiciary.gov.ph/thebookshelf/showdocs/2/83255)
- [OpenStax Prealgebra 2e, §6.5](https://openstax.org/books/prealgebra-2e/pages/6-5-solve-proportions-and-their-applications)
- [OpenStax Introductory Statistics 2e, §2.2](https://openstax.org/books/introductory-statistics-2e/pages/2-2-histograms-frequency-polygons-and-time-series-graphs)
