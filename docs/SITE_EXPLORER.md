# BoardPrep bounded QA explorer

The site explorer follows safe, stateful learner journeys against the isolated loopback Chromium fixture used by browser CI. It does not read or mutate owner acceptance reports, production learner records, external websites, or production routes. The fixture exports the tested commit with `git archive`, copies canonical storage into temporary test storage, and listens on `127.0.0.1` only.

## Run it

On supported desktop/CI Chromium:

```sh
npm run test:explore:smoke
npm run test:explore:full
```

For a repeatable seed:

```sh
node tools/run-site-explorer.cjs --mode=full --seed=691
```

Android Termux does not install Chromium or launch the explorer locally. Use GitHub Actions → **Bounded site explorer** → choose `smoke` or `full` and enter a seed. Pushes run the bounded full journey; pull requests run smoke; manual dispatch uses the selected mode. The explorer may enter the safe nonproduction `/developer` page, but it never follows `/developer/acceptance`, whose private feedback belongs to the owner.

The local report defaults to a private operating-system temporary directory. CI uploads the sanitized JSON/Markdown report and failure screenshots/DOM snapshots as a short-retention artifact.

## What it checks

Smoke explores two seeded boards. Full mode traverses all ten boards. Both modes start real practice sessions in the isolated fixture, submit learner-selected answers, inspect effective question eligibility through a loopback-only development diagnostic, and visit study, history, progress, profile, privacy, flashcard, and deliberate invalid-state routes. Full mode adds adaptive practice, a second attempt, exam timer/reload recovery, and flashcard navigation when a review card is available.

The run is bounded by a deterministic seed, one browser worker, an explicit same-origin route allowlist, page/action/time limits, and duplicate-route detection. Default budgets are 60 pages, 180 actions, and three minutes for smoke; full mode uses 100 pages, 320 actions, and nine minutes. The report marks a run `INCOMPLETE` if a configured limit is exceeded. Destructive controls and nonlocal navigation are not followed. The report records routes and forms visited, state transitions, board/subject combinations, question IDs and source references, viewport, screenshots for confirmed defects, console/network errors, and paths that were not tested.

## Read the report

- `PASS`: the bounded automated journey completed with no confirmed defects or review cues.
- `PASS_WITH_REVIEW_CUES`: the bounded automated journey completed without a confirmed deterministic defect. Review cues and untested paths still require attention.
- `FAIL`: a reproducible automated defect was confirmed. Re-run with the same seed and inspect the per-attempt report, route, reproduction note, screenshot, DOM snapshot, and diagnostic details.
- `INCOMPLETE`: the run stopped on an error or before completing its budget. This is not a pass.
- `REVIEW` cues (for example repeated distractor text, long option shape, source-semantic questions, and possible accessibility concerns) are not automatic academic decisions.

Automated evidence does not produce `HUMAN_PASS`. It cannot prove that every educational item is semantically correct, replace a real Android/screen-reader session, approve privacy terms, or provide academic sign-off.

## Batch 691 hosted run

Run `39057409388` tested commit `d40c04e4a3d4c18763ee41692f9414a3b07c1d47` with seed `691`: `PASS`, 100 visited routes, 290 recorded actions, all 10 boards, 10 board/subject combinations, and 50/50 independently checked question deliveries. It reported no confirmed defects and no heuristic review cues. The artifact is `boardprep-site-explorer-d40c04e4a3d4c18763ee41692f9414a3b07c1d47-39057409388` (artifact 12110077464).

The report also lists 27 unique internal paths outside the safe allowlist, mainly PDFs, private data export, and developer/editor routes; `/flashcards/missed` is an additional learner route not yet covered. The explorer records those paths as untested rather than probing them. The report is not a claim of exhaustive route coverage.

The exact-commit run `39060380608` tested `fa082ab138a5289b2622a5904e1d47353c24adbe` with seed `691`: `PASS`, 100 routes, 290 actions, 21 state transitions, 10 forms, all 10 boards, 10 board/subject combinations, and 50/50 independently checked question deliveries. It reported zero confirmed defects, heuristic review cues, console/page errors, failed internal requests, or unexpected HTTP errors. Its 59 untested-path entries represent 27 unique out-of-allowlist paths, chiefly learning PDFs, private data export, and developer/editor routes. Artifact `boardprep-site-explorer-fa082ab138a5289b2622a5904e1d47353c24adbe-39060380608` (ID `12110399126`) contains the sanitized JSON and Markdown report. This bounded traversal is not exhaustive route or semantic-content certification.

## Nonproduction account authentication journeys

The account suite uses a third isolated loopback server with its own temporary
account JSON store and PHP session directory. The fixture starts with empty
attempt, result, progress, and flashcard state. It never reads the owner's
private Acceptance Center feedback or learner records. The account feature flag
is enabled only for this `APP_ENV=testing` server; the separate production
fixture must return 404 for account routes.

On supported desktop/CI Chromium, run:

```sh
npm run test:explore:auth:smoke
npm run test:explore:auth:full
```

Smoke covers the five mobile-sized login viewports, anonymous access,
production-route exclusion, CSRF rejection, registration, duplicate-name
response, login/session rotation, and logout. Full adds persistent preferences
across independent sessions, two-account isolation, password-confirmed export,
recovery-code consumption/replay, password change and stale-session rejection,
deletion-request lockout, and rate limiting. The suite has a one-worker route
and page budget and follows only explicitly scripted actions on its two
loopback origins. Export content is checked in memory and its temporary
download is deleted.

Sanitized reports are written outside the repository under the selected
`BOARDPREP_EXPLORER_EVIDENCE_DIR` (or the operating-system temporary directory).
GitHub Actions runs the full account journey alongside the bounded site
explorer and uploads only the redacted report and masked failure screenshot.
These tests verify a local foundation; they do not authorize production
accounts, prove provider email delivery, complete account deletion, or replace
independent security review.

### Batch 692 exact-commit result

Run `39070597000` tested `07711e628b492d8ef47e6e11257f6ac3d13480f2` with seed
`691`. Its final bounded site report is `PASS`: all ten boards and ten
board/subject combinations were visited, 290 actions were recorded, 21 state
transitions and 10 forms were exercised, and the report had no final confirmed
defects, review cues, browser errors, failed internal requests, or unexpected
HTTP errors. Attempt 1 stopped at a study-library navigation after
`net::ERR_ABORTED` on `app-shell.js`; its failed attempt report and screenshot
are retained in the workflow artifact. The full rerun completed with the same
seed and all ten boards and did not reproduce the request cancellation. This
remains a transient, unverified navigation-cancellation cue, not a closed
application defect.

The same workflow ran the account suite on that exact commit: `PASS`, 23
scenarios and 45 main-frame navigations, with no confirmed defects. It covered
five mobile-sized login viewports, production-route exclusion, registration,
enumeration-safe duplicate response, wrong/correct password, session/CSRF
rotation, saved profile retrieval in a second context, account export, logout,
two-account profile isolation, recovery and replay rejection, password-change
session invalidation, deletion-request lockout, and login rate limiting. Idle
and absolute expiration are separately verified by `AccountSecurityTest.php`;
the browser journey does not wait 30 minutes to simulate idle expiry.

Run artifact: `boardprep-site-explorer-07711e628b492d8ef47e6e11257f6ac3d13480f2-39070597000`
(ID `12115550385`). Reports and failure screenshots contain no real learner
records or credential values.
