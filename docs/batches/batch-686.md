# BoardPrep — Batch 686 Final Report

## Status fields

```text
HEAD_BEFORE=14497847b23b794ce5b2566916a47838454c4333
ORIGIN_MAIN_BEFORE=14497847b23b794ce5b2566916a47838454c4333
HEAD_AFTER=044f996603c6306c4982f0d6cb3e987cfbd6d1d8 (last application/test commit; report and evidence inventory are a docs-only descendant)
START_TIME=2026-10-16 13:26:48 UTC
END_TIME=2026-10-16 15:33:06 UTC
ELAPSED_MINUTES=126.3
APPLICATION_COMMITS=db5dad1df645bef29bc80231ee5e611a481bbe0f; 218309fe97d26e9f00b757e90f29c2b678993b82; a2e40a651c605b2125508defaa60c02220044e77; 044f996603c6306c4982f0d6cb3e987cfbd6d1d8
CHANGED_QUESTION_IDS=7045,1181,1146,1227,1137,1138,1141,1142,1143,1234,5413,5301,5258,5281,5226,5993,5763,5538,5548,5563,864,930,897,895,861,622,6334,6353,5667,1387,1397,3533,4622,1589,1607
SOURCE_REVIEWED=100 distinct questions, AI-assisted only, 10 per supported board
DIRECTLY_SUPPORTED=34 (AI editorial classification, pending human confirmation)
PARTIAL=32 (AI editorial classification, pending human confirmation)
CONTRADICTED=0
UNKNOWN=34 (sample records; 8,862 canonical questions remain unreviewed)
COGNITIVE_INTENT_BEFORE=2,402 missing
COGNITIVE_INTENT_AFTER=2,377 missing; 25 labels applied pending human review
DUPLICATE_FINDINGS_BEFORE=85 exact errors; 85 normalized warnings; 179 heuristic clone signals
DUPLICATE_FINDINGS_AFTER=85 exact errors; 85 normalized warnings; 179 heuristic clone signals
DUPLICATE_CLUSTERS_ADJUDICATED=85 exact groups reconciled to Batch 681 and classified as redundant-content candidates; 0 human approvals and 0 merges/deletions
WEAK_RATIONALES_BEFORE=139
WEAK_RATIONALES_AFTER=129
NUMERICAL_SUPPORT_BEFORE=232/708 (32.77%)
NUMERICAL_SUPPORT_AFTER=245/714 (34.3%); 19 unresolved audit findings
BROWSER_RUN_ID/COMMIT/PASS/FAIL/SKIP=38922718846 / 044f996603c6306c4982f0d6cb3e987cfbd6d1d8 / 26 / 0 / 4 intentional project skips (pass/skip counts derived from the committed project matrix; hosted result is VERIFIED_PASS)
DOCTOR_STATUS=NOT_RUN for Batch 686; full Doctor/security suites were deferred under the storage safeguard
PRODUCT_READINESS=NOT_READY; formal command returned PRODUCT_FEATURE_COMPLETE=NO and PRODUCT_LAUNCH_READY=NO
PROTECTED_STATE_STATUS=PASS, 59/59 protected manifest hashes match; question-usage worktree delta independently isolated to 19 records
UNRELATED_WORK_PRESERVED=YES; existing learner delta, untracked assets, two stashes, recovery refs, branches, and HayaDoctor pin preserved
STORAGE_FREE_BEFORE=1,581,404 KiB (~1.51 GiB, first captured check)
STORAGE_FREE_AFTER=1,424,864 KiB (~1.36 GiB; below the 1.5 GiB floor by about 145 MiB at the final post-push check)
PUSH_STATUS=Application/test commits and final documentation/evidence commit pushed by ordinary fast-forward; no force push
CLIPBOARD_STATUS=UNVERIFIED (termux-clipboard-set returned success, but termux-clipboard-get returned 0 bytes; SHA-256 readback did not match)
UNRESOLVED_BLOCKERS=Human academic review; 8,862 unreviewed source questions; 75 sampled cognition records without a justified label; unresolved duplicate/clone decisions; full Doctor/security and ten-board HTTP gates not run; all 12 human/mobile/accessibility cases; owner-approved privacy and live-host checks
NEXT_SAFE_BATCH=Obtain independent academic sign-off for Q616/Q937 and the 35 pending edits, then review bounded duplicate clusters and continue source provenance in ten-board batches; keep readiness NOT_READY
```

`HEAD_AFTER` identifies the final code/test commit run by exact-commit Chromium. The batch report, browser review inventory, and run registry are committed separately as documentation/evidence, without application changes.

## Recovery baseline and preservation

Batch 685's full report and release evidence were read before editing. The current branch was `main`; baseline `HEAD` and `origin/main` both pointed to `14497847b23b794ce5b2566916a47838454c4333`. Batch 685's last verified application commit was `5f3fada7c89266a66f1107e6350f1e99fb82d84c`. No new worktree was created. The pre-existing two stashes, recovery references, and HayaDoctor pin `dc4fcda730d9a922f726593746e34ca0c9c0e890` were retained.

The pre-edit protected-state manifest is in the Termux temporary directory `boardprep-batch686-dmu3uj2y/protected-files.sha256`. All **59/59** listed hashes still match. Recorded spot hashes include attempts `e968fd3e8a391065ac2314ab9bcff932fc572cbe6024e16b40569b694bbed84b`, weakness `37517e5f3dc66819f61f5a7bb8ace1921282415f10551d2defa5c3eb0985b570`, and flashcard/question-statistics files `4f53cda18c2baa0c0354bb5f9a3ecbe5ed12ab4d8e11ba873c2f11161202b945`. The current question-bank file hash is `26abd43caaefa06f5536ae93b3cc09ed9f22b0fc76803cfc7afc63b4e005d237`.

The pre-existing uncommitted question usage delta remains outside all commits. Compared with the final committed question bank, exactly 19 question records differ only in `timesUsed`, `timesCorrect`, `timesIncorrect`, and their associated `updatedAt`: net `+26` uses, `+2` correct, `+24` incorrect. The 35 authored academic corrections are in scoped commits; no learner attempts, progress, weakness, statistics, recovery files, or flashcard state were committed or reset.

Pre-existing unrelated work remains untouched: `.commandcode/`, `docs/batches/batch-685-protected-state.sha256`, `public/assets/me/fluid-elbow.svg`, `gear-train.svg`, `shaft-guard.svg`, `storage/flashcard_state.json`, `storage/question-statistics.json`, and `tools/Batch596Author.php`. `storage/questions.json` is still dirty solely for the usage delta above. These files were not staged by a blanket add.

## Question-level source provenance

The source ledger is [academic-source-reviews.json](../../storage/academic-source-reviews.json); the coverage delta is [batch-686-source-coverage.json](batch-686-source-coverage.json). It adds 100 distinct, bounded question reviews (10 per supported board) from a baseline of zero question-level review rows. Each row binds a question snapshot to a source snapshot, source identity, stable section/page locator, concept/material links, review limits, and `AI_ASSISTED_EDITORIAL_REVIEW` state. All rows remain `AI_REVIEWED_PENDING_HUMAN_CONFIRMATION`.

| Board | Direct support | Partial | Unknown | Total |
|---|---:|---:|---:|---:|
| Architecture | 0 | 4 | 6 | 10 |
| Civil Engineering | 5 | 3 | 2 | 10 |
| Civil Service | 7 | 3 | 0 | 10 |
| CPA | 1 | 1 | 8 | 10 |
| Criminologist | 4 | 4 | 2 | 10 |
| LET | 3 | 4 | 3 | 10 |
| Mechanical Engineering | 4 | 3 | 3 | 10 |
| Nursing | 1 | 6 | 3 | 10 |
| Psychometrician | 1 | 2 | 7 | 10 |
| Registered Electrical Engineering | 8 | 2 | 0 | 10 |
| **Total** | **34** | **32** | **34** | **100** |

`DIRECT_SUPPORT` and `PARTIAL` are AI-assisted source-semantic classifications, not human academic verification. The sample is selected rather than random; 8,862 canonical questions remain unreviewed, human-confirmed source rows remain zero, and no whole-corpus verification is claimed. Registered links alone do not count. Professional/regulatory source adoption and effective-date checks remain open. No examination-frequency claim was added. The AASC PSA500 reproduction source has an exact source identity and locator; current Philippine adoption/version remains unverified.

The corpus remains at 8,962 canonical questions. Existing complete lessons/PDFs cover all ten boards; no new lesson was added because this batch did not establish a specific lesson gap. Question-to-complete-lesson/PDF structural linkage stays at 39 questions (39 before, 39 after). The separate 100-row source sample has structural material/source linkage for all sampled items; that is not a claim of semantic completeness. The ten existing searchable PDFs passed generator `--check`, two-page format/hash checks, and text/title extraction; the new browser suite validates a representative LET document.

## Duplicate and clone triage

See [batch-686-duplicate-triage.json](batch-686-duplicate-triage.json) and the human packet. The live 85 exact-duplicate groups all match the Batch 681 groups; the 85 normalized groups concern the same question-ID sets. Exact equality makes them redundant-content candidates, but no group has human academic approval. Three previously identified same-concept groups were rechecked as meaningful variations. The 179 near-duplicate and 179 number-swap components remain heuristic clone risks with insufficient evidence.

No questions were merged or deleted. Question identities, attempts/history, eligibility, lesson links, usage, and flashcard relationships remain intact. QA still reports 85 exact-duplicate errors and 85 normalized-duplicate warnings. Automated equality and stable cluster IDs improve triage; they do not substitute for a human decision about downstream learner impact.

## Cognitive intent, rationales, and human review

The intent gap decreased from 2,402 to 2,377. The 100 inspected records were balanced 25 each across the **four boards that contain missing-intent records**: Civil Engineering, Civil Service, LET, and Nursing. The other six supported boards currently have zero missing-intent records, so they were not eligible for this missing-record sample. Twenty-five labels are applied pending human review; 75 inspected rows remain `REVIEW_REQUIRED_NO_AUTOMATED_LABEL`. The source/cognitive ledgers and dashboard preserve reviewer type, question fingerprint, reason, and pending state.

| Board with missing intent | Before | After |
|---|---:|---:|
| Civil Engineering | 362 | 352 |
| Civil Service | 1,056 | 1,051 |
| LET | 808 | 803 |
| Nursing | 176 | 171 |
| Other six supported boards combined | 0 | 0 |

Ten rationale/calc explanations were strengthened and recomputed against the stem's explicit inputs/formula: `622, 6334, 6353, 5667, 1387, 1397, 3533, 4622, 1589, 1607`. No answer key changed. Supported structured recomputations rose from 232/708 (32.77%) to 245/714 (34.3%). The denominator grew by six; 469 cases remain unsupported by a reliable structured formula, four are unreadable, 60 have unresolved units, and the audit still has 19 findings. The unresolved-unit count increased from 57 because the structured audit now includes additional payloads; it is not presented as a repaired count.

All 35 Batch 686 question edits are recorded with before/after values, evidence/reason, validation, and `REVIEW_REQUIRED` / `APPLIED_PENDING_HUMAN_SIGNOFF`; all have `human_signoff=NOT_RECORDED`:

- Cognitive intent (25): `7045, 1181, 1146, 1227, 1137, 1138, 1141, 1142, 1143, 1234, 5413, 5301, 5258, 5281, 5226, 5993, 5763, 5538, 5548, 5563, 864, 930, 897, 895, 861`.
- Rationales/calculations (10): `622, 6334, 6353, 5667, 1387, 1397, 3533, 4622, 1589, 1607`.

The human packet [batch-686-human-academic-review.md](batch-686-human-academic-review.md) prepares Questions 616 and 937 with exact changes, source references, educational reasoning, and outstanding checks. Those two edits originated in Batch 685; they are included for sign-off, not counted among the 35 new Batch 686 changes. No reviewer was impersonated and no approval state was changed.

## Developer QA and corpus integrity

The existing Content Intelligence/Content Priority workflow now exposes structural links separately from semantic source states, pending/review decisions, source locators, stable duplicate/clone cluster IDs, taxonomy and board context, and direct question-editor links. Finding lists remain paginated/bounded (25 rows by default, capped at 100). Stale question/source snapshots are detected. Fail-on-error CLI behavior remains unchanged.

Before/after full QA scan:

| Metric | Before | After |
|---|---:|---:|
| Canonical questions | 8,962 | 8,962 |
| Total findings | 3,083 | 3,048 |
| ERROR | 85 | 85 |
| WARNING | 224 | 214 |
| REVIEW | 2,773 | 2,748 |
| INFO | 1 | 1 |
| Missing cognitive intent | 2,402 | 2,377 |
| Weak rationale | 139 | 129 |
| Exact / normalized duplicates | 85 / 85 | 85 / 85 |

The QA CLI exits nonzero as required while its 85 existing exact-duplicate ERROR findings remain. It was not reported as PASS. Structural content integrity passed with justified exclusions: 8,962/8,962 questions eligible and linked, 8,944 derivable flashcards, 18 justified exclusions, and zero accidental non-derivable cards. No duplicate records were removed.

## Chromium and PDF acceptance

The exact final application/test commit `044f996603c6306c4982f0d6cb3e987cfbd6d1d8` passed hosted Chromium run [38922718846](https://github.com/esonhaya/boardprep/actions/runs/38922718846); the result is registered as `VERIFIED_PASS`. Desktop is `1366×768`, mobile `390×844`, with the existing narrow viewport sweep. The test matrix contains 30 project executions: 26 pass and four intentional project skips. Counts are derived from the committed test/project matrix; the hosted result JSON was not downloaded.

The new coverage verifies the study-resource PDF response (`application/pdf`, `%PDF-`), manifest hash and lesson identity, searchable document title, keyboard-triggered download and byte equality; mobile board → study library → lesson navigation; and a read-only developer editor GET workflow that confirms the visible update form while sending no write requests. The exact-run screenshot/diagnostic artifact is remote: ID `12054784399`, `boardprep-chromium-visual-qa`, 127,016,768 bytes, expiring 2026-10-30. It was inventoried but not downloaded or manually reviewed due storage headroom.

Failure classifications and the exact correction trail are in [batch-686-browser-review-inventory.md](batch-686-browser-review-inventory.md). Run 389187 exposed a headless PDF navigation/download expectation and a selector matching a hidden conditional placement form. The app returned a valid static PDF; there was no application regression. Run 389218 then showed the shared keyboard helper's 30-Tab limit did not reach the PDF link. The PDF test now allows 100 real Tab events while retaining the focus-visible check. Run 389227 passed; assertions were not suppressed or removed. Automated screenshots are not human visual acceptance.

## Readiness and validation

Formal `php tools/product-readiness.php` returned exit 1 by design and reported:

```text
PRODUCT_FEATURE_COMPLETE=NO
PRODUCT_LAUNCH_READY=NO
REAL_BROWSER_ACCEPTANCE=BROWSER_EXECUTED_PASS
OWNER_PRIVACY_TERMS=MISSING_OR_UNAPPROVED
HUMAN_ACCEPTANCE_CASES=12/12; NOT_PASSED=12
OPEN_P0_P1=0
BLOCKING_CAPABILITIES=1
MANDATORY_EVIDENCE_NOT_PASSED=3
```

**PASS:** changed PHP syntax checks; JSON integrity and full question-bank identity tests; PDF generator `--check`; `StudyPdfPipelineTest`; `StudyLibraryNavigationTest`; `StudyNavigationTest`; `StudyResourceReadinessTest`; `Batch686ReviewLedgersTest`; `ContentQaServiceTest`; `ContentPriorityServiceTest`; `ContentPriorityCliTest` with `memory_limit=512M`; `QuestionBankContentIntegrityTest`; taxonomy/regression tests; numerical consistency audit; browser JavaScript syntax; browser-failure diagnostic regression; and `git diff --check`. Exact-commit hosted Chromium passed.

**NOT_RUN:** local Chromium (unsupported/uninstalled on Termux); full Doctor release and full security/privacy suite; this batch's ten-board HTTP journey; production preflight/smoke; human device/screen-reader/WCAG acceptance. The final local free space was below the 1.5 GiB safeguard, so these disk-sensitive gates were not attempted. Batch 685's historical technical evidence is not carried forward as a Batch 686 pass.

The expanded MVP-01–MVP-12 mobile/screen-reader checklist is [HUMAN_TESTER_READINESS.md](../HUMAN_TESTER_READINESS.md). Site/provider-specific operator, privacy, retention/deletion, HTTPS/cookie, PHP-extension, session-isolation, and filesystem-permission decisions remain in [OWNER_PRIVACY_AND_HOSTING_DECISIONS.md](../OWNER_PRIVACY_AND_HOSTING_DECISIONS.md), with owner-controlled fields explicitly pending.

## Storage and Git

Free space began at approximately 1,581,404 KiB and was measured at 1,424,864 KiB after the final push (about 1.36 GiB), below the required 1.5 GiB floor by about 145 MiB. No 127 MB browser artifact or local Chromium package was downloaded; the cause of the approximately 153 MiB decline was not isolated. No unrelated or user-owned files were cleaned to recover space. Further local heavy validation is deferred until headroom is restored.

Scoped application/test commits were pushed only by ordinary fast-forward. `origin/main` matched the application commit before the final documentation/evidence commit. The final documentation push and repository HEAD are recorded in the Git log; no force push, reset, new worktree, learner-data commit, or HayaDoctor pin change occurred.

## Unresolved blockers and next batch

- Human academic sign-off is pending for Questions 616/937, the 35 new edits, and the duplicate packet.
- The source sample is bounded and AI-assisted; 8,862 questions have no question-level source review.
- Seventy-five inspected cognitive cases remain unlabelled; 2,377 corpus records still lack cognitive intent.
- Exact duplicate findings remain 85 errors; 179 near-duplicate and 179 number-swap clone groups need evidence-based review.
- Numerical coverage remains 245/714; 469 cases do not have a supported structured recomputation.
- Full Doctor/security, ten-board HTTP, production-host, owner privacy, and all 12 human/mobile/accessibility checks remain outstanding.
- Product readiness remains **`NOT_READY`**.

Recommended next batch: collect authentic independent reviewer decisions for Q616/Q937 and this batch's 35 changes; adjudicate a small high-impact exact-duplicate packet without merging IDs; and extend the source ledger by another bounded, board-balanced group with stable locators and explicit unknowns. Do not start storage-heavy release gates until free space exceeds the 1.5 GiB floor.
