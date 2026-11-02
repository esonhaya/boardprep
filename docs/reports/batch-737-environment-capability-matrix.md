# Batch 737 environment capability matrix

## Baseline and preservation

- Requested baseline: `2159d1e398eeee4fef69f6a6a327d32a1086cd1a` on `batch-736-release-readiness-acceptance-hardening`.
- Verified `HEAD` and created `batch-737-independent-acceptance-evidence` in the existing worktree at `/data/data/com.termux/files/home/boardprep-batch-706`.
- Tracked worktree was clean. Existing untracked reports and storage were preserved.
- Preserved 12 registered worktrees and two stashes.
- The Batch 736 protected manifest verified `15/15`; Batch 737's pre-run copy and check are `batch-737-protected-before-20261102T000742Z.sha256` and `.check.txt`.
- Free space before the batch's checks was `2,477,788 KiB` (`df -Pk .`, about `2.36 GiB`).

## Capability matrix

| Capability | Available | Authorized | Finding |
|---|---|---|---|
| Supported browser host | No | No | The only command host is Android/arm64 Termux (`Linux 4.19.152-perf+`). There is no separate supported Linux host exposed to this session. |
| Local browser executable | No | N/A | `chromium`, `chromium-browser`, `google-chrome`, and `firefox` are absent from `PATH`. Browser preflight reports Android/arm64 unsupported and exits `2` with status `BLOCKED`. |
| Browser dependencies | Partial | Local checks only | Node is `v26.4.0`, npm is `12.0.2`; the repository pins Node `22.23.3` and Playwright `1.63.0`. The lockfile package is present, but the Playwright runner cannot load a compatible driver on Android. |
| Sodium-enabled PHP host | No | No | The local PHP is `8.5.1`, `pdo_sqlite` is available, and `sodium` is absent. The runbook requires PHP `8.3` with sodium primitives and `pdo_sqlite`. No alternate host is exposed. |
| Local virtualization/container host | No | N/A | `docker`, `podman`, `nerdctl`, `qemu-system-x86_64`, and `proot-distro` are absent from `PATH`. |
| GitHub Actions browser host | Workflow configured only | Dispatch not authorized | `.github/workflows/browser-acceptance.yml` defines Ubuntu 24.04, PHP 8.3, Node 22.23.3, pinned npm dependencies, and a bounded browser job. Its `workflow_dispatch` trigger is configuration, not permission to run it. It was not dispatched. |
| Disposable hosted configuration target | Not identified | No | No disposable host or credentials were provided. Production access is prohibited. |

## Acceptance execution decisions

- `BROWSER_HOST_AVAILABLE=NO`
- `BROWSER_HOST_AUTHORIZED=NO`
- `SODIUM_HOST_AVAILABLE=NO`
- `SODIUM_HOST_AUTHORIZED=NO`
- `HOSTED_CONFIGURATION_ACCESS_AUTHORIZED=NO`
- `REAL_BROWSER_ACCEPTANCE=NOT_EXECUTED_ENVIRONMENT_BLOCKED`
- `ENCRYPTED_BACKUP_ROUNDTRIP=NOT_EXECUTED_ENVIRONMENT_BLOCKED`
- `HOSTED_CONFIGURATION_ACCEPTANCE=NOT_EXECUTED_AUTHORIZATION_OR_ENVIRONMENT_BLOCKED`

The browser preflight artifact is `batch-737-browser-preflight-20261102T000526Z.stdout.txt`; its JSON identifies commit `2159d1e398eeee4fef69f6a6a327d32a1086cd1a`, no browser candidates, Android/arm64 platform incompatibility, and final status `BLOCKED`. The runner was not installed or invoked against an unsupported browser platform.

The sodium prerequisite probe is recorded in `batch-737-sodium-preflight-20261102T000742Z.*`. It exits `2` because this runtime is PHP 8.5 rather than the supported PHP 8.3; independent runtime inspection confirms sodium is absent and `pdo_sqlite` is present. Backup tests below exercise the application's fail-closed path only; that result is not a backup/restore acceptance pass.

The committed browser workflow registry was evaluated locally against the baseline commit using `BrowserEvidenceService`. Its latest eligible historical entry was run `39114896020`, tested commit `f7674ef97988cb91a5d90154c0f4f574241ac214`, and status `STALE`. It is not evidence for Batch 737. No remote API call or workflow dispatch was made.

## Supported-host procedure

Use the exact Ubuntu 24.04/PHP 8.3/Node 22.23.3 procedure in [the Batch 736 independent acceptance runbook](batch-736-independent-acceptance-runbook.md), after checking out the exact Batch 737 tested commit. That runbook already defines synthetic fixtures, bounded timeouts, the exact browser and sodium commands, output collection, and cleanup. Do not treat its commands, this capability check, browser contract tests, or fail-closed crypto tests as acceptance execution.

## Evidence sources

- `node tools/browser-preflight.cjs`: Batch 737 JSON/stdout, stderr, exit code, and metadata under `docs/reports/batch-737-browser-preflight-20261102T000526Z.*`.
- PHP sodium prerequisites: stdout, stderr, exit code, and metadata under `docs/reports/batch-737-sodium-preflight-20261102T000742Z.*`.
- Baseline protected hashes: `docs/reports/batch-737-protected-before-20261102T000742Z.sha256` and `.check.txt`.
- Workflow availability and pinning: `.github/workflows/browser-acceptance.yml` and `package-lock.json`.
- Historical workflow provenance: `docs/release-evidence/browser-workflows.json`, validated by `app/Services/Operations/BrowserEvidenceService.php`.
- Current local Doctor provenance remains separately validated by `app/Services/Developer/FullDoctorEvidenceService.php`.
