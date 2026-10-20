# BoardPrep human tester readiness

This is the single MVP acceptance checklist. It has separate automated and
human outcome fields. Playwright, HTTP tests, screenshots, and static checks do
not set a human outcome. MVP-01 through MVP-12 remain `HUMAN_STATUS=NOT_RUN`
until a tester records what they observed.

Before public launch, the operator must also complete and approve
[`OWNER_PRIVACY_AND_HOSTING_DECISIONS.md`](OWNER_PRIVACY_AND_HOSTING_DECISIONS.md).

## Outcome vocabulary

- `AUTOMATED_PASS`: named automated checks completed successfully for the
  recorded build SHA. It does not pass a human case.
- `HUMAN_PASS`: a tester completed the case and observed its pass conditions.
- `HUMAN_FAIL`: a tester reproduced a failure and recorded evidence.
- `BLOCKED`: the named automated or human activity could not run; include the
  environment or access reason.
- `NOT_RUN`: no result has been recorded.
- `NOT_APPLICABLE`: the case precondition is absent; explain why. It is not a
  pass and does not close a release requirement.

Keep `AUTOMATED_STATUS` and `HUMAN_STATUS` separate. `HUMAN_ACCEPTANCE=NOT_RUN`
while any required human case is `NOT_RUN`, `HUMAN_FAIL`, `BLOCKED`, or
`NOT_APPLICABLE`.

## Prepare a test session

For the automated prerequisite, check the latest GitHub Release Gates run and
its Doctor result for the exact build SHA under test. Batch 698's local
candidate source checkpoint `6550ef1cc39d4d7d13d44f0b413442bb36df7a07` passed
the Full Doctor release; its complete report is
[`batch-698-doctor-release.json`](reports/batch-698-doctor-release.json). The
local simulator also passed its storage-isolation check. Neither result is a
human acceptance result. The Acceptance Center launcher creates its own fresh
isolated learner fixture. Use a phone in portrait orientation for the mobile
cases.

The Acceptance Center records the committed build SHA, UTC date, environment,
browser, detected operating system, and current viewport. Enter a tester alias
and device model if you want them in the report. Check the detected details
before sharing; add any other viewport sizes you tried to a test note. Do not
include learner names, session cookies, real exported records, or credentials
in screenshots or artifacts.

## Launch the guided Android test

On the phone that has the repository, run:

```sh
./tools/acceptance/start.sh
```

Open the printed `OPEN_THIS_URL` in Android Chrome. The command serves the
exact committed build in a production-mode learner preview, then opens the
separate nonproduction Acceptance Center. Both listeners use `127.0.0.1` and
isolated temporary learner storage. The Center opens real learner pages in a
second tab; keep its tab open to follow the current instructions and record
what you observed. Use the **Continue / return to this test guide** control
when you switch back.

Comments and session reports stay outside the repository in
`$PREFIX/share/boardprep/acceptance-center` (Termux),
`$XDG_STATE_HOME/boardprep/acceptance-center`, or
`~/.local/state/boardprep/acceptance-center`. The owner-only local directory
persists when the preview stops. The page remembers the browser owner for 30
days; choose **Start a new session** to keep an earlier run and test another
build. The Center warns when its build SHA no longer matches the saved session.
Stop both local previews with Ctrl-C.

Use **Review and export** to preview the report, then copy it, download Markdown
or JSON, or use Android's share sheet when the browser supports it. The Center
does not send the report to ChatGPT. Remove names, screenshots, or other private
details you do not want to share before exporting. Do not include passwords,
cookies, or learner records.

This local run does not test live HTTPS, a hosting provider, approved privacy
terms, or screen-reader behavior. Playwright is a separate desktop/CI run
(`npm ci`, `npm run test:browser:preflight`, then `npm run test:browser`). The
Batch 699 Termux runtime cannot launch Playwright Chromium. Android Chrome is
installed on the phone, but this Termux session has no ADB/DevTools or usable
UI automation bridge; the browser must be opened manually for human testing.
Use supported desktop/CI for rendered automated checks and the same-device
Chrome preview for real phone checks. Playwright is not human acceptance,
TalkBack acceptance, or WCAG conformance.

## Six short stages

1. **Entry and mobile setup:** open `/`, `/exams`, `/exams?exam=LET`,
   `/quiz?exam=LET`, and `/privacy` at the phone viewport. The privacy page is
   titled **Data and privacy**.
2. **Practice:** choose an available subject and five questions. With the
   settings script loaded, the practice button says **Start practice**; its
   HTML fallback says **Start session**. Select an answer, use **Submit answer**,
   read the feedback, and choose **Next Question**. Finish the last question.
3. **Results and persistence:** inspect the score and explanations, revisit the
   result from **Quiz history**, then open `/progress`, `/study`,
   `/study-library`, `/flashcards`, and `/privacy`. Use a fresh test session for
   export and deletion; only delete that isolated test session.
4. **Exam simulation and second attempt:** choose **Exam simulation** under
   **Session type**, try **Untimed** and **Timed**, and use the smallest offered
   count (five). The start button says **Start exam simulation**. Answer or skip
   items, use **Review all answers**, review a question, then **Submit exam
   simulation**. Complete a separate practice attempt and verify the prior
   result remains unchanged.
5. **Recovery and edge cases:** refresh an active session, use browser
   Back/Forward, and revisit a stale result action. In the isolated fixture,
   request `/quiz?exam=LET&topic=__no_such_topic__`, try an invalid quiz action
   at `/quiz?action=not-a-real-action`, and inspect the empty flashcard review
   at `/flashcards/review?include_new=0`. Do not edit canonical questions to
   make an edge case.
6. **Keyboard and accessibility:** use Tab, Shift+Tab, Enter, and Space through
   navigation, quiz settings and answers, submission, flashcard navigation,
   and privacy controls. Check focus order, labels, validation, and status
   feedback. If TalkBack or another screen reader is available, record its
   name/version and the actual result in MVP-12; otherwise keep that evidence
   unverified.

MVP-06 uses five questions because that is the smallest count exposed by the
settings page; older instructions to start a three-question quiz did not match
the UI. The browser automation targets five mobile/tablet emulations:
`320x568`, `360x800`, `390x844`, `412x915`, and `768x1024`; its desktop project
uses `1366x768`. These are desktop-browser emulations. Record the phone's actual
viewport separately; they are not physical Android test results.

## Canonical MVP acceptance checklist

Every row is a required human case. Automated coverage references describe
bounded assertions; they do not substitute for the listed human judgment.
`HUMAN_STATUS` is deliberately `NOT_RUN` until a tester records the case.

| CASE | START / PRECONDITION | HUMAN ACTION | AUTOMATED COVERAGE | AUTOMATED_STATUS | HUMAN PASS EXPECTED | FAIL SIGNAL | HUMAN_STATUS |
|---|---|---|---|---|---|---|---|
| MVP-01 | `/`; fresh session | Open Exams, choose LET, review its subjects/actions, and open **Configure practice** | Home/exam navigation and rendered setup routes | `AUTOMATED_STATUS=AUTOMATED_PASS` | Main next steps and board links are understandable and reachable | Dead link, missing action, or unclear entry path | `HUMAN_STATUS=NOT_RUN` |
| MVP-02 | `/quiz?exam=LET`; fresh session | Choose an available subject and five questions, then press **Start practice** | Quiz setup labels, mode controls, and practice question rendering | `AUTOMATED_STATUS=AUTOMATED_PASS` | A question, choices, and current/total progress appear | No question, wrong board context, or blank/incomplete controls | `HUMAN_STATUS=NOT_RUN` |
| MVP-03 | Active practice question | Select one answer, press **Submit answer**, read feedback, then choose **Next Question** | Answer selection state, feedback, keyboard focus, and next-question flow | `AUTOMATED_STATUS=AUTOMATED_PASS` | Selection is visible; response, feedback, and next item are clear | Lost selection, duplicate/skip, hidden feedback, or stuck navigation | `HUMAN_STATUS=NOT_RUN` |
| MVP-04 | Active practice attempt | Complete the attempt with **Finish Quiz**; inspect score, denominator, explanations, and missed-answer review | Result score, answer review, explanation, and result navigation | `AUTOMATED_STATUS=AUTOMATED_PASS` | Result reflects submitted answers and offers usable review | Incorrect score/denominator, missing explanation, or dead-end result | `HUMAN_STATUS=NOT_RUN` |
| MVP-05 | Completed attempt | Refresh/reopen the result; visit Quiz history, Progress, Study Dashboard, Study materials, Flashcards, and Data and privacy | Result revisit, history/progress navigation, study and privacy routes | `AUTOMATED_STATUS=AUTOMATED_PASS` | One attempt remains consistent across learner pages | Attempt disappears, duplicates, or disagrees across pages | `HUMAN_STATUS=NOT_RUN` |
| MVP-06 | `/quiz?exam=LET`; fresh session or after MVP-05 | Choose **Exam simulation**, try both timer choices, answer and skip questions, review, change one answer, and submit | Timer resume/expiry, answer review, unanswered scoring, and exact-once result | `AUTOMATED_STATUS=AUTOMATED_PASS` | Timer and answers persist; unanswered items are clear; wording separates the simulator from the official exam | Timer resets, answer loss, premature feedback, wrong result, or official-exam implication | `HUMAN_STATUS=NOT_RUN` |
| MVP-07 | Completed result from MVP-05 | Complete a second practice attempt; revisit Quiz history and the first result | Ten-board practice completion, retry, persistence, and learner isolation | `AUTOMATED_STATUS=AUTOMATED_PASS` | Two distinct attempts appear and the first result stays unchanged | First attempt is replaced, duplicated, or altered | `HUMAN_STATUS=NOT_RUN` |
| MVP-08 | Active attempt with one answer submitted | Refresh, use browser Back/Forward, revisit the question, and retry Submit/Finish only when the UI offers a safe action | Session resume, duplicate/stale action protection, and lifecycle recovery | `AUTOMATED_STATUS=AUTOMATED_PASS` | Active state recovers or gives a clear safe return without a duplicate attempt | Lost/corrupt state, accidental duplicate, or unrecoverable dead end | `HUMAN_STATUS=NOT_RUN` |
| MVP-09 | Completed attempt; browser history available | Use Back to revisit a stale question/result action; inspect unavailable-content recovery | Stale action revalidation, invalid-action alert, and safe recovery route | `AUTOMATED_STATUS=AUTOMATED_PASS` | Stale action is rejected safely with a clear recovery path | Stale request changes history/progress or creates a false result | `HUMAN_STATUS=NOT_RUN` |
| MVP-10 | Fresh isolated session; no content edits | Request the no-match LET topic, open invalid quiz action, and inspect empty flashcard review | Empty flashcard state, invalid-action alert, 404 recovery, and short-pool safeguards | `AUTOMATED_STATUS=AUTOMATED_PASS` | Clear recovery is offered; no blank, stale, or fabricated quiz appears | Blank/error detail, wrong-board content, or no way back to valid choices | `HUMAN_STATUS=NOT_RUN` |
| MVP-11 | Android phone; portrait | Open Exams, Study, Practice, Flashcards, a completed Result, History, Progress, and Privacy; tap menu, answer choices, buttons, and deletion confirmation | Five mobile/tablet viewport targets plus desktop, page overflow, clipped controls, primary target sizing, and privacy controls | `AUTOMATED_STATUS=BLOCKED` | No clipped primary control or unexpected horizontal page scroll; touch actions are controllable | Clipping, overlap, accidental submit, or unusable touch target | `HUMAN_STATUS=NOT_RUN` |
| MVP-12 | Keyboard available; learner page | Use keyboard through navigation, quiz settings/choices, answer submission, flashcards, and privacy confirmation; inspect focus, labels, headings, and errors | Chromium focus visibility, browser DOM roles/names, form labels, and status/error semantics | `AUTOMATED_STATUS=BLOCKED` | Focus order and announcements are understandable; controls work with the available input method | Keyboard trap, invisible focus, unlabeled control, or silent validation failure | `HUMAN_STATUS=NOT_RUN` |

For Batch 698, MVP-01 through MVP-10 have bounded local HTTP and lifecycle
automation on the source checkpoint recorded below. MVP-11 and MVP-12 remain
`BLOCKED` for rendered browser checks. The 18 accessibility view assertions
passed, but they do not establish viewport rendering or interactive keyboard
behavior. The older Batch 689 browser run below is historical and does not
verify the Batch 698 candidate. Automation cannot change `HUMAN_STATUS`.

## Batch 698 local automation record

```text
APPLICATION_SOURCE_SHA=6550ef1cc39d4d7d13d44f0b413442bb36df7a07
LOCAL_MVP_01_TO_10=AUTOMATED_PASS; production HTTP, quiz lifecycle, simulator, study, flashcard, history/progress, and privacy checks listed in docs/batches/batch-698.md
MVP_11_AUTOMATION=BLOCKED; no local Chromium/Playwright; 18 static view contracts passed but five viewport rendering was not run
MVP_12_AUTOMATION=BLOCKED; no local Chromium/Playwright; static focus/label contracts passed but browser keyboard interaction was not run
FULL_DOCTOR_PROCESS_EXIT=0
FULL_DOCTOR_RELEASE_GATE=PASS; P0=0; P1=0
LOCAL_BROWSER=DEPENDENCY_MISSING
HOSTED_BROWSER=NOT_RUN
HUMAN_ACCEPTANCE=NOT_RUN; all 12 HUMAN_STATUS values remain NOT_RUN
```

The Acceptance Center test verified the canonical twelve-case order, isolated
session persistence, draft recovery, honest outcomes, note editing, and report
export. Its launcher displayed the committed build identity, bound both
preview servers to `127.0.0.1`, and served the learner preview in production
mode. For a tester session, use the SHA printed by the launcher as the exact
build identity and record it in the exported report.

## Record a result

Use the current case guide in the Acceptance Center to launch the real learner
page, record what you observed, and select its human result. A `PASS` requires
the personal confirmation there; other results save when selected. Use
**Review and export** to preview the report before copying, downloading, or
sharing it. Playwright and HTTP evidence remain separate from this human result.

The table below is the canonical acceptance checklist. Do not edit its
`HUMAN_STATUS` values from automated results or an empty draft. The owner can
update committed human evidence only after reviewing an actual tester report.

For a failure, record the URL, exact steps, expected and actual results, visible
message, viewport/device, and a screenshot reference. Add a short recording for
a mobile/interaction defect when practical. Never attach cookies, credentials,
or real learner records.

For a confusing or possibly incorrect question/explanation, choose the existing
`CONTENT_ISSUE` or `CONFUSING_UX` feedback category. Include the board/subject,
the question reference shown on screen if available, the exact wording that was
confusing, and what you expected to understand. Do not guess a replacement
answer or claim academic correction. The Center stores notes and report text,
not screenshot files; keep any sanitized screenshot in the owner's approved
private handoff location and put only its reference in the note.

The Android protocol is same-device only: the tester must use the phone running
Termux and open the printed loopback URL in Android Chrome. The preview binds to
`127.0.0.1`; do not change it to `0.0.0.0`, forward the port, or expose it to
the internet. Record the device model, Android version, Chrome version from
Chrome's About page, and actual viewport. Brave and other installed Android
browsers have not been tested by this batch.

## Batch 699 browser acceptance record

```text
APPLICATION_COMMIT=d9c628ab23b0e8f7520ca1f43564f1cc968dbac7
BROWSER_HARNESS_COMMIT=24f630a6f9d9793cd816bda9fb84dbd6d531c24f
BROWSER_ACCEPTANCE=BROWSER_BLOCKED
BROWSER_REASON=Termux Node reports android/arm64; Playwright Chromium does not support this runtime.
ANDROID_ALTERNATIVE=Android Chrome, Brave, and PI Browser packages are installed, but ADB/DevTools and usable UI automation are unavailable to Termux.
PREFLIGHT=Node loopback PASS; local server shutdown PASS; driver, Chromium launch, browser navigation, and browser shutdown BLOCKED.
MOBILE_VIEWPORTS=320x568,360x800,390x844,412x915,768x1024; not rendered in this environment
DESKTOP_VIEWPORT=1366x768; not rendered in this environment
SCREENSHOTS=0; CONSOLE_ERRORS=NOT_CAPTURED
PHYSICAL_ANDROID=NOT_RUN; HUMAN_ACCEPTANCE=NOT_RUN
```

The exact preflight output is
[`batch-699-browser-preflight.json`](release-evidence/batch-699-browser-preflight.json).
The case-by-case HTTP/browser/human evidence and open owner gates are in
[`batch-699-acceptance-evidence.json`](release-evidence/batch-699-acceptance-evidence.json).
MVP-01 through MVP-10 received current bounded HTTP/lifecycle checks; their
browser results remain `BLOCKED`. MVP-11 route/static checks do not establish
rendering or touch behavior. MVP-12 interactive keyboard behavior was not
tested. All twelve human statuses remain `NOT_RUN`.

## Historical Batch 689 automation record

```text
APPLICATION_BUILD_SHA=f9fc2d0e44fa54a2403fe3548aa0837a2526c837
BROWSER_WORKFLOW_RUN_ID=39013447763
BROWSER_RESULT=AUTOMATED_PASS
BROWSER_TESTS=32 total; 28 passed; 0 failed; 4 project-conditional skips
BROWSER_ARTIFACT_ID=12093507784; boardprep-chromium-visual-qa; 131518499 bytes; retained by GitHub Actions
SCREEN_READER_ACCEPTANCE=NOT_RUN
HUMAN_ACCEPTANCE=NOT_RUN
```

The Chromium run verifies rendered routes and browser semantics at the listed
viewport targets. It does not establish TalkBack/VoiceOver behavior, physical
Android rendering, WCAG conformance, or human acceptance. Keep human case
statuses at `NOT_RUN` until the operator records actual tester evidence.

## Historical automated evidence

The Batch 663 production-route fixture
`tools/Tests/TenBoardLearnerJourneyHttpTest.php` passed representative
entry-to-result and return journeys across all ten board codes. The anonymous
learner privacy test passed scoped export/deletion and JSON/SQLite isolation.
Those historical HTTP/markup/storage results did not complete any visual,
comprehension, touch, keyboard, screen-reader, or human acceptance case.

## Defect triage and continuation

- **Blocker:** a core quiz cannot start/finish, learner data is lost/corrupted,
  or data is exposed. Stop the affected acceptance run and block release.
- **Critical:** score/result/history is wrong, duplicate writes occur, or stale
  recovery fails. Stop the affected journey and block release until fixed and
  re-verified.
- **Major:** a core flow needs a workaround, a key phone control is inaccessible,
  or recovery guidance misleads. Record it and continue unrelated cases.
- **Minor:** a wording/layout issue does not prevent understanding or use.
  Record it with the viewport and continue.
