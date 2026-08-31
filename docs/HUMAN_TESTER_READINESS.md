# BoardPrep human tester readiness

This is the manual acceptance matrix for the MVP. It is the only human
acceptance checklist; do not create a second tracker or infer a result from
automation. Every case starts as `NOT_RUN` and requires a real browser and
tester evidence.

## Start here: automated prerequisite and session header

Run this once for the build under test, before opening the matrix:

```sh
php tools/doctor.php --simulate
```

Then copy this header into the test record. `commit/build`, `date`, `tester
alias`, `device`, `browser`, `environment`, `viewport/orientation`, and
`automated prerequisite status` are canonical fields.

```text
commit/build=
date=
tester alias=
device=
browser=
environment=production|staging|local
viewport/orientation=
automated prerequisite status=PASS|FAIL|SKIPPED|DEPENDENCY_MISSING|UNAVAILABLE
```

Do not begin human acceptance when the prerequisite is `FAIL`,
`DEPENDENCY_MISSING`, or `UNAVAILABLE`; record the environment blocker. Proceed
on a fresh learner/session unless a precondition says otherwise. Use a phone
viewport for `M` cases.

## Result vocabulary

`PASS` means the expected result and usable presentation were observed.
`FAIL` means reproducible behavior differs from expected. `BLOCKED` means the
case cannot be exercised because of an environment/build/access problem;
record the reason. `NOT_APPLICABLE` is only for a case whose precondition is
absent in the supplied build. Default: `NOT_RUN`.

## Short MVP acceptance pass

Run these 12 cases in order in one fresh learner session. Each case requires
human observation in a real browser; automation references are prechecks only.

| ID | Area and exact action | Expected result | Evidence / failed severity | Automated reference | Status |
|---|---|---|---|---|---|
| MVP-01 | Open `/` in a fresh browser, then open `/quiz` | Entry and settings clearly explain the next learner action; links work | Screenshot; major | Home and HTTP scenarios | NOT_RUN |
| MVP-02 | Submit valid LET/English practice settings | A question renders with usable choices and a clear progress indicator | Screenshot; major | Quiz lifecycle | NOT_RUN |
| MVP-03 | Answer a practice question, then use Next/Finish through the quiz | Feedback is understandable; no question is skipped or duplicated; completion is reachable | Short recording; major | Quiz lifecycle | NOT_RUN |
| MVP-04 | Inspect the completed result and each review item | Score, denominator, answer review, correct answers, and explanations are coherent | Screenshot; critical if data is wrong | Quiz lifecycle | NOT_RUN |
| MVP-05 | Refresh/revisit the result, then open history and learning surfaces | Result remains available; exactly one attempt appears; dashboard/progress/study/profile agree | Before/after screenshots; critical | Learner journey and persistence tests | NOT_RUN |
| MVP-06 | Start a 3-question exam, answer one, finish, and revisit its result | Exam labels/navigation are accurate and the partial result preserves the generated denominator | Screenshot; major, critical if score/history is wrong | Exam HTTP regression | NOT_RUN |
| MVP-07 | Start a second practice quiz after MVP-05 | New session replaces the old active state; prior history/result is unchanged | History screenshot; critical | Quiz lifecycle | NOT_RUN |
| MVP-08 | Revisit `/quiz`, refresh an active quiz, replay Submit/Finish, and use browser Back | No accidental completion, duplicate answer/attempt, or corrupted state; recovery is clear | Steps + screenshot; critical | Reload/exact-once lifecycle | NOT_RUN |
| MVP-09 | Submit a captured question after completion or make its question unavailable if supported | Safe redirect/message; no fabricated result or analytics/attempt side effect | URL + screenshot; critical | Lifecycle invalidation | NOT_RUN |
| MVP-10 | Request no-match settings, a short pool, and an invalid quiz action | Clear empty/short/error guidance; no blank, stale, or fabricated quiz | Screenshot + URL; major | Shortage and HTTP tests | NOT_RUN |
| MVP-11 | At a phone viewport, operate settings, choices, Submit, Next, and result/history links | No clipping or horizontal scroll; controls are tappable and the primary action is visible | Viewport screenshots/recording; major | UI checks (partial) | NOT_RUN |
| MVP-12 | Use keyboard where available; inspect headings, labels, focus, and error/status messages | Focus/order, labels, headings, and validation messages are understandable | Notes or accessibility capture; major | UI checks (partial) | NOT_RUN |

## Result record and evidence

Use one block per case. Leave `STATUS=NOT_RUN` until that case is actually
exercised by a human. `DEFECT_ID=` is blank when no defect is raised.

```text
CASE_ID=
STATUS=PASS|FAIL|BLOCKED|NOT_APPLICABLE
EVIDENCE=
NOTES=
DEFECT_ID=
```

`PASS` requires the expected behavior, usable presentation, and adequate
evidence. `BLOCKED` is never a product PASS. For every failure capture the URL,
visible message, exact steps, expected/actual behavior, and at least one
screenshot; include viewport dimensions and a short recording for mobile or
interaction defects when practical. Never include real learner secrets.

## Test session record

Copy this block for each session:

```text
commit/build:
date:
tester alias:
device:
browser:
environment:
viewport/orientation:
automated prerequisite status:
Cases: <IDs>
CASE_ID=
STATUS=NOT_RUN
EVIDENCE=
NOTES=
DEFECT_ID=
```

## Defect triage, stop, and continuation rules

- **Blocker** — cannot start/finish core quiz, data loss/corruption, unsafe
  exposure, or testing cannot proceed. Stop testing immediately and block
  release.
- **Critical** — wrong score/result/history, duplicate attempt, analytics side
  effect on failure, or stale recovery fails. Stop the affected journey and
  block release until fixed or explicitly re-accepted after a verified fix.
- **Major** — core flow needs a workaround, key phone control is inaccessible,
  or recovery guidance misleads. Continue unrelated groups and record the
  defect; release requires a documented release-owner fix or waiver.
- **Minor** — cosmetic/copy/layout issue without loss of comprehension or use.
  Continue testing and batch for follow-up; it does not block release unless
  its impact is reclassified.

Stop the current journey for any Blocker/Critical, or whenever continuing could
overwrite evidence or learner data. Testing may continue in unrelated groups
for a Major/Minor or an environment-only Blocked case. Re-run affected cases
after a fix using a new build/session; never silently change an old result.

Use defect IDs like `HP-<case>-<short-slug>` with build, exact steps,
expected/actual, severity, frequency, evidence, and journey impact. A BLOCKED
case is an environment ticket, never a product PASS.

## Release readiness gate

Record two independent gates. Automation must never change a human case to PASS.

**AUTOMATED READINESS**

- `php tools/doctor.php --simulate`: 7/7 scenarios, 6/6 personas, zero fails.
- Relevant focused/regression tests, ordinary Doctor/project checks, and clean
  `git diff --check`.
- Simulation storage isolation and fixture restoration verified.

**HUMAN READINESS**

- Required MVP-01 through MVP-12 are recorded.
- No unresolved Blocker/Critical defects; Major defects have an explicit
  release-owner decision.
- Mobile usability, visual clarity, accessibility, real browser navigation, and
  user comprehension have human evidence.

Release is **READY** only when both gates pass. Otherwise record **NOT_READY**
or **BLOCKED** with failing case/defect IDs. The automated report may say PASS
while human acceptance remains `NOT_RUN`.

## Entry points

- Automated prerequisite: `php tools/doctor.php --simulate`
- Canonical matrix/session template: this file
- General verification: `README.md` and `tools/Doctor/README.md`
- Do not edit `storage/doctor-report.json` as a test result; it is generated
  state and may remain locally dirty.
