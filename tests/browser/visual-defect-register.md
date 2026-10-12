# Batch 667 visual defect register

## Screenshot review

Chromium rendered and uploaded 85 screenshots in workflow run [38193706406](https://github.com/esonhaya/boardprep/actions/runs/38193706406). Manual visual review covered the mobile home, navigation, board detail, practice setup, selected/feedback answer, long question, results/review, progress, history, study resource, flashcard, privacy/export/deletion, and error states, plus desktop home, dashboard, history, progress, answer review, structured table/figure, and privacy states. The screenshots show a consistent, readable shell and usable layouts at the inspected sizes; automated viewport checks are not a substitute for physical Android, screen-reader, or human acceptance.

## Findings

| ID | Screen | Viewport | Screenshot evidence | Severity | Observed defect | User impact | Repair / status |
| --- | --- | --- | --- | --- | --- | --- | --- |
| B667-VIS-001 | Progress report | Mobile 390×844 | Run 381901 baseline; final `screenshots/mobile/progress-empty-390x844.png` | P2 | The “Practice now” action had only the base `.button` class and rendered as unboxed text. | The primary next action had weak button affordance. | Fixed in `33b2de1`; visually confirmed in run 381937. |
| B667-VIS-002 | Quiz history | Mobile 390×844 | Run 381901 baseline; final `screenshots/mobile/history-empty-390x844.png` | P2 | The header “Start practice” action rendered as unboxed text. | The action was easy to mistake for ordinary text and inconsistent with the empty-state CTA. | Fixed in `33b2de1`; visually confirmed in run 381937. |
| B667-VIS-003 | Production error page | Mobile 390×844 and desktop 1366×768 | Before: run 381937 `screenshots/mobile/error-state-390x844.png` and `screenshots/desktop/error-state-1366x768.png` | P2 | Unknown routes rendered a raw, unstyled “Error 404 / Something went wrong” document without BoardPrep navigation or recovery actions. | Learners could not easily recover or continue using the product. | Fixed in this follow-up using the shared learner layout and recovery links; final Chromium screenshot confirmation pending. |
| B667-QA-001 | Browser acceptance harness | CI Chromium | Run 381901 failure output | P2 | Stale timer copy and `git show` question-bank loading blocked browser flow setup. | Acceptance and evidence capture did not complete. | Fixed in `33b2de1`; final suite passed in run 381937. |
| B667-QA-002 | Structured-question fixtures | Mobile and desktop Chromium | Run 381917 failure output | P2 | Random five-question draws did not guarantee fixed IDs; the test attempted Next after the last question. | Structured viewport captures failed. | Fixed with eligible topic fixtures, supported counts, and finish-state guards; final suite passed in run 381937. |
| B667-QA-003 | Privacy export diagnostics | Mobile and desktop Chromium | Run 381917 failure output | P2 | Chromium's expected download navigation abort was classified as a failed request. | Valid export caused false browser failures. | Only the exact `/privacy/export` download abort is allowed; downloaded JSON is still validated. Final suite passed in run 381937. |
| B667-QA-004 | Browser report artifacts | GitHub Actions | Run 381917 artifact | P2 | Reporter outputs were absent at collector paths. | Structured results were missing from uploaded evidence. | Reporter paths and collection fixed; run 381937 artifact includes browser JSON, HTML report, and diagnostics. |

## Review limits

No additional P0/P1 defects were observed in the screenshots reviewed. This is not a claim that every screenshot or all browser states were visually inspected. Chromium does not establish physical Android touch behavior, device-specific rendering, screen-reader support, WCAG conformance, owner acceptance, or live hosting behavior.
