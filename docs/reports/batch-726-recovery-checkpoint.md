# Batch 726 Overnight Recovery Checkpoint

CHECKPOINT_BASE=0c72750fd72c033bd472ab1de654e0a8762208f8
BRANCH=batch-726-exam-review-study-continuity
WORKTREE=/data/data/com.termux/files/home/boardprep-batch-706
B726_APPLICATION_IMPLEMENTATION=9680965c8c6fa191489f1210e3d87f87f2d7507f

## Storage recovery

Thirteen stale simulator fixtures were removed from `/data/data/com.termux/files/usr/tmp` after confirming inactive simulator PIDs, current-user ownership, no symlinks, and empty attempts, weakness, statistics, flashcard and notes arrays. Their `doctor-manual-run.txt` files matched the retained repository copy. These reproducible fixtures reclaimed 683,277,312 allocated bytes. All four pre-existing release-session fixtures, original Doctor artifacts, worktrees, stashes and HayaDoctor were preserved.

The first eight removed paths and measured allocated bytes:

- `boardprep-simulator-20816-da7717efcdb411ef` — 52,552,704
- `boardprep-simulator-20818-dfebabc70a3ee0c5` — 52,552,704
- `boardprep-simulator-26904-b70a2e3deb823349` — 52,559,872
- `boardprep-simulator-27563-531d5c822aabb4b5` — 52,556,800
- `boardprep-simulator-27652-f10fa8c7e6a97ddd` — 52,560,896
- `boardprep-simulator-939-c454d928dc376454` — 52,556,800
- `boardprep-simulator-20028-654f78e5b56dbadb` — 52,560,896
- `boardprep-simulator-13825-4ca8798a9689ba31` — 52,556,800

The remaining five removed paths:

- `boardprep-simulator-14325-7fd853d4e9514618` — 52,560,896
- `boardprep-simulator-14757-40daf12117f8d8d0` — 52,563,968
- `boardprep-simulator-8604-f02c56755a2e4bfa` — 52,577,280
- `boardprep-simulator-20410-7faa3a0f14157fc3` — 52,560,896
- `boardprep-simulator-23369-d9825dcd103f83fa` — 52,556,800

## Full Doctor recovery

The first recovery Full Doctor artifact remains preserved at `/data/data/com.termux/files/usr/tmp/batch-726-full-doctor-recovery-20261030-043252.json` (exit 1; SHA-256 `3de535f67b03231f262523ab4be19e6379a8970abf7780ff33b8f1de8437f976`). It found one P0 because `ExamSimulationHttpTest.php` ran under `APP_ENV=testing`, allowing its invalid-token request to clear a mark. Production CSRF itself was not defective.

The focused test now sets `APP_ENV=production` only around invalid-token and foreign-session requests, restores the caller's setting in `finally`, and checks each forbidden foreign-session field separately. It passed through the same isolated release runner (exit 0, session cleanup PASS).

Full Doctor was rerun on tested HEAD `0c72750fd72c033bd472ab1de654e0a8762208f8`; application source remains implementation `9680965c8c6fa191489f1210e3d87f87f2d7507f`. The valid JSON artifact is `/data/data/com.termux/files/usr/tmp/batch-726-full-doctor-recovery-20261030-050521.json` (2,354,297 bytes; SHA-256 `dec656ef322eb93521536acd0b63c7e4aabcd28db59597aff1cbbcf43d13cda5`). Result: exit 0, release PASS, P0=0, P1=0, P2=1, P3=2, health REVIEW with zero repository-health failures. Doctor's measured minimum available storage was 2,562,662,400 bytes; its reserve-inclusive threshold is 2,374,114,008 bytes. Release fixture/session cleanup passed. `DoctorSimulatorStateIsolationTest` verified all 115 repository storage files and learner sessions were unchanged.

That run correctly left the pre-existing `/data/data/com.termux/files/usr/tmp/boardprep-release-test-storage-ffc8feb239d3` fixture untouched. Its Oct 30 05:02 timestamp and location matched my preceding isolated focused-test runner; all learner collections were empty. I removed only that exact owned fixture using `ReleaseReadinessService::removeFlowTestStorage` and verified it was gone. It reclaimed 92,792,832 allocated bytes. Free space afterward was 2,918,002,688 bytes.

## Integrity and remaining work

- Canonical count remains 9,123. The three protected untracked JSON files remain byte-identical; no production learner mutation occurred. The 276 pending proposals remain noncanonical; no approvals/imports occurred.
- The B726 review flags, navigator, pre-submit summary and result filters remain unchanged. The production-CSRF test context fix is committed; no application source changed through tested HEAD `0c72750…`.
- Real Chromium remains unavailable in Android/Termux; browser contracts are not real-browser acceptance.
- Finish the partial returning-learner dashboard continuity and capture comparable B726-path performance measurements. Test changes in isolation, then rerun Full Doctor because application code will have changed.
- Historical B653/B704/B705 snapshot mismatches remain as previously classified; do not rewrite archived evidence.
- Do not start B727 until final Full Doctor and protected hash checks pass on the final application revision.
