# BoardPrep — Batch 699 Browser Acceptance & Alpha Evidence

**Target:** restricted alpha candidate for 2026-10-31

**Outcome:** browser execution is `BROWSER_BLOCKED` in this Termux environment. The browser harness, preflight, canonical case evidence, and same-device Android handoff are now reproducible and explicit. No human, physical-device, hosted, academic, owner, or product-launch result is claimed.

## Baseline and scope

- Starting branch: `batch-698-october-alpha`
- Starting application/source SHA: `d9c628ab23b0e8f7520ca1f43564f1cc968dbac7`
- Source checkpoint with the B698 Full Doctor report: `6550ef1cc39d4d7d13d44f0b413442bb36df7a07`
- B699 worktree/branch: `../boardprep-batch-699`, `batch-699-browser-acceptance`
- Browser harness/preflight checkpoint: `24f630a6f9d9793cd816bda9fb84dbd6d531c24f`

The B699 worktree was created from the verified B698 branch. The dirty
Batch 695 academic-review worktree, its 276 pending questions and review
identities, main, B696, B697, B698, two stashes, HayaDoctor pin, and unrelated
untracked files were left in place. A test-created untracked
`storage/question-statistics.json` in the B699 worktree was preserved and not
staged. B699 changes no application, question, lesson, PDF, learner, account,
privacy, or production configuration code.

## Browser capability and reproduction

The execution host is Android/Termux, Linux kernel `4.19.152-perf+`,
`aarch64/arm64`, Node `v26.4.0`, npm `12.0.2`, and PHP `8.5.1`. Node reports
`process.platform=android`. The project pins Playwright `1.63.0`; the package
and bundled Chromium are not installed in this worktree. No Chromium executable
is on the Termux path. Android Chrome, Brave, and PI Browser are installed
applications, but `adb` is absent and Termux has no usable DevTools or UI
automation bridge. The available Android UI tools fail because `app_process`
is inaccessible. No graphical desktop/X server is available.

Installing the pinned desktop Playwright browser into this Android Node runtime
cannot make the project's runner supported. No browser packages or global npm
dependencies were installed. A Termux package search for Chromium timed out
while checking mirrors; installation was not attempted. The existing native
Android browsers therefore remain available only for a person to open
manually through the documented same-device loopback flow.

`npm run test:browser:preflight` records the runtime, pinned-driver status,
browser availability, loopback bind/navigation capability, and shutdown. Its
result is **BLOCKED**: Node and loopback server start/stop passed; driver
compatibility, Playwright package, Chromium launch/navigation, and browser
shutdown are blocked because no browser process could start. The runner
`node tools/run-browser-tests.cjs` exits `2` with
`BROWSER_ACCEPTANCE=BROWSER_BLOCKED`; it does not convert the missing
dependency into a pass. See
[`../release-evidence/batch-699-browser-preflight.json`](../release-evidence/batch-699-browser-preflight.json).

The existing Playwright suite uses real page controls and navigation. Its
acceptance projects were aligned to the requested viewport targets:

| Target | Result |
|---|---|
| 320×568 | Configured; not rendered |
| 360×800 | Configured; not rendered |
| 390×844 | Configured; not rendered |
| 412×915 | Configured; not rendered |
| 768×1024 | Configured; not rendered |
| 1366×768 desktop | Configured; not rendered |

No screenshot or browser console/runtime capture was produced. This is not a
physical Android result. The current limitation is an environment capability
block, not evidence of a browser defect or a visual pass.

On a supported desktop or CI host, install the lockfile-pinned dependencies and
run:

```sh
npm ci
npm run test:browser:preflight
npm run test:browser
```

Retain the runner's exact-SHA manifest and artifacts. Do not attribute a later
run to the B699 candidate unless its tested application SHA and acceptance
evidence are recorded.

## Acceptance evidence and Android handoff

The existing `docs/HUMAN_TESTER_READINESS.md` remains the single canonical
MVP-01–MVP-12 case list and the Acceptance Center remains the human result
workflow. The B699 machine-readable companion
[`../release-evidence/batch-699-acceptance-evidence.json`](../release-evidence/batch-699-acceptance-evidence.json)
keys every record to those same twelve IDs. It records expected behavior,
HTTP result and test/SHA, browser result and attempted harness SHA, human
result, environment, timestamp, artifact reference, and blocking reason. It
does not write or infer a human result.

| Evidence | Result |
|---|---|
| MVP-01–MVP-10 HTTP/lifecycle automation | PASS on application SHA `d9c628ab23b0e8f7520ca1f43564f1cc968dbac7`; browser BLOCKED; human NOT_RUN |
| MVP-11 HTTP/static template assertions | PASS, including 18 static view assertions; rendered layout and touch behavior BLOCKED; human NOT_RUN |
| MVP-12 static view semantics | PASS, included in the 18 static assertions; browser keyboard and assistive-technology behavior BLOCKED; human NOT_RUN |
| Browser execution across all cases | BLOCKED; no browser launched |
| Human outcomes | 12/12 NOT_RUN |

The guide includes all twelve scenarios, expected outcomes, recovery and
invalid-state steps, safe screenshot references, and content-confusion feedback
instructions. It explains that the preview binds only to `127.0.0.1`, must not
be exposed or port-forwarded, uses an isolated fixture, and is same-device only
for Android Chrome. Testers should record device/Android/Chrome versions and
actual viewport; never include passwords, cookies, names, or real learner
records. The Acceptance Center test confirms its twelve-case order, isolated
durable sessions, honest outcomes, draft recovery, feedback editing, CSRF-ready
storage and export integrity. No tester ran this guide during B699.

## Validation evidence

Focused local checks completed on the B699 worktree:

- `AcceptanceCenterTest.php` — PASS after the canonical guide edits.
- `TenBoardLearnerJourneyHttpTest.php` — PASS for LET, CSE, CLE, NLE, PLE, CE,
  CPA, ME, REE, and ARCH.
- `GuidedLearningJourneyHttpTest.php` — PASS at `memory_limit=512M`. Its first
  invocation at PHP's default 128 MiB ended at the resource limit; the same
  unchanged test passed once with the explicit memory setting.
- `ExamSimulationHttpTest.php`, `QuizExperienceResilienceTest.php`,
  `QuizSessionResilienceTest.php`, `QuizCompletionHttpTest.php`, and
  `QuizStartFailureHttpTest.php` — PASS.
- `AnonymousLearnerPrivacyHttpTest.php`, `ProductionQuizCsrfTest.php`,
  `SecurityTrustBoundaryTest.php`, and `SessionConfigurationTest.php` — PASS.
- `LearnerMobileAccessibilityViewTest.php` — PASS, 18 static assertions; this
  does not establish rendered or interactive accessibility.
- `QuestionSelectionScopeRegressionTest.php` and `StudyPdfPipelineTest.php` —
  PASS.
- `ReleasePackageTest.php`, `DeploymentPreflightIsolationTest.php`, and
  `ReleaseFlowStorageIsolationTest.php` — PASS.
- `npm run test:browser:fixture` — PASS for canonical content retention and
  isolated mutable learner collections. This is a fixture check, not browser
  execution.
- `node --check` for the preflight, runner, and modified Playwright specs —
  PASS.
- Canonical evidence validation — PASS: twelve ordered unique case IDs,
  expected behaviors match the Acceptance Center table, all human states are
  `NOT_RUN`, all browser states are `BLOCKED`, and the eleven open gates include
  owners and closure criteria.
- `php tools/product-readiness.php --json` — completed with exit `1` and
  `product_launch_ready=false`, as expected while required evidence is open.

The flashcard production path was not changed. B698's
`FlashcardHttpTest.php` and exact B698 preview smoke remain the applicable
evidence; no duplicate full-bank or flashcard scan was run. The full Doctor was
not rerun: B699 changes only browser test infrastructure and evidence
documentation, while B698's complete Doctor process exited `0`, release gate
PASS, P0=0/P1=0, 25/26 domains PASS and `SOURCE_TRACEABILITY=REVIEW` at a runtime
of 1,965 seconds. This historical local result is not a hosted or browser pass.

No P0/P1 production defect was observed in the B699 focused HTTP, privacy,
security, and storage checks; no production source changed. P2 source
traceability remains under independent review. B698's P3 thin-leaf planning
and architecture visual backlog remain nonblocking. The desktop browser
environment limitation is classified as `ENVIRONMENT_LIMITATION`.

## Alpha status and open gates

`docs/release-evidence/batch-699-acceptance-evidence.json` contains the
owner/status/closure register. Current states remain:

- Local code readiness: B698 `ALPHA_CODE_READY` remains the applicable local
  code result; B699 did not change application code or rerun the Doctor.
- Browser: **BROWSER_BLOCKED** in Termux. Desktop Chromium run pending.
- Android tester access: same-device loopback guide is ready; actual physical
  Android execution is **NOT_RUN**.
- Human acceptance: **NOT_RUN**, all twelve cases.
- Academic/source review: **REVIEW/PENDING**; the 276 Batch 695 questions
  remain isolated and unapproved.
- Owner privacy terms, operator/contact, retention/deletion decisions,
  hosting/provider and HTTPS/cookie evidence: **AWAITING_OWNER_APPROVAL** or
  **NOT_VERIFIED** as detailed in the register.
- Hosted exact-SHA CI/smoke/browser/explorer: **NOT_RUN**.
- Product launch readiness: **NO**. No privacy or owner approvals were
  modified to manufacture a pass.

The current Product Readiness document had one stale sentence implying that
current-commit Chromium evidence already covered the viewport and ten-board
checks. It now states that B699 browser evidence is blocked and that configured
tests are not execution evidence. The Product Readiness command still returns
its intentional nonzero result while mandatory launch evidence is missing.

## Protected content and data

No canonical question, answer, ID, eligibility mapping, study lesson, PDF,
source link, usage count, learner attempt, history, flashcard state, account,
anonymous identity, production setting, or academic-review record was changed.
The baseline remains 9,123 canonical questions and 47 lessons/PDFs. Batch 695's
276 review items and manifest remain on their isolated branch. The B699 test
generated untracked `storage/question-statistics.json`; it is preserved and
excluded from the commit.

## Next safe action

On a supported desktop/CI machine, run the three browser commands above and
retain exact-SHA evidence, screenshots, console output, and viewport results.
Then have actual Android testers run the same-device guide and record their
real outcomes through the existing Acceptance Center. Complete owner privacy
and hosting approvals, provider/HTTPS verification, and independent academic
review before considering access beyond isolated local testing. Do not push,
deploy, or call the candidate hosted, human, Android, academic, or product
launch accepted based on this batch.
