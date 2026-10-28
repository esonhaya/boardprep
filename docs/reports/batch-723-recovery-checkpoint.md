# Batch 723 Recovery Checkpoint

## Baseline and current revision

- Worktree: `/data/data/com.termux/files/home/boardprep-batch-706`
- Branch: `batch-723-exam-resilience-continuity`
- `HEAD_BEFORE`: B722 final documentation `5b088f1db029481108a28c78a3ccbf15248acfe8`.
- B722 implementation: `1f8b663aaa27662b03ff06cfca9cb7545a613ac1`.
- B722 Full Doctor evidence: `/data/data/com.termux/files/usr/tmp/batch722-full-doctor-1f8b663.json`; valid JSON, exit 0, release `PASS`, P0=0, P1=0, P2=1, P3=2, no failing health entities; artifact SHA-256 `2f9a4bccbd9b04644a31461afbf1ab2df1554ef3a9d324faec76426e6d69122b`.
- `HEAD_AFTER_IMPLEMENTATION` / latest tested SHA: `e41c8480061ee1582886503d64d21aa60ffd200f`.
- Implementation commit: `e41c8480061ee1582886503d64d21aa60ffd200f`; reports are not yet committed.
- Execution began 2026-10-29 01:19:45 +08:00. Latest checkpoint: 2026-10-29 02:46 +08:00.

## Completed implementation

- Exam writes bind to rendered question IDs. Stale navigation does not skip questions; delayed answer writes can only save their own unanswered active-exam item; existing answers win conflicts. Concurrent answer/navigation and two conflicting answer-save requests preserve one answer and advance once.
- Exam fault-injection tests verify refresh/resume, authoritative counts, expiration, lost-response retries, duplicate/concurrent finalization and persisted attempt/statistics state.
- JSON collection writes reject incomplete temporary writes and failed replacement while preserving the prior collection. Malformed JSON fails closed. The isolated test injects pre-write, short-write, replacement and malformed-state failures and concurrent creates.
- Weakness and question-statistics read/modify/write operations are serialized. Notes have idempotent create request IDs and edit revisions; Flashcards have idempotent review IDs and expected counts. Eight-process tests confirm no duplicate create/revision/schedule transition or lost aggregate increments.
- Registered legacy REE lesson subjects resolve only to an exact same-board canonical subject. Result links, filtered library, dashboard availability and exact-section practice are covered.
- Browser specs include 320, 360, 390 and 430 px. No CSS redesign or offline answer store was added.

## Verification on `e41c8480061ee1582886503d64d21aa60ffd200f`

- PASS: `JsonStorageFaultInjectionTest`, `LearnerWriteConcurrencyTest`, `ExamSimulationHttpTest`, `Batch711NotesBasedPracticeTest`, `TenBoardLearnerJourneyHttpTest`, `MultiAttemptLearningHttpTest`, `Batch712MistakeRecoveryTest`, `StudyLibraryNavigationTest`, `FlashcardHttpTest`, `FlashcardSchedulingTest`, `LearnerMobileAccessibilityViewTest` (18 assertions), `ProductionQuizCsrfTest`, `QuestionImportCsrfTest`, `SecurityTrustBoundaryTest`, `AnonymousLearnerPrivacyHttpTest`, `QuizSessionResilienceTest`, `Batch706CriticalMappingSafetyTest` (53 assertions), `Batch717AcademicGateClosureTest`, browser workflow contract suite (6/6).
- Additional implementation checks pass for `Batch696ContentIntegrityTest`, `FlashcardIntegrationTest`, `CrossBoardCanonicalReuseTest`, `Batch721TenBoardAdaptiveEligibilityTest`, `QuizExperienceResilienceTest`, `FlashcardControllerReadPathTest`, `Batch708Q6403QuarantineTest`, `MultiExamEligibilityTest`, and `StudyPdfPipelineTest`. Those ran before the final Notes-create and concurrent-answer test additions; the final affected behavior was retested at the exact SHA above.
- PASS: all changed PHP syntax checks, changed browser-spec `node --check`, and `git diff --check`.
- Historical-only mismatches were not rewritten: Batch653 CSE semantic hash is pinned to an older question snapshot; Batch704 PDF/source manifest is pinned to an older snapshot; Batch705 expected regenerated-PDF set predates later artifacts. Current canonical integrity, Batch696, and Study PDF pipeline checks pass.
- Browser status: `PREPARED_NOT_EXECUTED`; no supported Linux Chromium runner was available, and no remote CI was dispatched.
- Flashcard controller sample: 2,598.3 / 1,890.6 / 1,932.9 ms; median 1,932.9 ms; one canonical bank read per request; peak 217,579,520 bytes. No comparable B723 before sample, so no speedup is claimed. B722 dashboard/readiness gains are historical and preserved.

## Integrity and remaining gate

- Canonical count 9,123; protected questions, answer-key, eligibility, source, study-link, attempt, weakness, question-statistics, academic-decision, source-review and flashcard-exclusion hashes match B722 baseline.
- `storage/academic-proposal-workflow.json`, `storage/flashcard_state.json`, and `storage/question-statistics.json` remain two bytes each with SHA-256 `4f53cda18c2baa0c0354bb5f9a3ecbe5ed12ab4d8e11ba873c2f11161202b945`.
- B695 proposals remain 276 pending. No approvals/imports or production learner writes occurred. Q6403 remains excluded; Q1536/Q1537 remain source-only.
- Twelve registered worktrees, two stashes, HayaDoctor and the dirty historical checkout remain preserved.
- Latest free storage: 2,350,317,568 bytes; required Doctor storage plus 512 MiB reserve: 2,374,114,008 bytes; shortfall 23,796,440 bytes. Full Doctor was not run for B723; release result and P0/P1 counts are unmeasured.
- Next step: once available storage meets the threshold, run Full Doctor against exact SHA `e41c8480061ee1582886503d64d21aa60ffd200f`, retain/validate complete JSON, and recheck hashes. Then commit these reports and deliver the complete report. If the reserve stays unmet, retain `PARTIAL_RECOVERABLE`; do not claim release verification or start B724.
