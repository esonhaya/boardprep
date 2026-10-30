# Batch 727 recovery checkpoint

## Verified implementation checkpoint

- Branch: `batch-727-smart-study-performance`
- Implementation/tested HEAD: `702e399a3b059edac530d63e2ca7f1a3a7253074`
- Parent B726 final: `6590d6d6b419c6c0e85a0554e2d6b94582c61764`
- Implementation and all application changes are committed; Full Doctor and focused verification are complete. Final reports are ready for their separate documentation commit.
- The three protected untracked storage files remain unstaged and unchanged.

## Completed work

- Dashboard raw-history normalization is no longer repeated at callers that already have ordered history; readiness reuses full-scope dashboard summaries and still recomputes mixed-board scope independently.
- Added the Smart Study Hub, deriving at most three actions from existing readiness and mistake history, validating local destinations, preserving board/variant context, and showing a useful no-history state.
- Registered-topic study links remain exact. Unregistered legacy/synthetic topic recommendations now return the no-link fallback before building a full canonical question-topic index.
- Added response-equivalence, Smart Study action, HTTP journey, and mobile static-contract coverage. Added new B727 tests to the Full Doctor learner-core flow and the existing Notes practice test to study-experience coverage.

## Verification

- Dashboard equivalence passed for 0, 1, 25, 100 and 500 synthetic attempts; mixed-board readiness retained scoped results.
- Isolated Notes, mistake recovery and Flashcard regressions passed.
- Q6403 quarantine and Q1536/Q1537 source-only tests passed.
- Browser contract suite: 8/8 passed. Real Chromium remains unexecuted on Termux.
- Full Doctor on implementation SHA `702e399a3b059edac530d63e2ca7f1a3a7253074`: exit 0, valid JSON, release PASS, P0=0, P1=0, P2=1, P3=2; 78/78 release flow tests passed.
- Doctor artifact: `batch-727-full-doctor.json`, SHA-256 `46dd8c3a8c6d28dc1a48da08c8d0eb9595ff8bd703272e46e74c061aaaa843ba`.
- Minimum Doctor-observed free space: 2,571,911,168 bytes; calculated required space: 1,837,243,096 bytes; fixture cleanup PASS.
- Protected canonical, learner and academic hashes were recomputed after Doctor and match the supplied baseline. Q6403 and Q1536/Q1537 restriction tests pass.
- The three browser contracts and eight browser/client contract tests pass, but actual Chromium is unavailable on this Android/Termux runner.
- Twelve registered worktrees and two stashes remain registered/preserved; no tmux server/session is running. A wake-lock request previously returned exit 0; this is not a guarantee against Android suspension.

## Performance

Same synthetic HTTP workload, seven warm requests each, before/after B727. Warm medians (ms): 0 attempts `177.710 → 158.697`; 1 `1033.102 → 168.292`; 25 `1070.860 → 203.400`; 100 `1163.781 → 259.055`; 500 `1823.449 → 592.220`. See `batch-727-dashboard-performance.json`; memory and aggregate storage/bank read counts were not instrumented.

## Final delivery actions

Commit the concise B727 reports separately, then prepare the exact final delivery report and attempt clipboard readback. The documentation commit's own SHA is reported in the delivery output and omitted from its self-committed report. Do not start B728.
