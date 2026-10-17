# Batch 690 — Guided Human Acceptance Center and feedback export

## Development report

```text
BATCH=690
START_TIME=2026-10-17T13:19:38Z (earliest retained task timestamp; invocation time was not captured)
END_TIME=2026-10-17T14:31Z (rounded closeout time)
ELAPSED_MINUTES=~71 minutes from the earliest retained task timestamp; invocation time was not captured
HEAD_BEFORE=cfccd6b64d73798f0942485c0ac421f4457ddf2a
HEAD_AFTER_APPLICATION=28de92ad49e17fd1edaee33b2bc89ddbe5d0337d
HEAD_AFTER_DOCUMENTATION=25d7fe493537c7393746fa671c7f59ebf85bdb59 (owner handoff and evidence registration; final report closeout follows)
ORIGIN_MAIN_AFTER=final report-only closeout commit; self-referential SHA is omitted here and reported in the final response

ACCEPTANCE_CENTER_STATUS=PASS; browser suite passed on final application commit
ACCEPTANCE_ROUTE=/developer/acceptance (nonproduction, loopback only)
START_COMMAND=./tools/acceptance/start.sh
SCENARIOS_IMPLEMENTED=Canonical MVP-01 through MVP-12; no competing checklist
GUIDED_NAVIGATION_STATUS=PASS; one-tap guide to learner pages in a separate tab with return control
MOBILE_USABILITY_STATUS=Automated Chromium sweep PASS at 320x640, 360x800, 390x844, 430x932, and 768x1024; actual handset use remains HUMAN_NOT_RUN

FEEDBACK_AUTOSAVE_STATUS=PASS
FEEDBACK_RESUME_STATUS=PASS; reload/new-page recovery and a fresh store instance are covered; real owner phone session remains HUMAN_NOT_RUN
MULTIPLE_COMMENTS_STATUS=PASS
GENERAL_FEEDBACK_STATUS=PASS
EXPORT_MARKDOWN_STATUS=PASS; fields and comment text preserved
EXPORT_JSON_STATUS=PASS; schema 1.0
BROWSER_CLIPBOARD_STATUS=PASS; browser clipboard API, denied-permission fallback, Markdown/JSON downloads, and share API path passed automated Chromium checks
ANDROID_SHARE_STATUS=Browser share API supported and CI-mocked; real Android share sheet unverified

NONPRODUCTION_ISOLATION_STATUS=PASS; private reports and isolated production-mode learner fixture outside repository
PRODUCTION_ROUTE_EXCLUSION_STATUS=PASS; production-mode preview returns 404 for developer acceptance route
CANONICAL_DATA_PROTECTION_STATUS=PASS; no learner/question storage mutation by Acceptance Center tests

HUMAN_ACCEPTANCE_ACTUAL_STATUS=NOT_RUN; none executed; 0/12 passed and all 12 remain NOT_RUN
HUMAN_MVP_CASES_PASSED=0
HUMAN_MVP_CASES_NOT_RUN=12
SCREEN_READER_STATUS=NOT_RUN; no TalkBack or WCAG claim
AUTOMATED_COVERAGE_STATUS=PASS; 12 Acceptance Center behaviors ran in mobile and desktop Chromium projects alongside existing learner flows
CHROMIUM_STATUS=52 PASS / 0 FAIL / 4 justified conditional SKIP of 56
PRODUCT_BROWSER_STATUS_BEFORE=PASS; DOCUMENTATION_ONLY_DESCENDANT at HEAD_BEFORE
PRODUCT_BROWSER_STATUS_AFTER_APPLICATION=PASS; exact current application commit after browser run registration
PRODUCT_BROWSER_RELATION_AFTER_DOCUMENTATION=DOCUMENTATION_ONLY_DESCENDANT
DOCTOR_STATUS=PASS; P0=0, P1=0, P2=1, P3=2
SECURITY_PRIVACY_STATUS=6/6 PASS
PRODUCT_READINESS_STATUS=FAIL as required; browser evidence is BROWSER_EXECUTED_PASS with DOCUMENTATION_ONLY_DESCENDANT; human, owner privacy, and live-host blockers remain
PRODUCT_FEATURE_COMPLETE=false
PRODUCT_LAUNCH_READY=false
MANDATORY_EVIDENCE_NOT_PASSED=Human acceptance MVP-01..12; site-specific privacy/operator/retention/deletion approval; live HTTPS and hosting evidence
REMAINING_INCOMPLETE_FEATURES=Data transparency and deployment-specific privacy terms
PRIVACY_APPLICATION_STATUS=PASS; anonymous disclosure, session-scoped export/deletion, CSRF, rotation/isolation, and preservation of other learners passed focused regression
PRIVACY_OWNER_APPROVAL_STATUS=AWAITING_OWNER_APPROVAL; no owner/legal terms approved or inferred
LIVE_HOSTING_EVIDENCE_STATUS=NOT_VERIFIED; no live deployment was tested

STORAGE_FREE_BEFORE=about 8.53 million KiB (over 8 GiB)
STORAGE_FREE_AFTER=8,512,456 KiB (~8.12 GiB) at final repository checks
STORAGE_FLOOR_STATUS=PASS; remained above 1.5 GiB
CANONICAL_QUESTIONS_BEFORE_AFTER=8,962 / 8,962; IDs, keys, explanations, taxonomy, eligibility, and educational content unchanged
QUESTION_USAGE_DELTA_STATUS=PASS; 19 pre-existing rows only (timesUsed 19, timesCorrect 18, timesIncorrect 17, updatedAt 19); remained unstaged and unchanged
PROTECTED_STATE_STATUS=Batch 685 historical manifest retained unmodified; 163/168 snapshot hashes match; five pre-existing deltas are storage/questions.json plus storage/sources.json, storage/study-lessons.json, storage/study-materials.json, and storage/study-question-links.json
HAYADOCTOR_PIN_STATUS=PASS; dc4fcda730d9a922f726593746e34ca0c9c0e890
UNRELATED_WORK_PRESERVED=PASS; all pre-existing modified/untracked paths remain unstaged and intact
PUSH_STATUS=Application, docs/evidence, and report closeout pushed using ordinary fast-forward updates

USER_LAUNCH_INSTRUCTIONS=Run ./tools/acceptance/start.sh; open the printed OPEN_THIS_URL in Android Chrome
REPORT_EXPORT_LOCATION=$PREFIX/share/boardprep/acceptance-center (Termux); private local JSON sessions and report history
UNRESOLVED_ISSUES=Human acceptance; owner/provider privacy and hosting evidence; real-device share-sheet and screen-reader acceptance; independent academic signoff
NEXT_SAFE_BATCH=Owner completes MVP-01..12 and BP662-CAP-001 evidence; continue with confirmed feedback and independent academic review
CLIPBOARD_STATUS=UNVERIFIED; termux-clipboard-set returned 0, but termux-clipboard-get returned 0 bytes and exact comparison failed; saved report: docs/batches/batch-690.md
```

The documentation commit is recorded in the terminal report and Git history.
Including its own hash in the committed contents would change that hash.

## What the owner uses

Run `./tools/acceptance/start.sh` from the repository on the Android phone.
The command identifies committed `HEAD`, makes a committed production-mode
learner fixture, starts the separate nonproduction Acceptance Center, binds
both servers to `127.0.0.1`, and prints `OPEN_THIS_URL`. Open that URL in Android
Chrome. No route memorization or manual navigation from unrelated pages is
needed.

The dashboard shows all twelve canonical cases, progress, session metadata,
the next test, and final review. Each case explains what is being checked, why,
where to start, what to do, what good looks like, and common problems. The
Center opens a real learner route in a second tab; keep its tab available and
use the return button to record the observation. It does not select answers or
complete the test for the owner.

Draft notes save automatically and a saved session can be resumed. Start a new
session to preserve the old report and test a different build. The build SHA is
stored with each session and a changed build is flagged. Multiple observations
per case, general feedback, editable drafts, and optional evidence references
are supported. No outcome is inferred from an automated result; a human PASS
requires the tester's explicit confirmation.

Review the report preview, then copy the full text, download Markdown or JSON,
or use the browser's Android share action if available. The Center does not
send data to ChatGPT. Before sharing, remove private names, screenshots,
credentials, cookies, and any learner records. Private local reports are outside
the repository in `$PREFIX/share/boardprep/acceptance-center` on Termux,
`$XDG_STATE_HOME/boardprep/acceptance-center`, or
`~/.local/state/boardprep/acceptance-center` on other systems. Do not copy that
directory into source control.

## Implementation and safety

The developer route is within the existing nonproduction route block and is
guarded for loopback. The launcher archives committed `HEAD` and serves it in
isolated test fixtures. The learner preview uses production mode; the guide
uses a separate testing environment. Private session and report files use
owner-only directory/file permissions. Requests enforce CSRF and same-origin
checks, owner/session separation, safe identifiers, and bounded fields. Feedback
never enters canonical question or learner collections. Starting a new session
does not delete historical reports. The exact committed-build launcher smoke
returned HTTP 200 for the guide and learner home, HTTP 404 for
`/developer/acceptance` on the production-mode server, and confirmed the
`25d7fe4` build SHA and loopback-only binding.

Exports include the build/session/tester/device/browser/environment metadata,
per-case outcome and all observation fields, general feedback, category-based
bug/improvement groups, unresolved cases, and an explicitly non-inferred
triage summary. Comments are kept verbatim. Empty optional evidence references
are omitted from the grouped references array. Markdown values are escaped and
JSON has schema version `1.0`. Browser clipboard API, denied-permission
fallback, Markdown/JSON downloads, and share API behavior are covered by the
CI browser tests; actual Android share sheet behavior remains unverified.

## Validation and CI

Focused tests passed locally for `tools/Tests/AcceptanceCenterTest.php`,
Product Readiness, browser evidence, release-readiness contract, anonymous
learner privacy, ten-board learner HTTP, learner navigation, production
foundation, session configuration, security trust boundary, and production
quiz CSRF. Changed PHP/JavaScript/shell files passed syntax checks and
`git diff --check` passed. GitHub Chromium is the supported browser environment;
Playwright was not installed on Termux.

Intermediate Browser acceptance runs exposed export-field/Markdown assertions,
duplicate empty evidence references, and an unnecessary server request for an
empty note. These received targeted fixes; the empty-note check now runs in the
form before the server's defensive validation. A separate final regression
confirms the visible changed-build warning. The final run `39040070952` passed
52 tests with 0 failures and 4 project-conditional skips. No failed assertion
was removed to obtain that result.

The four baseline Chromium skips remain justified project splits:

1. Mobile study-resource navigation runs in the mobile project only.
2. The isolated developer question-editor workflow runs in the desktop project
   only.
3. The ten-board browser matrix runs in the mobile project only.
4. The viewport sweep runs in the mobile project only.

Each skipped case is exercised by its designated supported project; none is a
missing test setup.

The local PHP registrar received HTTP 403 from the public GitHub API rate
limit. The connected GitHub API reader independently returned the completed
run and all job records for run `39040070952`; their response bodies were
hashed, the resulting record passed `BrowserEvidenceService::validateRecord`,
and only that verified successful run was appended to the registry. Failed or
unverified retries were not registered as passing evidence.

The local `php tools/doctor.php --simulate` command exceeded its default 512 MiB
memory limit while constructing an existing large Doctor fixture. It wrote
only to an isolated temporary fixture. One Release Gates run reproduced the
limit in `DoctorSimulatorStateIsolationTest.php`, raising a Doctor P0. Its child
process now uses the Doctor CLI's existing bounded 1 GiB budget for the 90.7 MB
isolated question fixture; the repository-storage hash assertion remains
intact. The final Doctor run passes that test and reports P0=0/P1=0/P2=1/P3=2.
This changes the diagnostic test process limit, not the production PHP memory
setting. The same-device Acceptance Center launch does not require running the
local simulation.

## Remaining acceptance and release state

The committed human matrix remains the sole canonical checklist and all
`HUMAN_STATUS` values remain `NOT_RUN`. Playwright only verifies automation; it
does not complete human acceptance or TalkBack testing. No WCAG conformance,
owner privacy approval, live HTTPS/hosting evidence, or academic signoff is
claimed. The formal Product Readiness job must continue to fail while genuine
owner/provider, human, live-host, and academic requirements remain open.

The local preview does not test production hosting, HTTPS/certificates,
provider backup retention, support identity, approved privacy terms, or actual
Android screen-reader behavior. Those facts must be verified by the owner,
provider, and independent reviewers and recorded in the existing canonical
decision documents. No owner/provider/legal facts are inferred by this batch.

### Final evidence registration

```text
BROWSER_WORKFLOW_RUN_ID=39040070952
BROWSER_WORKFLOW_JOB_ID=117417551775
BROWSER_ARTIFACT_ID=12103429410
BROWSER_APPLICATION_COMMIT=28de92ad49e17fd1edaee33b2bc89ddbe5d0337d
CHROMIUM_PASS_FAIL_SKIP_BEFORE_AFTER=28/0/4 of 32; 52/0/4 of 56
DOCTOR_WORKFLOW_RUN_ID=39040469550
DOCTOR_JOB_ID=117418801002
DOCTOR_APPLICATION_ASSESSED_COMMIT=25d7fe493537c7393746fa671c7f59ebf85bdb59
SECURITY_PRIVACY_RUN_STATUS=6/6 PASS
TEN_BOARD_HTTP_STATUS=PASS
PRODUCTION_PREFLIGHT_AND_SMOKE_STATUS=PASS
TECHNICAL_RELEASE_GATE=PASS; RELEASE_READY_WITH_NONBLOCKING_DEBT
PRODUCT_READINESS_JOB_STATUS=FAIL as expected; 1 incomplete in-scope feature and 3 mandatory evidence groups not passed
PRODUCT_READINESS_JOB_ID=117418801158
PRODUCT_FEATURE_COMPLETE=false; real owner privacy capability remains incomplete
PRODUCT_LAUNCH_READY=false
ACADEMIC_SIGNOFF_STATUS=AWAITING_INDEPENDENT_REVIEW
```
