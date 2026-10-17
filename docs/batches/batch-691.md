# Batch 691 — owner feedback remediation and QA explorer

## Result

The real Android report supplied 12 comments across the 12 canonical MVP cases. Its outcomes remain 10 `PASS`, 1 `NOT_RUN`, and 1 `NOT_APPLICABLE`; none were changed by automation. The comments were individually sanitized into [the engineering findings matrix](batch-691-owner-findings.md). The private full report and local feedback data are not committed.

The primary application changes were committed in `8fb5be901f668be69ece2be4a283ca94ae0e58fb`; the final effective-subject resolver parity correction and regression were committed in `d40c04e4a3d4c18763ee41692f9414a3b07c1d47`. CI and test follow-ups were pushed separately as `302af4476568ba4cb3973d8ce5a749ffd38ce621`, `d57ef36844164de3deb679893aa3987d2aeeb0bd`, `b527250bab1174a39b86e8f43f4c34353052f5a6`, `679496ec69efa29cabccad1a3a27573b1ff881eb`, `f3e0af074f75a5038289d0bdc35dba1134c15667`, `5346bde23898008e13afba6d21dff8e716feb0e8`, `2d27111e47265d933b8971702ca22e4d2a55693f`, `fa082ab138a5289b2622a5904e1d47353c24adbe`, and browser-fixture corrections in `5960f42d015ccc4bc20ce3c78e001b90cd8aa701`. All were pushed by ordinary fast-forward integration.

## Question scope and selection

Question eligibility is now checked against the selected exam’s effective subject placement. Same-board rows with blank stored placement may use their canonical taxonomy subject; a reusable question with no verified destination subject fails closed. Explicit subject choices are hard constraints in practice and exam blueprint allocation. A final independent check verifies each question before it enters the learner session. A shortage preserves the chosen context and asks the learner to reduce the count or broaden a topic within that subject.

The new loopback-only nonproduction `/developer/question-origin` diagnostic reports question ID, board, requested and effective subject, taxonomy, selection/fallback path, and source references for the active quiz only. Production routes do not expose it. Hosted exploration uncovered that the storage-backed resolver used by the diagnostic returned blank for a same-board LET question whose per-question eligibility placement is blank, while the request-scoped index correctly resolved its canonical English taxonomy. The storage-backed resolver now uses the same board-limited fallback; the eligibility row and question content were not changed.

`QuestionSelectionScopeRegressionTest.php` passed for English, Mathematics, Values Education, exam allocation, adaptive selection, elementary/secondary LET variants, cross-board destination placement, and insufficient pools. It now asserts that both indexed and storage-backed eligibility resolve question 2771 to LET English. Cross-board, Common Core, multi-exam, exam simulation, multi-attempt, and ten-board HTTP regressions passed. The owner’s Math/source contradiction was not reproduced from current source metadata; independent semantic review remains open.

## Distractor and study-resource review

The full-bank distractor audit found 0 deterministic exact duplicate choices and 2,389 heuristic review cues: 772 repeated-distractor groups, 702 near-duplicate pairs, 905 option-length outliers, and 10 answer-format cues. Values Education contains three recurring distractor strings across Q5818–Q5852, but no within-question duplicate was confirmed. The cues are not academic decisions. No canonical question text, answer, explanation, or taxonomy was changed. The detailed audit output stayed in a temporary directory.

The library now distinguishes complete lessons and downloadable learning materials from primary references. One original Values Education lesson and searchable two-page PDF were added, with objectives, explanatory text, a worked classroom example, misconceptions, review patterns, linked questions, and source references. Its PDF hash matches the committed manifest. The lesson is source-checked, not independently academically approved. The PRC enhanced LET TOS and DepEd Learning Portal curriculum-guide record informed scope and instruction ([PRC TOS](https://www.prc.gov.ph/sites/default/files/2025-11%20published%20PROFESSIONAL%20TEACHERS.pdf), [DepEd curriculum guide](https://lrmds.deped.gov.ph/detail/15800)). No external PDF was copied into BoardPrep.

The repository-wide `php tools/generate-study-pdfs.php --check` currently reports a stale pre-existing Nursing lesson PDF: the tracked PDF matches its historical file hash, but generated content no longer matches the unchanged Nursing lesson. Its PDF and historical manifest entry were preserved; no unrelated lesson was regenerated. The new Values PDF independently matches its manifest hash, and the source/library tests pass.

## Learner setup, preferences, flashcards, and timer

Normal board navigation no longer displays inventory counts. A board/subject selection carries into practice setup with a compact context header, hidden duplicate selectors and submitted values, plus a Change link. A generic quiz route remains a valid board-selection entry point.

Validated anonymous-session preferences cover board, LET candidate level and specialization, Civil Service level, and subject interests. Candidate level is distinct from analytics-derived learning level. Explicit URL choices win over old preferences. Real account registration and sign-in were not added; the security plan for Batch 692 is [here](batch-692-authentication-plan.md).

Flashcard options are derived from effective board eligibility. Subjects and topics must belong to the selected board context, descendant filters clear when an ancestor changes, and the server rejects tampered combinations. Review history and card identity are preserved.

The browser workflow later exposed a preference-only failure: the controller passed validated Civil Service or LET candidate filters into `FlashcardService::dueSelection()`, whose service boundary accepted only board, subject, and topic. A saved CSE Professional or LET Elementary profile therefore inherited the correct board but received a temporary-unavailable message. The service now validates and accepts those internal variant filters; HTTP regressions cover both profiles, and the hosted journey verifies CSE board inheritance while explicit board context still wins. A separate structured-card visual fixture supplies its intended LET context explicitly so a prior saved CSE preference cannot contaminate that journey.

Untimed sessions hide and disable the duration field when JavaScript is active, and stale duration values are ignored server-side. Timed sessions require a whole number from 1 to 600 minutes. Invalid values return to the same board/subject configuration with the entered value and explanation intact. No-JavaScript behavior is covered in the hosted browser suite.

## Acceptance Center and QA explorer

The Acceptance Center dashboard now puts `Export My Feedback` first and directly opens the MVP-10 negative/error routes with a note that these URLs intentionally do not represent ordinary pages. Exports include saved unfinished drafts marked `DRAFT`, without counting them as submitted observations or human outcomes. Existing private comments and session history were not rewritten.

See the short [QA explorer guide](../SITE_EXPLORER.md). On supported desktop/CI Chromium:

```sh
npm run test:explore:smoke
npm run test:explore:full
```

The explorer uses deterministic selection, a safe same-origin route allowlist, one worker, bounded page/action/time limits, duplicate-state tracking, and isolated loopback production/developer fixtures. It records visited routes, forms, transitions, question eligibility/source metadata, responsive and accessibility cues, reproduction steps, and sanitized failure screenshots/DOM snapshots. Reports are generated in runner temporary storage. Automated evidence never writes a `HUMAN_PASS`.

The first explorer workflow definition was rejected before a job was created because it referenced the runner temporary directory at job environment scope. The workflow was corrected to create the directory in a runner step and pass it through the runner environment. Later runs exposed and corrected a flashcard filter test that waited for navigation after a client-side change, a mismatch between indexed and storage-backed same-board eligibility reporting, and the route count cap's failure to include synthetic active-quiz routes. The final run records each retry separately and enforces its overall visit and action limits.

## Validation

Local focused checks passed:

- Question scope, cross-board eligibility, Common Core mappings, multi-exam eligibility, LET variants, exam blueprint/shortage, and multi-attempt state.
- Production quiz/profile CSRF and invalid timer round-trip.
- Flashcard scope matcher, HTTP filter tampering, and integration behavior.
- Anonymous learner privacy, session rotation, deletion isolation, and ten-board learner HTTP journey.
- Distractor audit, content QA, acceptance center, study-source/library, learner-navigation, and product-readiness evaluator tests.
- PHP syntax checks for 46 changed PHP files, changed JS/browser `node --check`, and `git diff --check`.

`npm run test:explore:smoke` was intentionally not run in local Android Termux Chromium; it returned `UNAVAILABLE_LOCAL_CHROMIUM`. Hosted CI is the supported browser environment.

## Integrity and remaining blockers

- Canonical question count remained 8,962; IDs, keys, explanations, taxonomy, eligibility, and other educational fields match the repository base. The 19-row working-tree usage delta remained counter/timestamp-only and was not committed.
- Learner attempts/results/history/progress and pre-existing flashcard/statistics files were not used as test fixtures or staged.
- The existing Batch 685 protected-state checksum file was untouched. Its 168 recorded path hashes currently yield 163 matches and five known differences: the pre-existing question usage delta, this new study lesson, and three content files whose current committed lineage already differs from that older capture. No other protected learner-state checksum mismatch was observed.
- HayaDoctor remains pinned at `dc4fcda730d9a922f726593746e34ca0c9c0e890`. Existing stashes, recovery refs, SD archives, and unrelated untracked files were preserved.
- Product Readiness remains `FAIL` / not launch-ready. The hosted evaluator reported one incomplete feature and four mandatory evidence items not passed. Human acceptance, owner privacy approval, live-host HTTPS/operations evidence, and academic sign-off remain independent requirements. No approval or human outcome was fabricated.
- The new authentication foundation is session-scoped only. Values distractor cues and source-semantic correctness require independent academic review; additional board/topic lesson coverage remains incomplete.

## CI evidence

The final hosted explorer run `39060380608` tested application commit `fa082ab138a5289b2622a5904e1d47353c24adbe` with seed 691: PASS, 100 routes, 290 actions, 21 state transitions, 10 forms, all ten boards, ten board/subject combinations, and 50/50 independent question-eligibility checks. It found zero confirmed defects, review cues, page/console errors, unexpected HTTP errors, or failed internal requests. Its 59 untested entries are 27 unique routes outside the explicit safe allowlist. The sanitized report is artifact `12110399126`; see the [explorer guide](../SITE_EXPLORER.md).

The first Chromium run on `fa082ab` produced 57 PASS, 2 FAIL, and 7 conditional SKIP. It exposed two test-journey issues: the no-JavaScript timer case reused an unfinished quiz session between timer modes, and a structured LET flashcard visual URL did not override the CSE preference intentionally saved earlier in that test. The timer cases now use independent cookie sessions and assert the actual POST response plus resulting timer behavior. The visual route explicitly sets `board=let`. The rerun on `5960f42d015ccc4bc20ce3c78e001b90cd8aa701` completed with 59 PASS, 0 FAIL, 7 conditional SKIP, and no flaky or unrun tests. Run `39061585492` was verified from the GitHub Actions API and registered in `docs/release-evidence/browser-workflows.json`; Product Readiness classified the browser relationship as `EXACT` for that SHA.

The seven project-conditional skips are deliberate single-project checks: study-resource navigation (mobile project only), developer editor (desktop only), acceptance-center desktop flow (desktop only), acceptance-center responsive flow (mobile only), no-JavaScript timer fallback (desktop only), ten-board browser matrix (mobile only), and the five-width viewport sweep (mobile only). Each runs in its designated project; the paired skip avoids a duplicate execution in the other project.

The formal Product Readiness evaluation on documentation/evidence commit `17a5b0d0a48d020a6a4dc13902c7458b0bf965c8` recognizes the successful browser evidence as `DOCUMENTATION_ONLY_DESCENDANT` from `5960f42`. Its launch decision remains false: browser evidence and ten-board HTTP evidence pass, while real-device human acceptance, owner-approved privacy terms, and live-host deployment evidence are still unverified. The only failed critical capability check is owner-approved privacy terms. Privacy configuration remains fail-closed; no owner, provider, legal, academic, or production approval was invented. Release Gates run `39062494544` completed Doctor PASS (P0=0/P1=0/P2=1/P3=2), security/privacy 6/6 PASS, ten-board HTTP PASS, and production preflight/smoke PASS. The formal Product Readiness job correctly remained FAIL because the real external launch requirements are not met.

## Complete development report

The full terminal report is stored separately at [batch-691-final-report.md](batch-691-final-report.md). Human outcomes are reported from the supplied real Android session only. Automated browser checks did not change those outcomes or produce owner, legal, provider, or academic approval.
