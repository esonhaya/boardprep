# Batch 728 Recovery Checkpoint

Recorded on 2026-10-31 (Asia/Manila). The session-wide start time was not recorded; elapsed time is not inferred. The interrupted Full Doctor run lasted 26 minutes 18 seconds.

## Verified state

- Worktree: `/data/data/com.termux/files/home/boardprep-batch-706`
- Branch: `batch-728-browser-acceptance-study-handoff`
- Recovery started at `0dbd46d5d2d1e971a0a5b8e446468bb7295bbd66`; tested implementation is `525b00858b72ab50b184a45038d3a808586e3ab9`.
- B727 final ancestor: `2ddb5d5c0c692e94478c1e20ae638469fe7bf07f`; B727 tested SHA: `702e399a3b059edac530d63e2ca7f1a3a7253074`.
- `npm run test:browser:contract`, focused exam/history/study/security regressions, PHP/JS syntax, `git diff --check`, production preflight, production smoke, and B717's 276-proposal academic-gate regression passed.
- Real Chromium was unavailable on Termux ARM64. `REAL_BROWSER=NOT_EXECUTED`; the existing Ubuntu 24.04 workflow remains the reproducible runner. No workflow was dispatched.
- The B728 Full Doctor command was stopped after free space crossed the reserve target. Its zero-byte JSON artifact is preserved as a failed attempt; it is not a valid Doctor report and does not pass the release gate.
- Current source preflight requires 1,837,243,096 bytes at launch. The interrupted run started with 2,976,616,448 bytes and observed a 1,286,111,232-byte drawdown before reaching 1,690,505,216 bytes. Preserving the 1.5 GiB floor plus 128 MiB runtime reserve against that observed drawdown calls for at least 3,030,941,696 bytes before retry; 3,299,377,152 bytes is the recommended retry threshold with 256 MiB additional headroom. The latest free-space sample, at `2026-10-30T16:07:08Z`, was 2,416,304,128 bytes—about 40 MiB above the historical threshold and below the conservative Doctor retry requirement.
- Eleven stale, UID-owned temporary test fixtures were inspected against their creator/cleanup code and process references, then removed. They were under Termux's temporary directory, mode 0700, with no symlinks/special files/open references. No repository storage, history, git objects, caches, dependencies, unknown temporary trees, or user files were removed. Two historical Batch 684 temporary trees were retained because their ownership/purpose was not sufficiently established and they contain recovery/session snapshots.
- The three protected untracked JSON files still have SHA-256 `4f53cda18c2baa0c0354bb5f9a3ecbe5ed12ab4d8e11ba873c2f11161202b945`; they were not staged or edited.
- Existing dirty `/data/data/com.termux/files/home/BoardPrep`, HayaDoctor, 12 registered worktrees, and two stashes were preserved.
- `termux-wake-lock` was available and returned exit 0. `tmux` was available without an existing session; work continued in this Codex session.

## Recovery actions

1. Wait for free space to recover naturally or remove only another positively identified, unused BoardPrep-owned disposable fixture. Do not touch the retained Batch 684 trees or unknown app data.
2. Before retrying, record `df -k` and RAM, confirm no test/Doctor process is active, and require at least 3,299,377,152 free bytes for a 256 MiB margin over the observed Doctor drawdown envelope.
3. Rerun the exact production-mode `tools/doctor.php --release --json` command at implementation SHA `525b00858b72ab50b184a45038d3a808586e3ab9`, writing to a new unique JSON artifact and sampling free space. Require valid complete JSON, exit 0, RELEASE_GATE=PASS, P0=0, P1=0, and no unresolved security/integrity failure.
4. Obtain authorization for the configured Ubuntu 24.04 / Node 22.23.3 browser workflow, then run `npm run test:browser` there and preserve the workflow artifacts. Do not dispatch it from this session.
5. Keep academic review and public-release approval separate. Do not start B729 until mandatory local technical gates are complete.

## Recovery completion — B728 technical gate passed

Recorded `2026-10-30T21:45:01Z` (UTC). The successful Doctor run supersedes recovery actions 1–3 above; the browser and human-review actions remain separate gates.

- Branch/worktree remain `batch-728-browser-acceptance-study-handoff` at `/data/data/com.termux/files/home/boardprep-batch-706`.
- Full Doctor ran at tested HEAD `880c06231f601ee82937ff21319984be130c18e7`. Application files are identical to implementation SHA `525b00858b72ab50b184a45038d3a808586e3ab9`; intervening commits changed documentation only.
- Run: `2026-10-30T21:14:37Z`–`2026-10-30T21:42:57Z`, 28m20s; exit 0; complete JSON; `RELEASE_GATE=PASS`; P0=0, P1=0, P2=1, P3=2. Health is REVIEW with 0 failing entities. Twenty-five release domains passed; source traceability remains REVIEW. Security and integrity domains passed.
- Artifact: `docs/reports/batch-728-full-doctor-recovery-20261030T211437Z.json`, 2,367,143 bytes, SHA-256 `df032739f03de4136e39bac69335d4e68f522d049da82d297c3d044dee25381d`.
- Doctor observed 3,402,850,304 bytes before the run and a minimum 3,132,829,696 bytes. It recorded 92,412,632 fixture bytes, 167,624,704 largest additional per-test storage, and 270,020,608 total observed drawdown. The drawdown-based requirement including the 1.5 GiB floor and 128 MiB runtime reserve was 2,014,851,072 bytes. Final measured free space was 3,389,382,656 bytes.
- Flow fixture cleanup and per-test session cleanup both passed. No release or simulator fixture/session directories remained after completion. The 3 protected untracked storage files retain their expected hashes; all canonical and protected data hashes match. B695 remains 276 pending, with no approvals or imports.
- The earlier zero-byte Doctor artifacts remain preserved as failed attempts; the complete passing artifact is stored separately. Wake lock was reacquired for the run and remains active for the current recovery session.
- B728 local technical gate is PASS. `REAL_BROWSER=NOT_EXECUTED`; browser acceptance remains open. Academic approval and public release remain deferred. B729 becomes eligible only after this B728 report/evidence checkpoint is committed and the clean gate is rechecked.
