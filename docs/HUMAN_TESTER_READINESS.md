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

Run the local prerequisite once for the build under test:

```sh
php tools/doctor.php --simulate
```

Do not begin if the prerequisite is `FAIL`, `DEPENDENCY_MISSING`, or
`UNAVAILABLE`; record `BLOCKED` and its reason. Start with a fresh isolated
learner session. Use a phone in portrait orientation for the mobile cases.

Record these fields before testing:

```text
BUILD_SHA=
DATE_UTC=
TESTER_OR_ALIAS=
DEVICE_AND_MODEL=
OS_AND_VERSION=
BROWSER_AND_VERSION=
ENVIRONMENT=production|staging|local-fixture
VIEWPORT_WIDTH_HEIGHT=
ORIENTATION=portrait|landscape
AUTOMATED_PREREQUISITE_STATUS=PASS|FAIL|SKIPPED|DEPENDENCY_MISSING|UNAVAILABLE
AUTOMATED_RUN_IDS=
EVIDENCE_REFERENCES=
```

Use a tester alias if the report will be shared. Do not include learner names,
session cookies, real exported records, or credentials in screenshots or
artifacts.

## Android same-device preview

For local acceptance on the Android phone that has the repository, the preview
uses the committed `HEAD` archive with isolated production-mode storage and
PHP sessions. It binds only to loopback and does not serve the working storage.

```sh
git status --short
git rev-parse HEAD
php tools/doctor.php --simulate
node tests/browser/server.cjs
```

Open the exact URL printed by the server in Chrome on the same phone; it is
normally `http://127.0.0.1:4173`. If that port is occupied, stop the first
server or set `BOARDPREP_BROWSER_PORT=4174` before starting the preview and use
the printed URL. Stop it with Ctrl-C. Do not change the bind address to
`0.0.0.0`, create a tunnel, or use real learner data for destructive checks.

The preview serves committed `HEAD`, not uncommitted files. Starting a new
preview creates a fresh isolated fixture. It does not verify HTTPS, a hosting
provider, or production privacy terms.

Playwright is a separate desktop/CI run (`npm ci`, then `npm run test:browser`).
The Android Termux Node runtime cannot launch its Chromium binary. Use supported
desktop/CI for automated browser checks and the same-device Chrome preview for
human phone checks. Playwright is not human acceptance, TalkBack acceptance, or
WCAG conformance.

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
the UI. On mobile, inspect all five targets used by automation: `320x640`,
`360x800`, `390x844`, `430x932`, and `768x1024`. Record the phone's actual
viewport separately from these automated targets.

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
| MVP-11 | Android phone; portrait | Open Exams, Study, Practice, Flashcards, a completed Result, History, Progress, and Privacy; tap menu, answer choices, buttons, and deletion confirmation | Five viewport targets, page overflow, clipped controls, primary target sizing, and privacy controls | `AUTOMATED_STATUS=AUTOMATED_PASS` | No clipped primary control or unexpected horizontal page scroll; touch actions are controllable | Clipping, overlap, accidental submit, or unusable touch target | `HUMAN_STATUS=NOT_RUN` |
| MVP-12 | Keyboard available; learner page | Use keyboard through navigation, quiz settings/choices, answer submission, flashcards, and privacy confirmation; inspect focus, labels, headings, and errors | Chromium focus visibility, browser DOM roles/names, form labels, and status/error semantics | `AUTOMATED_STATUS=AUTOMATED_PASS` | Focus order and announcements are understandable; controls work with the available input method | Keyboard trap, invisible focus, unlabeled control, or silent validation failure | `HUMAN_STATUS=NOT_RUN` |

The automated statuses above are populated only by the Batch 689 CI evidence
recorded below. For later builds, rerun automation and replace each automated
status with `AUTOMATED_PASS`, `BLOCKED`, `NOT_RUN`, or `NOT_APPLICABLE` with its
run/build reference. Automation cannot change `HUMAN_STATUS`.

## Record a result

Keep one record per case. `HUMAN_PASS` requires the expected behavior, usable
presentation, and adequate evidence. `BLOCKED` and `NOT_APPLICABLE` are not
passes.

```text
CASE_ID=
AUTOMATED_STATUS=AUTOMATED_PASS|BLOCKED|NOT_RUN|NOT_APPLICABLE
AUTOMATED_EVIDENCE_REFERENCE=
HUMAN_STATUS=HUMAN_PASS|HUMAN_FAIL|BLOCKED|NOT_RUN|NOT_APPLICABLE
BUILD_SHA=
DATE_UTC=
TESTER_OR_ALIAS=
DEVICE_AND_MODEL=
OS_AND_VERSION=
BROWSER_AND_VERSION=
ENVIRONMENT=
VIEWPORT_WIDTH_HEIGHT_AND_ORIENTATION=
OBSERVATIONS=
EVIDENCE_REFERENCES=
DEFECT_ID=
```

For a failure, record the URL, exact steps, expected and actual results, visible
message, viewport/device, and a screenshot reference. Add a short recording for
a mobile/interaction defect when practical. Never attach cookies, credentials,
or real learner records.

## Batch 689 automation record

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
