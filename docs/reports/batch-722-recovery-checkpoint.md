# Batch 722 recovery checkpoint

## Verified position

- Current branch: `batch-722-performance-experience-hardening`
- Current application base: `ea2b1ca48a50de34cdaf957e8980f71a3713d720` (B721 final documentation tip)
- B721 release evidence: Full Doctor PASS, P0=0, P1=0, tested at `4a05da3c1da64f74ca170aca583b168ae0b19743`
- B722 implementation is in progress. No B723 branch was created.
- Historical B723 prerequisite report remains outside the repository at `/data/data/com.termux/files/usr/tmp/batch-723-prerequisite-recovery-report.md` and is not being treated as B722 work.

## Completed work

- Profiled the canonical CSE Professional count path and removed 40 repeated blueprint collection reads during eligibility-index construction. The result now uses one blueprint snapshot.
- Reworked subject pool counting to validate/count without materializing normalized question copies and to map subject aliases to IDs once. Counts remain identical.
- Reused normalized, sorted learner history and computed topic/subject/progress summaries across dashboard and readiness construction.
- Reused the bounded adaptive weak-topic index and one exposure lookup per candidate while preserving established selection output.
- Added focused equivalence/read-count tests and reproducible synthetic benchmark scripts.
- Profile evidence is recorded in `batch-722-cold-path-profile.json`.

## Files changed at checkpoint

- `app/Services/Learning/LearningHistoryService.php`
- `app/Services/Learning/LearningProgressService.php`
- `app/Services/Learning/LearningReadinessService.php`
- `app/Services/Learning/StudyDashboardService.php`
- `app/Services/Learning/StudyInsightService.php`
- `app/Services/Learning/StudyRecommendationService.php`
- `app/Services/Learning/SubjectPerformanceService.php`
- `app/Services/Learning/TopicPerformanceService.php`
- `app/Services/Question/QuestionEligibilityService.php`
- `app/Services/Question/QuestionReuseEligibilityResolver.php`
- `app/Services/Quiz/Adaptive/AdaptiveQuestionSelectionService.php`
- `app/Services/Quiz/Selection/QuestionPoolFilter.php`
- `tools/Benchmarks/batch722-count-by-subject-profile.php`
- `tools/Benchmarks/batch722-adaptive-ranking-profile.php`
- `tools/Tests/Batch722AdaptiveRankingEquivalenceTest.php`
- `tools/Tests/Batch722EligibilityBlueprintReadReuseTest.php`
- `tools/Tests/Batch722LearningAnalyticsReuseTest.php`
- `tools/Tests/Batch722QuestionPoolCountEquivalenceTest.php`
- `docs/reports/batch-722-cold-path-profile.json`
- `docs/reports/batch-722-recovery-checkpoint.md`

The three pre-existing untracked storage JSON files are intentionally not included in this list or staged.

## Tests and measured results

- PASS: `Batch722QuestionPoolCountEquivalenceTest.php`
- PASS: `Batch722EligibilityBlueprintReadReuseTest.php`
- PASS: `Batch722LearningAnalyticsReuseTest.php`
- PASS: `Batch722AdaptiveRankingEquivalenceTest.php`
- PASS: `QuestionPoolFilterTest.php`, `StudyDashboardAggregateTest.php`, `StudyDashboardEmptyStateTest.php`, `StudyDashboardActionabilityTest.php`, `StudyDashboardWebTest.php`, `StudyDashboardProductionTest.php`, `LearningReadinessMobileContractTest.php`, `Batch721TenBoardAdaptiveEligibilityTest.php`, and `Batch610ConceptDerivedEligibilityTest.php`
- PASS: `AdaptivePracticeSelectionTest.php`; ten-board pool and selection invariants remain intact.
- `git diff --check`: PASS at the previous check; rerun before the implementation commit.
- Benchmark: dashboard cold/warm median 2,527.340/219.152 ms before and 1,691.940/119.003 ms after; readiness 1,562.342/88.380 ms before and 1,152.505/67.120 ms after. Same synthetic fixture and methodology; OS cache not cleared.
- Benchmark: count-path blueprint reads 41→1, question-bank reads remain 1, exact subject counts preserved.
- Benchmark: adaptive ranking warm medians improved from 14.002/95.053/415.657 ms to 4.976/22.422/118.254 ms at 150/1,200/5,000 rows; ordered selected IDs match exactly.
- Available storage at checkpoint: approximately 2.7 GiB. No production learner or canonical mutation was made by the benchmark wrappers.

## Remaining work

- Run a broader examination lifecycle, notes, mistake recovery, flashcard, authentication/CSRF, ownership, Developer access, import-gate, and quarantine regression subset.
- Inspect remaining learner/mobile interface and browser workflow evidence; execute no unsupported browser installation.
- Review all changes, lint touched files, record protected hashes, and commit verified implementation.
- Run Full Doctor only after focused verification passes and free space meets reserve requirements; capture exact tested SHA and artifact digest.
- Produce final Batch 722 reports and separate documentation commit, then attempt clipboard write/readback verification.

## Recovery instructions

Continue on the current branch and preserve these source changes. Never stage or overwrite the three pre-existing untracked storage JSON files. Do not create or start Batch 723 in this session.

## 2026-10-28 21:53 Asia/Manila checkpoint

- `CURRENT_HEAD=1f8b663aaa27662b03ff06cfca9cb7545a613ac1`; branch remains `batch-722-performance-experience-hardening`.
- Implementation commits: `91db44b167f6921aac473596c7bf0b6bdbda064d` (measured cold-path/adaptive improvements) and `1f8b663aaa27662b03ff06cfca9cb7545a613ac1` (exam save-state and lost-response retry feedback).
- The isolated `ExamSimulationHttpTest.php` passed after adding initial/recorded answer counts, identical stale retry confirmation, conflicting stale retry protection, and refresh reconciliation. `QuizSessionResilienceTest.php`, browser workflow contracts (6/6), syntax checks, and `git diff --check` passed.
- Broader B711–B721 regression coverage and all-ten-board synthetic learner journeys passed as recorded above. Two historical snapshot-pin failures remain documented: B653 archived semantic hash and B704 archived PDF manifest. They were not changed.
- Protected integrity map matched the B721 baseline byte-for-byte before final Doctor; raw canonical, eligibility, source, study-link, attempt, weakness, statistics, academic-review and flashcard-exclusion hashes matched. The three pre-existing untracked storage JSON files remain unchanged.
- Available storage last observed: 2,703,808 KiB (about 2.58 GiB); available memory was 848 MiB. No other memory-heavy test process was active.
- Full Doctor completed against the exact tested code SHA `1f8b663aaa27662b03ff06cfca9cb7545a613ac1`: exit 0, valid JSON, release gate PASS, P0=0/P1=0/P2=1/P3=2, health REVIEW with 835 signals and zero fail entities. Artifact: `/data/data/com.termux/files/usr/tmp/batch722-full-doctor-1f8b663.json`, 2,353,216 bytes, SHA-256 `2f9a4bccbd9b04644a31461afbf1ab2df1554ef3a9d324faec76426e6d69122b`.
- Doctor storage status and cleanup passed: required 1,837,243,096 bytes; minimum observed free 2,590,318,592 bytes; the minimum exceeded the required reserve plus 512 MiB. Four pre-existing fixture directories and four pre-existing session directories were left untouched. Current free storage after Doctor was about 2.72 GiB.
- Recomputed the protected map after Doctor. It is byte-identical to `/data/data/com.termux/files/usr/tmp/batch721-recovery-20261028/integrity-before.json`; the recomputed map SHA-256 is `58877738fde75cb17e9238fd1d3b608acdff190423bee6a9a3a04777156687b2`. Canonical count 9,123, protected hashes unchanged, and the three pre-existing untracked JSON files remain at SHA-256 `4f53cda18c2baa0c0354bb5f9a3ecbe5ed12ab4d8e11ba873c2f11161202b945` each.
- All required reports were committed in documentation commit `ac3fe37708aeca60362ebe1ce929c41c573b9ab9`. One clipboard attempt followed: `termux-clipboard-set` exited 0; `termux-clipboard-get` exited 0 but returned 0 bytes, so full-content verification failed. No retry was made. The final overnight report now records `CLIPBOARD_STATUS=UNVERIFIED` and the reason.
- Next action: commit this small documentation correction, verify the final worktree and protected untracked files, and print the complete final report because clipboard readback was empty. Do not start Batch 723.
