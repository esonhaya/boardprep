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
COMMIT_BUILD=
DATE=
TESTER=
DEVICE=
BROWSER=
ENVIRONMENT=production|staging|local
VIEWPORT=
AUTOMATED_PREREQUISITE=PASS|FAIL|SKIPPED|DEPENDENCY_MISSING|UNAVAILABLE
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

Run these 12 cases in six stages, using one fresh learner session where the
preconditions allow it. `AUTOMATED_CONFIRMED` means the functional assertions
already pass locally; `AUTOMATED_PARTIAL` means automation covers behavior but
not the whole human judgment; `HUMAN_REQUIRED` means a real tester must judge
the interaction or presentation. No automated result changes `HUMAN_STATUS`.

### Stage A — Entry and mobile setup

Set a phone viewport and open `/`, `/quiz`, and the settings page. Record the
session header above. Run MVP-01 and MVP-11.

### Stage B — Practice start and active quiz

Submit valid LET/English practice settings, answer a question, use Next, and
continue to Finish. Run MVP-02 and MVP-03. Note any unclear feedback or
control, but do not interrupt the journey for a cosmetic issue.

### Stage C — Completion and persistence

Inspect the result and review, refresh/revisit it, then open history and the
learning surfaces. Run MVP-04 and MVP-05. Capture evidence only for a failure,
unexpected data, or confusing presentation.

### Stage D — Exam and second session

Start a three-question exam, answer one, finish, revisit its result, then start
a second practice quiz. Run MVP-06 and MVP-07. Confirm the earlier history and
result remain unchanged.

### Stage E — Recovery and edge states

During the active session revisit `/quiz`, refresh, use Back, replay Submit or
Finish, and submit a captured question after completion. If the supplied
environment permits, make its question unavailable. Request no-match and
short-pool settings and try an invalid quiz action. Run MVP-08, MVP-09, and
MVP-10.

### Stage F — Keyboard and accessibility

Use keyboard navigation where available and inspect headings, labels, visible
focus, validation, and status messages. Run MVP-12. Record viewport/device
details for any interaction or accessibility defect.

| CASE | PURPOSE | AUTOMATED EVIDENCE | HUMAN ACTION | EXPECTED RESULT | STATUS |
|---|---|---|---|---|---|
| MVP-01 | Entry and settings | `AUTOMATED_PARTIAL` — home/settings routes and links pass | Open `/` then `/quiz`; judge clarity | Learner understands the next action and links work | AUTOMATED_GATE=PASS; HUMAN_STATUS=NOT_RUN |
| MVP-02 | Practice start/render | `AUTOMATED_PARTIAL` — POST start and question HTML pass | Submit valid settings; judge choices/progress usability | A valid question renders with clear progress | AUTOMATED_GATE=PASS; HUMAN_STATUS=NOT_RUN |
| MVP-03 | Answer/navigation | `AUTOMATED_PARTIAL` — submission, feedback path, navigation pass | Answer and advance through practice; judge feedback | Answer is accepted, feedback is understandable, no skips/duplicates | AUTOMATED_GATE=PASS; HUMAN_STATUS=NOT_RUN |
| MVP-04 | Result/review | `AUTOMATED_CONFIRMED` — score, denominator, review and result flow pass | Inspect result/review for comprehension | Score and review data are correct and coherent | AUTOMATED_GATE=PASS; HUMAN_STATUS=NOT_RUN |
| MVP-05 | Revisit/persistence | `AUTOMATED_CONFIRMED` — result revisit, history, persistence pass | Refresh result; visit history/dashboard/progress/study/profile | One attempt persists and learner surfaces agree | AUTOMATED_GATE=PASS; HUMAN_STATUS=NOT_RUN |
| MVP-06 | Exam mode | `AUTOMATED_CONFIRMED` — exam HTTP lifecycle and denominator pass | Run 3-question exam and judge labels | Exam navigation and partial result are accurate | AUTOMATED_GATE=PASS; HUMAN_STATUS=NOT_RUN |
| MVP-07 | Second session | `AUTOMATED_CONFIRMED` — replacement and exact-once isolation pass | Start another practice quiz; spot-check history | New session is distinct; earlier result is unchanged | AUTOMATED_GATE=PASS; HUMAN_STATUS=NOT_RUN |
| MVP-08 | Reload/back/replay | `AUTOMATED_PARTIAL` — stale replay and lifecycle recovery pass | Refresh, Back, replay Submit/Finish during a session | No accidental completion, duplicate write, or corruption | AUTOMATED_GATE=PASS; HUMAN_STATUS=NOT_RUN |
| MVP-09 | Stale/unavailable question | `AUTOMATED_PARTIAL` — live status revalidation and safe redirect pass | Use a captured question after completion; try unavailable content if possible | Safe recovery message; no fabricated result or side effect | AUTOMATED_GATE=PASS; HUMAN_STATUS=NOT_RUN |
| MVP-10 | Empty/short/error states | `AUTOMATED_CONFIRMED` — shortage, eligibility, and invalid-action paths pass | Request no-match/short pool and invalid action; judge guidance | Clear recovery; no blank, stale, or fabricated quiz | AUTOMATED_GATE=PASS; HUMAN_STATUS=NOT_RUN |
| MVP-11 | Mobile interaction | `AUTOMATED_PARTIAL` — viewport/markup checks only | Use phone viewport and operate all primary controls | No clipping/scroll; controls are tappable and visible | AUTOMATED_GATE=PASS; HUMAN_STATUS=NOT_RUN |
| MVP-12 | Keyboard/accessibility | `AUTOMATED_PARTIAL` — semantic markup/static checks only | Navigate by keyboard; inspect focus, labels, headings, errors | Focus and messages are understandable and usable | AUTOMATED_GATE=PASS; HUMAN_STATUS=NOT_RUN |

Case-level classification: `AUTOMATED_CONFIRMED=5`,
`AUTOMATED_PARTIAL=7`, and `HUMAN_REQUIRED=12` for the learner-facing
presentation, comprehension, or practical interaction assertions.

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
COMMIT_BUILD=
DATE=
TESTER=
DEVICE=
BROWSER=
ENVIRONMENT=
VIEWPORT=
AUTOMATED_PREREQUISITE=
CASES=<IDs>
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
