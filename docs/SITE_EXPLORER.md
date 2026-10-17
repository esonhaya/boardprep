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
