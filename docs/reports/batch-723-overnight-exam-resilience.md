# BoardPrep Batch 723 — Overnight Recovery

## Outcome

**B723 is complete.** The recovered branch is batch-723-exam-resilience-continuity. Its original implementation remains at e41c8480061ee1582886503d64d21aa60ffd200f; the verified release-gate repairs are committed at 246756b2cee0ed614e095e5a289435f9f713b727.

B722 was independently confirmed at final documentation commit 5b088f1db029481108a28c78a3ccbf15248acfe8; its stored Full Doctor result was valid JSON, exit 0, release PASS, P0=0 and P1=0. Recovery began at the existing B723 documentation tip a58f62ab471909b95fd83b62ca4bbeaa262fee45.

## Release verification and repairs

The first Full Doctor was preserved at /data/data/com.termux/files/usr/tmp/batch-723-full-doctor-a58f62ab471909b95fd83b62ca4bbeaa262fee45.json (valid JSON, exit 1, SHA-256 36b3bfc4e460c5bdb36945c31801dd4ae89dd7300db784d4e3de505441bc1956). It identified three P0 release domains:

- The simulator posted an invalid placeholder instead of a rendered answer choice. The scenario now submits a choice from its own quiz form; DoctorSimulatorStateIsolationTest passed and verified all 115 repository storage files and learner sessions were unchanged.
- The storage safety detector still matched the old temporary-file variable. It now checks exclusive locking, a same-directory temporary file, a complete write, atomic rename, and validated storage paths. JsonStorageFaultInjectionTest is part of release determinism checks.
- A weakness/statistics test left a nested private account lock directory in its own temporary fixture. Its cleanup now recursively removes only that uniquely created fixture and verifies the root is gone.

The affected contract, cleanup and fault-injection tests passed. A second Full Doctor on 246756b2cee0ed614e095e5a289435f9f713b727 returned valid JSON, exit 0, release PASS, P0=0, P1=0, P2=1, P3=2. Repository health remains REVIEW with 835 disclosed review/warning signals and zero fail entities; this is not academic approval or public-release approval.

The final artifact is /data/data/com.termux/files/usr/tmp/batch-723-full-doctor-246756b.json, 2,354,065 bytes, SHA-256 81e905d338dbbfcdf9f99afd83340846af3c77fb95e197a62721553f59670afe. Fixture cleanup passed. Doctor required 1,837,243,096 bytes; the separately tracked 512 MiB reserve made the pre-run threshold 2,374,114,008 bytes. Minimum observed free space was 2,448,261,120 bytes, above that threshold by 74,147,112 bytes.

## Storage recovery

Recovery began with 2,395,373,568 bytes free, below the Doctor threshold. Four old boardprep-release-test-storage-* directories were identified as isolated, reproducible fixtures created by the repository's release-test helper. Their learner collections were empty, no processes held them open, and the helper resets learner data before tests. Those exact stale fixtures were removed; three older copies initially reclaimed 81,727,488 observed free bytes. The free-space sample after the final focused checks was 2,698,092,544 bytes. The final Doctor began with 2,711,638,016 bytes available and exceeded the prior 256 MiB headroom target.

The four older boardprep-release-sessions-* directories reported by Doctor were left intact as historical test evidence. A 50 MiB isolated fixture left by the intentionally failing B704 historical test was inspected, confirmed to have empty learner collections, and removed. The application storage, Git objects, other projects, registered worktrees, stashes, HayaDoctor and the dirty historical B695 checkout were not cleaned or overwritten.

## Historical snapshot tests

The historical tests and their source evidence were not edited.

- **B653:** The test fails on current storage at its old semantic question-bank hash assertion. Its archived artifact is unchanged (SHA-256 d31d767d362a0e506efc5dcfd654833cd000c31d54106be04bf22e85bda605ff). The original parent snapshot has 8,947 questions and the exact recorded pre-production hash; the B653 commit has 8,950 questions and the exact recorded post-review hash. Current storage has 9,123 questions. This is a snapshot comparison, not a current canonical-integrity failure.
- **B704:** The archived baseline file is byte-identical to its original commit. Its original snapshot checks all 56 prior PDF assets without mismatches. The current test fails on the CPA audit PDF because a later source-metadata export commit, da896f545984c0cf25425006a3163f72036c78cd, changed its hash from c39e97e79ab092463230562acfbb0ccde96c3f372cc082e108819b087c25bb18 to 17fabea8ebc0a5436e861363fa00bbf7705336d3ac41c24b5a00f36b92f82f8c. The current file matches its current manifest.
- **B705:** Its archived baseline is byte-identical to the original commit; the original B705 manifest changes exactly the five PDFs the test expects. The current test finds 12 later-regenerated PDFs: those five plus one CPA and six LET PDFs refreshed by the later source-metadata export commit. All 59 current PDFs have searchable text; 227 ordered section headings and 368 source attributions were checked. The current StudyPdfPipelineTest passes.

These three failures are explicitly classified as historical snapshot expectations. Current integrity checks pass; no expected historical hashes were replaced.

## Integrity, learners and release limits

The canonical bank remains 9,123 questions. Question bytes, answer keys, eligibility, source registry, study links, attempts, weakness, question statistics, academic decisions, source reviews and flashcard exclusions match the protected B722 hashes. The three pre-existing untracked JSON files remain byte-identical at SHA-256 4f53cda18c2baa0c0354bb5f9a3ecbe5ed12ab4d8e11ba873c2f11161202b945.

All 276 B695 proposals remain pending qualified academic review. No academic approvals, imports, production learner mutations, push, merge or deployment occurred. Q6403 remains excluded; Q1536/Q1537 remain source-only. Existing session, attempt, Notes, Flashcard and learner-isolation tests passed on the original implementation; repair-specific tests and Full Doctor passed on the final implementation commit.

Browser status remains PREPARED_NOT_EXECUTED: Android/Termux had no supported Linux Chromium runner. No screenshots or real-browser acceptance are claimed. No offline examination mode was introduced; answer persistence requires connectivity.

## Delivery state

Implementation commits: e41c8480061ee1582886503d64d21aa60ffd200f, 246756b2cee0ed614e095e5a289435f9f713b727. Final verification and recovery reports are committed separately. B724 was not started.
