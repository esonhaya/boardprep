# Batch 726 Overnight Recovery Checkpoint

CHECKPOINT_BASE=da989c9bc69cff2145c36ec0707e6e05c78e1e5b
BRANCH=batch-726-exam-review-study-continuity
WORKTREE=/data/data/com.termux/files/home/boardprep-batch-706
B726_APPLICATION_IMPLEMENTATION=9680965c8c6fa191489f1210e3d87f87f2d7507f

## Storage recovery

Eight stale, B726-compatible simulator fixtures were removed from `/data/data/com.termux/files/usr/tmp` after confirming that their simulator PIDs were inactive, roots were not symlinks, and all five mutable learner collections were empty. The directories were reproducible copies created by `SimulationStorageFixture`; their `doctor-manual-run.txt` files matched the retained repository copy. Together they reclaimed 420,457,472 bytes. Five newer simulator fixtures, all four pre-existing release-session fixtures, standalone Doctor artifacts, worktrees and stashes were preserved. The protected three untracked JSON files remain byte-identical.

Removed paths and measured allocated bytes:

- `boardprep-simulator-20816-da7717efcdb411ef` — 52,552,704
- `boardprep-simulator-20818-dfebabc70a3ee0c5` — 52,552,704
- `boardprep-simulator-26904-b70a2e3deb823349` — 52,559,872
- `boardprep-simulator-27563-531d5c822aabb4b5` — 52,556,800
- `boardprep-simulator-27652-f10fa8c7e6a97ddd` — 52,560,896
- `boardprep-simulator-939-c454d928dc376454` — 52,556,800
- `boardprep-simulator-20028-654f78e5b56dbadb` — 52,560,896
- `boardprep-simulator-13825-4ca8798a9689ba31` — 52,556,800

## Full Doctor finding and repair

The first recovery Full Doctor artifact is preserved at `/data/data/com.termux/files/usr/tmp/batch-726-full-doctor-recovery-20261030-043252.json` (exit 1; SHA-256 `3de535f67b03231f262523ab4be19e6379a8970abf7780ff33b8f1de8437f976`). It reported P0=1, P1=0, P2=1, P3=2. The sole P0 was the simulator-recovery group because `ExamSimulationHttpTest.php` ran under `APP_ENV=testing`, so its invalid-CSRF request was accepted and cleared a flag. Production CSRF behavior itself was not defective; the dedicated production test and the release test's intended assertion require production mode.

The test now scopes `APP_ENV=production` to the invalid-token and foreign-session requests, restores the caller's environment in `finally`, and checks for each forbidden foreign-session key independently. The exact test passed in the release runner's isolated storage fixture (exit 0; session cleanup passed). This was a test-environment correction, not an application-source change.

Free storage was 2,659,934,208 bytes before the first Doctor. Doctor's measured minimum was 2,394,030,080 bytes, above its reserve-inclusive 2,374,114,008-byte threshold. Doctor cleaned its owned release fixture; no production learner files changed.

## Completed and remaining

- B726 review flags, status navigator, submission summary, result filters and dashboard same-session continuation remain committed and unchanged.
- Prior B726 learner, security, mobile accessibility and browser-contract tests remain as reported in the main report.
- Real browser remains `PREPARED_NOT_EXECUTED` on Android/Termux.
- Full Doctor must be rerun after the test correction. Then complete the remaining returning-learner continuity and performance review, run affected tests, and rerun Full Doctor if application code changes.
- Do not start B727 until final Full Doctor is valid PASS with P0=0/P1=0 and integrity checks match.
