# Batch 688 — Doctor P0 Recovery, Source Integrity & Release-Gate Hardening

## Batch summary

START_TIME=2026-10-17 02:30 UTC (baseline inspection; minute precision)
END_TIME=2026-10-17 03:04 UTC (report handoff; minute precision)
ELAPSED_MINUTES=34
HEAD_BEFORE=cd04a9741ed2b8af868d41725350e3c0e369a691
ORIGIN_MAIN_BEFORE=cd04a9741ed2b8af868d41725350e3c0e369a691
HEAD_AFTER_APPLICATION=614aa36990c4ae792e2a0fd7f2fb257f4aa3f4fd
HEAD_AFTER_DOCUMENTATION=b994c2e98edb55f58f396533aa55da8489bafcad (browser evidence registration; this report is a later documentation-only commit)
APPLICATION_COMMITS=614aa36990c4ae792e2a0fd7f2fb257f4aa3f4fd — Fix Doctor source and browser evidence validation
EVIDENCE_COMMIT=b994c2e98edb55f58f396533aa55da8489bafcad — Register Batch 688 exact Chromium evidence

## Protected state and storage

STORAGE_FREE_BEFORE=8,083,908 KiB (about 7.71 GiB; internal Termux filesystem)
STORAGE_FREE_AFTER=8,073,976 KiB (about 7.70 GiB; internal Termux filesystem)
STORAGE_FLOOR_STATUS=PASS — remained above the 1.5 GiB minimum
STORAGE_RECOVERY_ACTIONS=NONE — the user’s cleanup was preserved; no historical scratch/archive was restored or deleted
PROTECTED_STATE_STATUS=PASS — the existing 59-entry Batch 687 SHA-256 manifest verified; storage/questions.json remained SHA-256 26abd43caaefa06f5536ae93b3cc09ed9f22b0fc76803cfc7afc63b4e005d237
CANONICAL_QUESTIONS_BEFORE_AFTER=8,962 / 8,962; all canonical IDs and non-usage question content match the baseline
QUESTION_USAGE_DELTA=19 rows, usage counters only; preserved unchanged throughout Batch 688
HAYADOCTOR_PIN_STATUS=PASS — tools/HayaDoctor remains dc4fcda730d9a922f726593746e34ca0c9c0e890
UNRELATED_WORK_PRESERVED=PASS — the pre-existing dirty storage/questions.json and untracked .commandcode/, batch-685-protected-state.sha256, three public/assets/me SVGs, storage/flashcard_state.json, storage/question-statistics.json, and tools/Batch596Author.php remain untouched and unstaged
STASHES_AND_RECOVERY_REFS=Preserved; no stash, branch, recovery ref, or repository worktree was removed or rewritten
TEMP_ARTIFACTS=Two small Doctor evidence ZIPs (about 191 KB each) were downloaded to Termux tmp for bounded parsing. The 130.5 MB Chromium artifact remains hosted and was not downloaded. Test-owned temporary Git fixtures were removed by their test cleanup. No historical archive cleanup was performed.

## Root causes and fixes

SOURCE_LIBRARY_ROOT_CAUSE=storage/sources.json record aasc-psa-500-redrafted is a professional-standard publisher overview with source_type PROFESSIONAL_STANDARD. SourceRegistryService did not recognize that legitimate type and returned only the record ID; SourceStudyLibraryTest collapsed all three validators into a generic error. StudyLibraryService and ExamIntelligenceService data were otherwise structurally valid.
SOURCE_LIBRARY_FIX=Added PROFESSIONAL_STANDARD to the supported source types. SourceRegistryService now returns record-level issues with type, ID, severity, reason, and recommended action. ExamIntelligenceService now reports the affected signal and invalid source/type/exam/confidence relationship. SourceStudyLibraryTest prints each failing validator and its issues. No source, material, lesson, question, or mapping was deleted or remapped.
SOURCE_LIBRARY_TEST=PASS — php tools/Tests/SourceStudyLibraryTest.php. Isolated regressions cover valid source/study records, unsupported source types, broken material/link source references, unknown question IDs, incompatible eligibility, duplicate study-link IDs, actionable diagnostics, the existing published LET lesson/PDF/question links, and the 8,962-question identity invariant.

DEPENDENCY_ROOT_CAUSE=The scanner found 45 raw findings: 44 resolver-confirmed analyzer artifacts and one evidence-backed dynamic forwarding call, with zero unresolved findings. The historical test still required exactly 43/1 after an additional legitimate root-qualified StudyResourceReadinessService reference had been added. This was an obsolete count assertion, not a newly unresolved dependency.
DEPENDENCY_CLASSIFICATIONS_BEFORE_AFTER=Historical assertion 43 FIXED_ANALYZER / 1 JUSTIFIED_DYNAMIC (44); current structural accounting 44 FIXED_ANALYZER / 1 JUSTIFIED_DYNAMIC / 0 UNRESOLVED (45). All findings remain counted and classified individually.
DEPENDENCY_TEST=PASS — php tools/Tests/Batch675StaticDoctorPrecisionTest.php. Counts must reconcile to the raw total, unresolved must remain zero, and a genuinely unresolved root-qualified dependency stays unclassified so the Doctor check fails. Alias, root-qualified name, false namespace match, variadic, and verified forwarding regressions remain covered.

BROWSER_EVIDENCE_ROOT_CAUSE=The Doctor release job used actions/checkout@v4 with its default depth-1 history. BrowserEvidenceService proves historical ancestry with git merge-base --is-ancestor, so the checkout could not establish that Batch 677’s verified commit preceded a documentation-only descendant.
BROWSER_EVIDENCE_FIX=The Doctor release job now uses fetch-depth: 0. BrowserEvidenceService and its evidence distinctions were not weakened. Release Gates also runs when docs/release-evidence/browser-workflows.json changes, so registering fresh evidence rechecks the evidence on the updated documentation descendant.
BROWSER_ANCESTRY_TEST=PASS — php tools/Tests/BrowserWorkflowEvidenceTest.php. A bounded local full-history fixture proves documentation-only descendant evidence; a depth-1 fixture missing the evidence commit fails closed. GitHub’s final Doctor job log confirms fetch-depth: 0. Exact failures, stale application changes, mismatches, dirty worktrees, invalid attestations, jobs, and GitHub API evidence remain covered.

## Validation results

LOCAL_FOCUSED_TESTS=PASS — SourceStudyLibraryTest.php; Batch675StaticDoctorPrecisionTest.php; BrowserWorkflowEvidenceTest.php; StudyResourceReadinessTest.php; ContentIntegrityServiceTest.php
PHP_SYNTAX=PASS — both changed services and all three changed PHP test files
GIT_WHITESPACE=PASS — git diff --check before each scoped commit

DOCTOR_P0_BEFORE_AFTER=4 (Batch 687 run 38931866048, artifact 12059421961) → 0 (Batch 688 final run 38996511120)
DOCTOR_FAILED_DOMAINS_AFTER=NONE — STUDY_EXPERIENCE, STUDY_FLASHCARD_EXPERIENCE, DEVELOPER_HEALTH, and PRODUCTION_CONFIGURATION all PASS. SOURCE_TRACEABILITY remains REVIEW, not a failed domain.
DOCTOR_RELEASE_STATUS=PASS — final Doctor release gate PASS at docs-only HEAD b994c2e98edb55f58f396533aa55da8489bafcad; P0=0, P1=0, P2=1, P3=2; health WARN with 0 failed health checks. Decision: RELEASE_READY_WITH_NONBLOCKING_DEBT. The earlier exact application-commit Doctor run 38995936936 also passed with P0=0.
DOCTOR_WORKFLOW_RUN_ID=38996511120; artifact=12087217948; commit=b994c2e98edb55f58f396533aa55da8489bafcad
SECURITY_PRIVACY_STATUS=PASS — all 6/6 matrix jobs on run 38996511120
TEN_BOARD_HTTP_STATUS=PASS — run 38996511120
PRODUCTION_PREFLIGHT_STATUS=PASS — production preflight and smoke both pass on run 38996511120
BROWSER_WORKFLOW_STATUS=PASS — [run 38995936964](https://github.com/esonhaya/boardprep/actions/runs/38995936964), exact application SHA 614aa36990c4ae792e2a0fd7f2fb257f4aa3f4fd, API-verified and registered in docs/release-evidence/browser-workflows.json. Browser summary: 30 total, 26 passed, 0 failed, 4 project-conditional skips, 0 flaky, 0 not run. Artifact 12087291650 (130,508,281 bytes) remains hosted. These are automated assertions; no manual visual acceptance is claimed.

RELEASE_GATES_WORKFLOW_CONCLUSION=FAIL only because the formal Product Readiness job correctly remains blocking; the independent Doctor, security/privacy, ten-board HTTP, and production preflight/smoke gates pass.
PRODUCT_READINESS=NOT_READY — exact run reports product_feature_complete=no, product_launch_ready=no, privacy_terms=no, 2 incomplete in-scope features, and 4 mandatory evidence items not passed.
HUMAN_SIGNOFF_STATUS=PENDING — the 37 educational edits and 85 duplicate clusters remain unapproved and unchanged; no human reviewer or academic approval was fabricated.

## Git integration and remaining work

GIT_INTEGRATION=Application commit 614aa36990c4ae792e2a0fd7f2fb257f4aa3f4fd and evidence commit b994c2e98edb55f58f396533aa55da8489bafcad were pushed to main by ordinary fast-forward. No force push, reset, cleanup, or blanket staging was used.
PUSH_STATUS=PASS — the application and evidence commits are present on origin/main. The final report is committed and pushed as a documentation-only descendant before handoff.
CHANGED_QUESTION_IDS=NONE
UNRESOLVED_BLOCKERS=Owner-approved privacy/terms configuration; four mandatory production/acceptance evidence items; two incomplete product features; human MVP acceptance; pending academic signoff; non-blocking source-traceability REVIEW and Doctor P2/P3 debt.
NEXT_SAFE_BATCH=Complete owner-controlled privacy/terms and production evidence with authorized operators, perform the required human MVP and academic reviews, then reassess readiness without changing question identities or pending decisions automatically.
CLIPBOARD_STATUS=UNVERIFIED — both stdin and argument forms of termux-clipboard-set returned without error, but termux-clipboard-get returned zero bytes. The complete report is saved at /data/data/com.termux/files/home/BoardPrep/docs/batches/batch-688.md; open that file and copy its contents manually as the fallback.
