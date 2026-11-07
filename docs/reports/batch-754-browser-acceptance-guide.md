# Batch 754 browser acceptance guide

## Current infrastructure

BoardPrep already has a rendered Chromium suite and a dedicated GitHub Actions workflow. The suite uses a committed-source fixture (`git archive HEAD`), isolated temporary JSON stores and PHP session directories, and loopback-only PHP servers. The browser workflow is `.github/workflows/browser-acceptance.yml`; it has read-only repository permission, pinned actions, no deployment permission, and no production secrets. Hosted execution was not started in this batch.

The pinned test driver is Playwright `1.63.0`. The workflow uses Ubuntu `24.04`, PHP `8.3`, and Node `22.23.3`, installs Chromium, runs browser/security regressions, records exact-SHA evidence, and uploads diagnostics with a 14-day retention period.

## Supported Linux execution

Use a disposable Linux checkout with PHP 8.3 and the extensions used by the workflow (`json`, `session`, `pdo_sqlite`, and `sodium`). Node `22.23.3` and the committed `package-lock.json` are the workflow versions. From the repository root:

```sh
npm ci
npm run test:browser:contract
npm run test:browser:fixture
npx playwright install --with-deps chromium
node tools/browser-preflight.cjs
npm run test:browser
```

Set `BOARDPREP_BROWSER_EVIDENCE_DIR` to a new private directory under the runner's temporary root to retain the execution report and artifacts. The runner refuses to certify missing, skipped, flaky, stale-SHA, dirty-checkout, or failed browser evidence. Use the GitHub Actions workflow for the pinned end-to-end environment. Do not set production credentials or point the fixture at production storage.

## Browser journey matrix

| Area | Existing rendered coverage |
| --- | --- |
| Board discovery, practice setup, answers, review, completion, results, history, responsive controls | `tests/browser/acceptance/boardprep.spec.cjs` |
| Login, logout, account ownership, PHP session loss, exam recovery, export/restore, deletion | `tests/browser/accounts/account-auth.spec.cjs` |
| Result question reporting and reporter isolation | `tests/browser/acceptance/question-reports.spec.cjs` |
| Mistake review and retry | `tests/browser/acceptance/mistake-notebook.spec.cjs` |
| Study calendar generation and completion | `tests/browser/acceptance/study-calendar.spec.cjs` |
| Authenticated, isolated reviewer pilot access | `tests/browser/acceptance/academic-review-pilot.spec.cjs` |
| Flashcard and supporting learner surfaces | `tests/browser/acceptance/boardprep.spec.cjs`, `tests/browser/acceptance/notes-based-practice.spec.cjs` |

The mobile project checks `320x568`, `360x800`, `390x844`, `430x932`, and `768x1024` in its journey contracts. The desktop project uses `1366x768`. Account journeys are run only in the mobile project; server-side tests cover the account transitions separately. Playwright retries remain disabled, and skipped required tests make the acceptance manifest incomplete.

## Artifact privacy

The browser server writes its account-storage/session-path handoff to a mode-`0600` sidecar next to, but outside, the uploadable evidence directory. The sidecar is removed when the server exits and by the test runner after Playwright returns. The workflow excludes the legacy in-directory filename and `*.private.json` files as a second boundary. The credential-bearing account and reviewer specs disable trace persistence so password, recovery-code, and backup-passphrase form bodies are not stored in failure traces. Other browser journeys keep failure screenshots and traces enabled.

Evidence directories are created with owner-only permissions where the host supports Unix modes. CI uses only synthetic accounts and disposable storage. No runtime secrets are configured in the browser workflow. Static browser contracts and PHP HTTP simulators are not real-browser acceptance.

## Termux limitation observed in Batch 754

This run used Android/arm64 Termux with Node `v26.4.0`. The repository's pinned Playwright package is not installed in this Termux checkout, and the preflight found no system browser executable. The preflight classified driver compatibility and browser launch as `BLOCKED`; no browser binary was installed. Therefore real rendered-browser acceptance was **not executed**. Run the Linux workflow only after the owner separately authorizes hosted CI, or run the same pinned commands on an already available supported Linux host.
