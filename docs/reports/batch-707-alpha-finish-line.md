# BOARDPREP — BATCH 707

## Final product decision

The core learner journey is implemented and passed isolated production-HTTP acceptance. No P0 or P1 application defects remain from this bounded review. Two concrete issues were fixed: release readiness was spending thousands of subprocesses walking Git history, and the Nursing lesson review disclosure appeared after the instruction.

This is **RESTRICTED_INTERNAL_DEMO_ONLY**. Public release is **NOT APPROVED**. The current browser run is stale for this code, 12 human acceptance cases remain unrun, site-specific privacy terms are not owner-approved, and Nursing academic review remains outstanding. The app still serves published Nursing lessons; the new notice is a disclosure, not an approval or an access-control substitute.

The single area-by-area completion matrix is [batch-707-release-scorecard.json](batch-707-release-scorecard.json). Exact command, exit-code, SHA, and reuse records are in [batch-707-validation-evidence.json](batch-707-validation-evidence.json).

## Baseline

```text
B706_FINAL_SHA=8682b37363ef4ea2f3d820b7522f404edace0ad1
HEAD_BEFORE=8682b37363ef4ea2f3d820b7522f404edace0ad1
BRANCH=batch-707-alpha-finish-line
WORKTREE=/data/data/com.termux/files/home/boardprep-batch-706 (reused in place)
RECOVERY_STATUS=B706 tip verified as a descendant of 43c3c461495c58e574267888145b93eeeba48a39; B706 worktree was clean; no B707 work existed. The B707 branch was created without copying the repository.
STORAGE_AVAILABLE=305233920 bytes at recovery; 292208640 bytes after validation
```

The dirty B695 checkout was not used. Existing branches, worktrees, and both stashes remain. The B706 branch still points to its original final SHA.

## Product completion

The reusable `TenBoardLearnerJourneyHttpTest` covers board and subject choice, practice, answer submission, expected score, rationale, result actions, history, progress, retry, and stale-action recovery for all ten boards. B706's `GuidedLearningJourneyHttpTest` separately covered exact study practice, correct and incorrect feedback, supporting-section return, and anonymous history isolation. B707 freshly retested all ten study-library routes and the Nursing lesson page in production HTTP simulation. These are HTTP results, not current browser acceptance.

The learner routes, scoring, and session paths show no known P0/P1 code blocker. Developer and Doctor routes remain absent in production; quiz and preference mutations require CSRF. The available browser suite is already comprehensive, but it has not been executed against this code revision.

## Defects and fixes

1. `BrowserEvidenceService` launched one `git diff-tree` process for every first-parent descendant of every registered workflow run. The register has 26 records spanning 53–159 commits each, or 3,010 descendant checks. Two initial readiness invocations were stopped after they remained active for over two minutes. The service now gets first-parent paths in one `git log` call per evidence record, with first-parent merge diffs retained. Regression coverage checks documentation-only descendants, application edits, reverted edits, merge-introduced edits, shallow history, and mismatches. The final readiness command completed in about seven seconds.
2. All 13 published Nursing lessons have source-check dates and zero independent academic approvals, but the non-approval disclosure was after the lesson text. It now appears beside the lesson title before instruction: source checks do not represent independent human academic or clinical approval. A production HTTP regression verifies ordering. No lesson, source, question, eligibility, answer key, or approval status was changed.

No P0 defect or additional P1 code defect was found. Optional cross-device accounts, official exam-format fidelity, production authoring tools, and advanced personalization remain deferred; none is needed to exercise the core anonymous learner journey.

## Release engineering and safety boundaries

Local free space is 292208640 bytes. The unchanged Doctor preflight requirement is 1837116727 bytes, leaving a 1544908087-byte shortfall. No cleanup was performed and Full Doctor was not started. The existing `.github/workflows/release-gates.yml` runs the unmodified Full Doctor on Ubuntu with PHP 8.3 and a 1 GiB PHP memory limit. The existing `.github/workflows/browser-acceptance.yml` installs the pinned browser on Ubuntu and runs desktop/mobile acceptance. Both workflows support manual dispatch; neither was run for B707 because this branch was not pushed.

The Termux browser probe reports Android/arm64, no installed Playwright package or system browser, and working loopback. Browser acceptance is therefore `BLOCKED` locally and `PREPARED_NOT_EXECUTED` externally. No browser installation was attempted.

Current product readiness reports zero open P0–P3 findings, but returns exit 1 because product completion and launch readiness are false. Mandatory evidence still missing includes current real-browser behavior, all 12 human acceptance cases, owner-approved privacy/operator/retention terms, and live provider/HTTPS/session/storage configuration. The last registered successful browser run (39114896020, SHA `f7674ef97988cb91a5d90154c0f4f574241ac214`) is explicitly stale for this code.

Nursing currently has 47 exact mappings, all independently review-pending, and zero academic approvals. Q1536/Q1537 remain quarantined from exact lesson practice; Q1018 remains pending its independent review. The application does not have an approval-based Nursing publication filter. The new notice does not substitute for professional review. Do not expose the Nursing set publicly until authorized academic review and the product's release gates are satisfied; deployment was not attempted.

## Final report fields

```text
=== BASELINE ===
B706_FINAL_SHA=8682b37363ef4ea2f3d820b7522f404edace0ad1
HEAD_BEFORE=8682b37363ef4ea2f3d820b7522f404edace0ad1
BRANCH=batch-707-alpha-finish-line
WORKTREE=/data/data/com.termux/files/home/boardprep-batch-706
STORAGE_AVAILABLE=305233920 bytes at recovery; 292208640 bytes at final validation
RECOVERY_STATUS=verified clean B706 checkout reused in place

=== PRODUCT COMPLETION ===
CORE_JOURNEY_STATUS=PASS in isolated production HTTP; current real browser acceptance BLOCKED
HOME=PASS (ten-board journey)
BOARD_SELECTION=PASS (ten-board journey)
SUBJECT_SELECTION=PASS (ten-board journey and scoped study HTTP)
QUIZ_START=PASS (production quiz CSRF HTTP)
ANSWER_SELECTION=PASS (ten-board and guided learning HTTP)
SUBMISSION=PASS (correct/incorrect guided HTTP and production CSRF HTTP)
SCORING=PASS (ten-board expected 1/1 results)
RESULT_REVIEW=PASS (rationale and exact-support navigation)
PROGRESS=PASS (isolated learner HTTP)
HISTORY=PASS (attempt history and anonymous isolation)
STATE_RECOVERY=PASS (retry and stale-action HTTP; session resilience regression)
ADMIN_OPERABILITY=Developer tools remain non-production; no production authoring UI is required for this alpha

=== DEFECTS ===
P0_FOUND=0
P0_FIXED=0
P0_REMAINING=0 identified in the bounded application review
P1_FOUND=2
P1_FIXED=2
P1_REMAINING=0 identified in code; browser, privacy-owner, and academic gates remain external
P2_DEFERRED=Cross-device account recovery, official exam-format fidelity, production authoring, optional personalization/statistics
ROOT_CAUSES=3,010 per-commit Git subprocesses in readiness ancestry checks; Nursing academic disclosure rendered after lesson content

=== UI ===
MOBILE_USABILITY=Structural and HTTP checks PASS; visual browser behavior BLOCKED
NAVIGATION=PASS in production HTTP; all ten study-library scopes freshly tested
QUESTION_PRESENTATION=PASS structural labels/choice contracts; visual browser review remains BLOCKED
EMPTY_STATES=PASS focused empty-state contracts
ERROR_STATES=PASS invalid/stale quiz recovery contracts
ACCESSIBILITY=Structural view checks PASS (18 assertions); TalkBack/VoiceOver and WCAG conformance NOT_RUN
ACTUAL_BROWSER_VALIDATION=BLOCKED on Termux; external workflow prepared, not executed

=== RELEASE ENGINEERING ===
LOCAL_STORAGE_STATUS=292208640 bytes free; Full Doctor requires 1837116727 bytes; 1544908087-byte shortfall
CI_WORKFLOW=Existing browser-acceptance.yml and release-gates.yml; no duplicate workflow added
SUPPORTED_HOST_PREPARED=YES (Ubuntu runner; pinned PHP/Node/browser and evidence artifacts)
EXTERNAL_BROWSER_STATUS=PREPARED_NOT_EXECUTED
FULL_DOCTOR_STATUS=BLOCKED locally; external workflow not executed
DEPLOYMENT_STATUS=NOT_DEPLOYED; no deployment authorization used

=== CONTENT SAFETY ===
NURSING_EXACT_MAPPINGS=47
ACADEMIC_APPROVALS=0
UNREVIEWED_CONTENT_HANDLING=Prominent pre-instruction notice; not a clinical approval or server-side publication gate
CANONICAL_DATA_PRESERVED=YES
CONTENT_VISIBILITY_GATE=REVIEW_REQUIRED; do not publicly expose Nursing instruction before independent review

=== SECURITY ===
SESSION_HANDLING=PASS focused strict-session and quiz-state tests
AUTHORIZATION=PASS production developer/Doctor routes unavailable
CSRF=PASS quiz and preference mutations
OUTPUT_ESCAPING=PASS focused trust-boundary regression
LEARNER_ISOLATION=PASS isolated anonymous history evidence; no primary learner state changed
UNRESOLVED_SECURITY_FINDINGS=No P0/P1 code findings identified; deployment privacy terms and provider settings remain unverified

=== VALIDATION ===
FOCUSED_TESTS=PASS BrowserWorkflowEvidenceTest; StudyLibraryNavigationTest; ProductionQuizCsrfTest; SecurityTrustBoundaryTest; LearnerMobileAccessibilityViewTest; LearnerNavigationUxTest; QuizSessionResilienceTest; SessionConfigurationTest; ProductReadinessTest
CORE_HTTP_JOURNEY=PASS isolated current/reused HTTP evidence; no browser claim
TEN_BOARD_REGRESSION=PASS study-library all ten boards; learner journey reused with unchanged boundaries
FULL_DOCTOR=BLOCKED by storage; not run
BROWSER_TESTS=BLOCKED locally; external workflow contract PASS, browser suite not executed
TESTED_SHA=9ec6aa078f01f544d7731a62102a57baad245399 (current implementation); 2dfbe25a003870d4db846add0d8f80f90ed5128a also records the release-evidence optimization test
FAILED_OR_BLOCKED_CHECKS=Product readiness exit 1 as designed for release blockers; browser capability probe exit 2 BLOCKED; Full Doctor not run. Initial test-fixture assertion/setup errors were corrected and the final regressions passed.

=== DELIVERY ===
COMMITS=2dfbe25a003870d4db846add0d8f80f90ed5128a; 9ec6aa078f01f544d7731a62102a57baad245399; report-only commit is a descendant of 9ec6aa0
HEAD_AFTER=9ec6aa078f01f544d7731a62102a57baad245399 (last implementation/tested SHA; report commit is documentation-only)
WORKTREE_STATE=CLEAN after delivery commit
PROTECTED_WORK_PRESERVED=YES; B695 hashes, branches, worktrees, both stashes, canonical questions, attempts, statistics, flashcards, and HayaDoctor pin preserved
PUSH_STATUS=NOT_PUSHED
REPORT_PATH=/data/data/com.termux/files/home/boardprep-batch-706/docs/reports/batch-707-alpha-finish-line.md
CLIPBOARD_STATUS=UNVERIFIED (setter/getter exited 0; getter returned 0 bytes; readback SHA-256 e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855)

=== FINAL DECISION ===
ENGINEERING_ALPHA_STATUS=RESTRICTED_INTERNAL_DEMO_ONLY; core HTTP journey is functional, current real browser acceptance is absent
PUBLIC_RELEASE_STATUS=NOT_APPROVED
ACTUAL_CODE_BLOCKERS=None identified among P0/P1 essentials after two targeted fixes
EXTERNAL_VALIDATION_BLOCKERS=Current-SHA desktop/mobile browser acceptance, Full Doctor on a capable host, live provider configuration
ACADEMIC_REVIEW_BLOCKERS=12 human acceptance cases; Nursing content/mapping review; owner-approved privacy terms
DEFERRED_NONESSENTIAL_WORK=Cross-device accounts, official-format fidelity, production authoring, optional personalization/statistics, cosmetic changes
SMALLEST_NEXT_RELEASE_ACTION=Make the committed SHA available to an authorized GitHub runner and manually run the existing browser-acceptance and release-gates workflows; then complete the owner privacy configuration and required human reviews before any learner-facing release.
```

## Delivery

Implementation commits are `2dfbe25a003870d4db846add0d8f80f90ed5128a` and `9ec6aa078f01f544d7731a62102a57baad245399`. Reports are committed separately. No push, merge, tag, or deployment was performed.
