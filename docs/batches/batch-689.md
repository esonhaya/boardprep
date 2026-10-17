# Batch 689 — Product readiness, mobile accessibility, and release evidence

## Execution record

```text
BATCH=689
START_TIME=2026-10-17T06:59:00Z (first baseline observation; approximate)
END_TIME=2026-10-17T07:39:00Z (report commit/push immediately followed)
ELAPSED_MINUTES=40 (approximate from first baseline observation)
HEAD_BEFORE=5ba1d249ffc062db0e345f2f3cf72f67e7e5c052
HEAD_AFTER_APPLICATION=f9fc2d0e44fa54a2403fe3548aa0837a2526c837
HEAD_AFTER_DOCUMENTATION=final Batch 689 report commit (exact SHA in final handoff)
ORIGIN_MAIN_AFTER=final Batch 689 report commit (exact SHA in final handoff)
STORAGE_FREE_BEFORE=about 8.5 GiB
STORAGE_FREE_AFTER=8.4 GiB at final pre-report check
STORAGE_FLOOR_STATUS=PASS; stayed above 1.5 GiB
CANONICAL_QUESTIONS_BEFORE_AFTER=8,962 / 8,962; identities and content preserved
PROTECTED_STATE_STATUS=PASS; protected manifest 59/59
QUESTION_USAGE_DELTA_STATUS=PASS; preserved the pre-existing 19-row usage-counter delta
HAYADOCTOR_PIN_STATUS=PASS; dc4fcda730d9a922f726593746e34ca0c9c0e890
UNRELATED_WORK_PRESERVED=PASS; initial modified questions file, untracked paths, two stashes, recovery data, and historical archives preserved
PUSH_STATUS=FAST_FORWARD; application and evidence commits pushed normally
CLIPBOARD_STATUS=FAILED_VERIFICATION (Termux commands present; clipboard readback returned 0 bytes)
```

The report commit is the final documentation-only batch commit. Its SHA is
recorded in the batch handoff and Git history; embedding a commit's own SHA in
its content would change that SHA.

## Product Readiness browser evidence

```text
PRODUCT_BROWSER_STATUS_BEFORE=COMMIT_MISMATCH
PRODUCT_BROWSER_RELATION_BEFORE=unrecognized as an ancestor in a shallow Product Readiness checkout
PRODUCT_BROWSER_STATUS_AFTER=BROWSER_EXECUTED_PASS
PRODUCT_BROWSER_RELATION_AFTER=DOCUMENTATION_ONLY_DESCENDANT
PRODUCT_BROWSER_ROOT_CAUSE=Product Readiness checked ancestry with actions/checkout's default shallow history; CI-generated manifests and reports also landed in the application worktree and could make its integrity inventory dirty
CI_CHECKOUT_FIX=Product Readiness checkout uses fetch-depth: 0
CI_ARTIFACT_ISOLATION_STATUS=PASS; manifests, readiness JSON/logs, Playwright reports, screenshots, summaries, and diagnostics use a dedicated directory under RUNNER_TEMP
```

The Batch 688 assessment commit `b994c2e98edb55f58f396533aa55da8489bafcad`
followed the verified application commit `614aa36990c4ae792e2a0fd7f2fb257f4aa3f4fd`
through documentation-only changes. The evaluator had treated the missing
shallow ancestry as a mismatch. It now fails closed when history is shallow or
unavailable, recognizes a verified documentation-only descendant, and
continues to reject application-changing descendants as `STALE`, unrelated
known commits as `MISMATCH`, modified application files as `DIRTY_WORKTREE`,
unverifiable attestations/history as `UNVERIFIED`, and failed/cancelled runs as
`FAIL`.

Regression fixtures cover full and shallow history, exact evidence,
documentation-only and application-changing descendants, generated files
inside and outside the repository, dirty source, failed and cancelled runs,
bad GitHub attestations, and unrelated commits. The successful current run
`39013447763` verified application commit
`f9fc2d0e44fa54a2403fe3548aa0837a2526c837`. Product Readiness on
`ec84fa9d7c79e5777ca497b0864b406b5c35e95f` confirms the documentation-only
relationship and an empty dirty application-path list.

An initial dispatch, `39013197791`, exposed an invalid workflow expression:
`runner.temp` is unavailable in job-level `env`. The evidence-directory setup
was moved into a runner step which writes the path to `GITHUB_ENV`; the
corrected workflow passed on `39013276294` and the final application run
`39013447763`. Artifact uploads and exact commit attribution remain enabled.

## Mobile usability and browser evidence

```text
MOBILE_ISSUES_FOUND=1 confirmed privacy deletion confirmation target below 44 CSS px
MOBILE_ISSUES_FIXED=1; label uses a 44 px minimum target, consistent spacing, and a non-shrinking checkbox
MOBILE_VIEWPORT_RESULTS=PASS at 320x640, 360x800, 390x844, 430x932, and 768x1024
KEYBOARD_ACCESSIBILITY_STATUS=Automated Chromium checks passed for visible focus, keyboard use, labels/names, selected answers, alerts, and result/review navigation
SCREEN_READER_STATUS=NOT_RUN; no screen-reader acceptance or WCAG conformance claim
CHROMIUM_PASS_FAIL_SKIP_BEFORE_AFTER=26/0/4 of 30; 28/0/4 of 32
BROWSER_WORKFLOW_RUN_ID=39013447763
BROWSER_WORKFLOW_JOB_ID=117340597446
BROWSER_APPLICATION_COMMIT=f9fc2d0e44fa54a2403fe3548aa0837a2526c837
```

The added browser assertions cover viewport overflow and clipped primary
controls, usable touch targets, form labels, keyboard focus, answer selection,
result/review paths, simulator controls, flashcards, privacy actions, empty and
error states, and long content. No scoring, simulator timing, eligibility,
session recovery, or adaptive behavior changed. The browser artifact is
`boardprep-chromium-visual-qa` (artifact ID `12093507784`); it remains hosted by
GitHub Actions rather than copied into protected application storage.

The four conditional skips are intentional project splits, not missing setup:

1. Mobile study-resource navigation runs in the phone project only.
2. The isolated developer question-editor workflow runs in the desktop project only.
3. The ten-board learner browser matrix runs once in the phone project.
4. The five-size viewport sweep runs once in the mobile project.

Their behaviors are exercised in the designated supported project. The run
therefore reports 32 cases, 28 passed, 0 failed, and 4 reasoned skips.

## Human acceptance handoff

`docs/HUMAN_TESTER_READINESS.md` remains the single canonical 12-case checklist.
Its routes, labels, and actions were checked against the UI. It separates
`AUTOMATED_PASS` from `HUMAN_PASS`, `HUMAN_FAIL`, `BLOCKED`, `NOT_RUN`, and
`NOT_APPLICABLE`, and requires device/browser/viewport, build SHA, tester, date,
environment, observations, and evidence references for a human result.

The Android same-device preview uses a committed production-mode fixture,
copies tracked storage to an isolated temporary directory, uses temporary PHP
sessions, and binds to `127.0.0.1`. It does not operate on canonical question or
learner-state storage.

```text
HUMAN_MVP_CASES_PASSED=0
HUMAN_MVP_CASES_NOT_RUN=12 (MVP-01 through MVP-12)
HUMAN_ACCEPTANCE_STATUS=NOT_RUN
```

All twelve cases have automated coverage recorded as `AUTOMATED_PASS`; this is
not human acceptance and did not change their `HUMAN_STATUS=NOT_RUN` values.

## Privacy and hosting

```text
PRIVACY_APPLICATION_STATUS=PASS; anonymous disclosure, session-scoped export, CSRF, deletion confirmation, session rotation/isolation, preserving other learners, and deletion limitations verified
PRIVACY_OWNER_APPROVAL_STATUS=AWAITING_OWNER_APPROVAL / NOT_APPROVED
LIVE_HOSTING_EVIDENCE_STATUS=NOT_VERIFIED
```

The focused privacy HTTP regression and all six GitHub security/privacy jobs
passed. No application privacy defect was demonstrated. The owner/provider
checklist in `docs/OWNER_PRIVACY_AND_HOSTING_DECISIONS.md` remains bounded to
real operator/contact, approved notice, learner/log/backup retention and
deletion scope, hosting provider/region/runtime, HTTPS/certificate and secure
cookies, storage permissions, and session isolation. It invents no operator,
domain, provider, retention period, or privacy approval. Application deletion
does not promise removal from provider backups or logs.

## Formal readiness and release evidence

```text
DOCTOR_P0_P1_P2_P3=0 / 0 / 1 / 2
DOCTOR_WORKFLOW_RUN_ID=39014105638
SECURITY_PRIVACY_STATUS=6/6 PASS (run 39014105638)
TEN_BOARD_HTTP_STATUS=PASS (run 39014105638)
PRODUCTION_PREFLIGHT_STATUS=PASS (run 39014105638)
PRODUCT_READINESS_JOB_STATUS=FAIL as expected for genuine external blockers (run 39014105638)
TECHNICAL_RELEASE_GATE=PASS; release decision RELEASE_READY_WITH_NONBLOCKING_DEBT
PRODUCT_FEATURE_COMPLETE=false
PRODUCT_LAUNCH_READY=false
ACADEMIC_SIGNOFF_STATUS=AWAITING_INDEPENDENT_REVIEW; no content approval claimed
```

The formal Product Readiness job reports one incomplete in-scope feature,
`Data transparency and deployment-specific privacy terms`, and blocking
capability `BP662-CAP-001`. Three mandatory evidence groups remain unpassed:

1. Real human execution of MVP-01 through MVP-12.
2. Owner-approved, deployment-specific privacy terms, contact, retention, and
   deletion process.
3. Evidence from the actual live host for HTTPS, provider runtime, session
   cookie, storage permissions, and routes.

The technical release gate is kept distinct from feature completeness and
launch readiness. The P2/P3 Doctor findings remain nonblocking debt. Academic
review/signoff remains pending; the existing unapproved question-content and
duplicate-cluster reviews were not changed.

## Tests, integration, and next action

Focused PHP regressions passed for Product Readiness, browser evidence,
learner mobile accessibility, learner navigation, anonymous learner privacy,
the study library, and the ten-board HTTP journey. Changed PHP files passed
syntax checks; browser JavaScript passed syntax checks; `git diff --check`
passed. Chromium ran in GitHub Actions, not by installing a browser in Android
Termux. Application commit `09e6e41703d63b394f359493a7fee3edaa82daee`, follow-up
workflow fix `e0e670eb3288f9a7f4d771ec42b517817ae308a2`, human-status parser fix
`f9fc2d0e44fa54a2403fe3548aa0837a2526c837`, and evidence/handoff commit
`ec84fa9d7c79e5777ca497b0864b406b5c35e95f` were pushed by fast-forward.

```text
UNRESOLVED_BLOCKERS=human acceptance; owner/provider privacy and hosting evidence; independent academic signoff
NEXT_SAFE_BATCH=Owner/provider completion of BP662-CAP-001 evidence, followed by recorded real-device MVP-01..12 and independent academic review
```

Do not change human, owner, live-host, or academic statuses until their
respective evidence is supplied and reviewed.
