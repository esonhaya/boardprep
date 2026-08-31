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

## Execution order and groups

Run the groups below in order. Each case appears once in the matrix. The first
group is the high-risk pass and should be completed before secondary behavior.

1. **Critical learner journey — HP-01–HP-09, HP-15**
   Entry, settings, practice, exam, submit/finish, result/revisit/reload, and
   repeat/history integrity. Prioritize HP-02–HP-05, HP-07–HP-09, and HP-15.
2. **Persistence and recovery — HP-10–HP-14, M-04–M-06**
   Browser Back/settings navigation, stale or unavailable sessions, empty/short
   pools, reload, double submission, and persisted state.
3. **Mobile, usability, and accessibility — M-01–M-03**
   Phone layout, touch/forms, keyboard focus, labels, headings, and errors.
4. **Secondary and edge behavior — HP-16–HP-18**
   Learning surfaces, longitudinal state, and invalid actions.

High-risk cases (execute first): **HP-02, HP-03, HP-04, HP-05, HP-07,
HP-08, HP-09, HP-10, HP-11, HP-12, HP-15, M-01, M-02, M-04, M-05, M-06**.
These cover practice and exam completion, result integrity, accidental or
double submission, browser/settings navigation, stale/invalid recovery,
persisted history/progress, and mobile forms/navigation.

## Acceptance matrix

| ID | Area | Preconditions and steps | Expected result | Evidence / failed severity | Automated reference | Status |
|---|---|---|---|---|---|---|
| HP-01 | Entry | Fresh browser; open `/` | Entry point explains the next learner action and links work | Screenshot; major | Home page scenario | NOT_RUN |
| HP-02 | Configuration | Open `/quiz`; submit valid LET/English settings | Settings are understandable; quiz starts with a question | Screenshot; major | Quiz lifecycle | NOT_RUN |
| HP-03 | Practice | Start practice; answer one question | Answer is accepted and feedback is understandable | Screenshot; major | Quiz lifecycle | NOT_RUN |
| HP-04 | Navigation | Use practice Next/Finish through all questions | No skipped/duplicated question; finish is reachable | Recording or steps; major | Quiz lifecycle | NOT_RUN |
| HP-05 | Results | Finish HP-04 | Result shows score/summary and answer review | Screenshot; critical if data wrong | Quiz lifecycle | NOT_RUN |
| HP-06 | Review | Open each answer review item | Question, answer, correct answer, and explanation are coherent | Screenshot; major | Quiz lifecycle | NOT_RUN |
| HP-07 | Result revisit | Refresh result; leave to settings and return | Result remains available and is not duplicated | Before/after; critical | Quiz lifecycle | NOT_RUN |
| HP-08 | Exam | Start 3-question exam; answer one; finish | Exam navigation and partial completion match labels | Screenshot; major | Quiz lifecycle/exam tests | NOT_RUN |
| HP-09 | Repeat | Complete a second practice session | New history entry is distinct; earlier result unchanged | History screenshots; critical | Quiz lifecycle | NOT_RUN |
| HP-10 | Back/settings | During quiz visit `/quiz`, use browser Back, continue | No accidental completion or loss; controls recover | Steps + screenshots; major | Quiz lifecycle | NOT_RUN |
| HP-11 | Stale session | Submit a captured question after completing/invalidating | Safe redirect and stale/invalid message; no fabricated result | URL + screenshot; critical | Lifecycle invalidation | NOT_RUN |
| HP-12 | Unavailable question | Start quiz; make active question unavailable in supplied content/admin environment; submit | Session abandoned with guidance; no attempt/analytics side effect | Before/after; critical | Lifecycle invalidation | NOT_RUN |
| HP-13 | Empty pool | Request settings matching no questions | Recovery message; no blank/fabricated or stale quiz | Screenshot + URL; major | Lifecycle shortage | NOT_RUN |
| HP-14 | Short pool | Request more than eligible supply | Displayed count/content matches production behavior; no leak | Screenshot + count; major | Lifecycle shortage | NOT_RUN |
| HP-15 | History | Open `/history` after HP-05/09 | Sessions are listed once with understandable labels | Screenshot; major | Learning surfaces/personas | NOT_RUN |
| HP-16 | Learning surfaces | Visit `/dashboard`, `/progress`, `/profile`, `/study` | Surfaces load, agree on state, and links work | Four screenshots; major | Learning surfaces/personas | NOT_RUN |
| HP-17 | Longitudinal loop | Complete session; revisit study/progress; use shown targeted action if present | Progress/weakness state changes coherently | Before/after; major | Learner persona simulation | NOT_RUN |
| HP-18 | Invalid actions | Try invalid quiz action or stale Finish | Safe redirect/message; no server error/history mutation | URL + screenshot; major | HTTP status/lifecycle | NOT_RUN |
| M-01 | Mobile layout | Phone viewport; home, settings, quiz, result, history | No clipping/horizontal scroll; primary action visible | Viewport screenshots; major | UI checks (partial) | NOT_RUN |
| M-02 | Touch/forms | On phone operate inputs, choices, buttons | Targets tappable; labels associated; submits once | Short recording; major | UI checks (partial) | NOT_RUN |
| M-03 | Accessibility | Keyboard where available; inspect headings, focus, labels, status | Logical focus/order, visible focus, meaningful headings and errors | Notes/tree capture; major | UI checks (partial) | NOT_RUN |
| M-04 | Reload | Refresh settings, active quiz, feedback, result | No duplicate/corrupt navigation; recovery is clear | URLs + screenshots; critical | Lifecycle/reload | NOT_RUN |
| M-05 | Double submit | Double-tap Submit/Finish or replay browser request | At most one answer/attempt; replay safely rejected | Recording + history; critical | Exact-once lifecycle | NOT_RUN |
| M-06 | Persistence | Move between quiz, result, history, learning surfaces | State is consistent and survives navigation | Screenshot sequence; major | Learner journey | NOT_RUN |

HP-11 and HP-12 cover equivalent invalid-session guards; run both only when the
environment permits distinct stale and unavailable-content setups.

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

- Required HP-01 through HP-18 and M-01 through M-06 are recorded.
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
