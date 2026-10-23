# BoardPrep Batch 707 — External Release Handoff

## Candidate

- Branch: `batch-707-alpha-finish-line`
- Tested application/content SHA: `963606542af3984a1ba260e2f5b55a0b0b1c5232`
- Worktree: `/data/data/com.termux/files/home/boardprep-batch-706`
- The follow-up report commit changes documentation only. After it is committed, record `git rev-parse HEAD` and the Actions run `GITHUB_SHA` together.
- Publication and workflow dispatch have not occurred. No push or deployment is authorized by this handoff.

## Current decision

`QUESTION_STRUCTURE_READY=YES`; `QUESTION_CONTENT_REVIEW_REQUIRED=YES`; `ENGINEERING_ALPHA=BLOCKED_PENDING_HOSTED_BROWSER_AND_FULL_DOCTOR`; `PUBLIC_RELEASE=NOT_APPROVED`.

Q6403 remains held from new Civil Service question selection while its key/stem contradiction awaits an authorized item-owner decision. There are zero independent academic approvals. A reachable URL does not establish that its claim is supported.

## Hosted workflow requirements

The repository already has both required manually dispatchable workflows:

1. `.github/workflows/browser-acceptance.yml` — Ubuntu 24.04; PHP 8.3 (`json`, `session`, `pdo_sqlite`, `sodium`); Node 22.23.3; `npm ci`; installs Chromium with dependencies; runs focused privacy/session checks, isolated fixtures, browser preflight and real browser acceptance. It uploads bounded artifacts for 14 days.
2. `.github/workflows/release-gates.yml` — Ubuntu; PHP 8.3 and configured extensions; Full Doctor with `memory_limit=1G`, complete Git history and recursive submodules; security/privacy matrix; ten-board HTTP journey; production preflight and smoke. Each job records the commit SHA and exit status and uploads diagnostic evidence.

The source tree and workflow contracts were inspected. `BrowserWorkflowEvidenceTest.php` passed. The local browser contract runner had four passing cases and one server-fixture setup failure caused by `No space left on device`; this is not browser acceptance. No hosted run has been executed.

Doctor's current read-only storage estimate is `1,837,228,798` bytes, including a `92,398,334`-byte fixture, `1.5 GiB` safety floor and `128 MiB` runtime reserve. Termux had `45,150,208` bytes available on the Doctor temp path at final measurement, so Full Doctor was not started locally. Do not lower this preflight.

## User-run procedure

The local branch is ahead of `origin/batch-707-alpha-finish-line`; no changes were pushed. If you authorize publishing this branch, the branch-scoped command is:

```sh
git push origin batch-707-alpha-finish-line
```

Then in GitHub Actions, choose the pushed `batch-707-alpha-finish-line` ref and manually run **Browser acceptance** and **Release gates** separately. Confirm each run's `GITHUB_SHA` equals the intended branch tip. Retain the run URL and downloaded artifacts; distinguish each job's result and exit code. Do not treat workflow file presence, local HTML assertions, or a successful workflow dispatch as acceptance.

## Required disposition after hosted runs

- Review Full Doctor's actual release findings and storage preflight output.
- Review browser screenshots/logs at desktop and narrow mobile widths; confirm navigation, question selection, submission, score/review, stale/empty recovery, and console/runtime errors.
- Obtain the authorized Q6403 owner decision without rewriting historical scores.
- Obtain genuine qualified review for unresolved question/source claims before learner-facing educational release.
- Keep restricted or inconclusive URL outcomes distinct from confirmed dead links; no reachability result is academic approval.

Until these actions produce evidence, external acceptance is pending and public release remains blocked. No additional question or lesson expansion is needed to perform these gates.
