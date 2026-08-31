# BoardPrep human tester readiness

This is the manual acceptance matrix for the MVP. Every case starts as
`NOT_RUN` and requires a real browser and tester evidence. Run the automated
prerequisite first:

```sh
php tools/doctor.php --simulate
```

Proceed in ID order on a fresh learner/session unless a precondition says
otherwise. Use a phone viewport for `M` cases.

## Result vocabulary

`PASS` means the expected result and usable presentation were observed.
`FAIL` means reproducible behavior differs from expected. `BLOCKED` means the
case cannot be exercised because of an environment/build/access problem;
record the reason. `NOT_APPLICABLE` is only for a case whose precondition is
absent in the supplied build. Default: `NOT_RUN`.

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

## Test session record

Copy this block for each session:

```text
Tester:
Device / OS / browser + version:
Viewport / connection:
Build commit or release:
Date/time/timezone:
Cases: <IDs>
Case ID | Result (PASS/FAIL/BLOCKED/NOT_APPLICABLE) | Notes | Evidence ref | Defect ID
```

Capture URL, visible message, and one screenshot for every failure; add viewport
dimensions and a short recording for mobile/interaction defects when practical.
Never include real learner secrets in evidence.

## Defect triage and continuation

- **Blocker** — cannot start/finish core quiz, data loss/corruption, unsafe
  exposure, or testing cannot proceed. Stop the journey and block release.
- **Critical** — wrong score/result/history, duplicate attempt, analytics side
  effect on failure, or stale recovery fails. Stop that journey; release blocks.
- **Major** — core flow needs a workaround, key phone control is inaccessible,
  or recovery guidance misleads. Continue unrelated cases; fix or waive.
- **Minor** — cosmetic/copy/layout issue without loss of comprehension or use.
  Continue and batch for follow-up.

Use defect IDs like `HP-<case>-<short-slug>` with build, exact steps,
expected/actual, severity, frequency, evidence, and journey impact. A BLOCKED
case is an environment ticket, never a product PASS.

## Release readiness gate

Record two independent gates. Automation must never change a human case to PASS.

**AUTOMATED**

- `php tools/doctor.php --simulate`: 7/7 scenarios, 6/6 personas, zero fails.
- Relevant focused/regression tests, ordinary Doctor/project checks, and clean
  `git diff --check`.
- Simulation storage isolation and fixture restoration verified.

**HUMAN**

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
