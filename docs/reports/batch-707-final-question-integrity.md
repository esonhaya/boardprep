# BOARDPREP — FINAL QUESTION INTEGRITY

Continuation checkpoint for `batch-707-alpha-finish-line`. The current application revision is technically tested; external release acceptance and human decisions remain separate gates.

## Revision and preservation

HEAD_BEFORE=`b823e785ab1b0f5f75546161ebbaf45555638d77`
HEAD_AFTER=`963606542af3984a1ba260e2f5b55a0b0b1c5232` (last verified application commit; later commits contain reports only)
HEAD_AFTER_IMPLEMENTATION=`963606542af3984a1ba260e2f5b55a0b0b1c5232`
BRANCH=`batch-707-alpha-finish-line`
WORKTREE=`/data/data/com.termux/files/home/boardprep-batch-706`
FREE_STORAGE=`45,150,208` bytes at Doctor temp path (`44,092 KiB` filesystem availability at final measurement)
REPORT_ONLY_COMMIT=separate documentation commit after implementation; the final tip is available from Git history.
COMMITS=`1afd50095ff211634b160b7e96e2e28e896f15e3`; `963606542af3984a1ba260e2f5b55a0b0b1c5232`; final reports committed separately.
TESTED_SHA=`963606542af3984a1ba260e2f5b55a0b0b1c5232` for the final dashboard/workflow evidence; core source, mapping, and selection regressions ran at `1afd50095ff211634b160b7e96e2e28e896f15e3`.
PUSH_STATUS=`NOT_PUSHED`

The verified remediation ancestry is present. Implementation commits are `1afd50095ff211634b160b7e96e2e28e896f15e3` (complete URL evidence and Q6403 finding) and `963606542af3984a1ba260e2f5b55a0b0b1c5232` (display the hold details). No new worktree was created.

Protected data remained byte-identical to the start checkpoint:

- Canonical questions: `73cdc775cd7240b57898868d67902d833db1171f947c8dcc89081ddaf46fb4be`
- Question-to-study links: `fa9daec05391e575e5029ac717a28969414e4c6df87c540bf1319d612bd59de6`
- Attempts: `e968fd3e8a391065ac2314ab9bcff932fc572cbe6024e16b40569b694bbed84b`
- Weakness/progress: `37517e5f3dc66819f61f5a7bb8ace1921282415f10551d2defa5c3eb0985b570`
- Question statistics: `4f53cda18c2baa0c0354bb5f9a3ecbe5ed12ab4d8e11ba873c2f11161202b945`
- Flashcard exclusions: `236975ba94ef72c223ebe7f78a4725088ca53b7c20395edb52f39e5d5e2f7717`

`storage/question-statistics.json` remains the pre-existing untracked file and was never staged. The dirty Batch 695 checkout, its refs/stashes, learner files, and HayaDoctor reference were not modified.

## Question and source integrity

CANONICAL_QUESTIONS=`9123` (all active)
INVALID_DIRECT_SOURCE_IDS=`0`
SOURCE_REGISTRY_RECORDS=`320` (319 URL-bearing; 1 internal editorial source without an external URL)
UNIQUE_REGISTERED_URLS=`310`
CURRENT_URL_SNAPSHOT=`319/319 source records; 310/310 unique URLs; CURRENT_COMPLETE`
UNVERIFIED_URLS=`0`

| Reachability status | Source records | Unique URLs | Interpretation |
|---|---:|---:|---|
| HTTP_REACHABLE | 236 | 229 | Reachable at the check time; not proof of claim support |
| ACCESS_RESTRICTED | 51 | 50 | Includes access restrictions; not classified as dead |
| CONFIRMED_404 | 3 | 3 | All three have zero current question/material references |
| REQUEST_ERROR | 26 | 25 | No reliable HTTP conclusion |
| HTTP_OTHER | 1 | 1 | Non-2xx response |
| REDIRECT_LIMIT | 1 | 1 | Inconclusive redirect chain |
| DNS_ERROR | 1 | 1 | Inconclusive; this is no longer IAS 21 |

The three remaining confirmed 404 records are `aasc-psa-500-currentness-review`, `deped-matatag-general-education-framework`, and `prc-lept-program-march-2026`; none is referenced by a current question or study material. No question has a confirmed-dead registered source. Twenty-six question IDs have no alternative registered source URL returning 2xx in this point-in-time check. Their statuses are restricted or inconclusive; this does not establish that the sources are unusable or that their claims are unsupported. The exact IDs and per-source evidence are in [batch-707-final-source-status.json](batch-707-final-source-status.json).

The 242 previously unchecked unique URLs were checked in five bounded batches of at most 50 representatives. Batch results included restricted and request-error responses, so each checker batch returned exit 2 by design. IAS 21 received exactly one requested retry and returned HTTP 200. It remains `REVIEW_REQUIRED` for claim support. The checker initially printed a hardcoded `WRITTEN_PARTIAL` label; the final persisted snapshot independently validates as `CURRENT_COMPLETE`, and the writer now reports complete versus partial dynamically. No claim-level support is inferred from reachability.

## Q6403 scoring containment

Q6403 says the exact transcription is `AB-0072.`; choice 3 preserves the final period and the explanation says punctuation must be preserved. The stored key is choice 2, `AB-0072`, so the punctuation-preserving choice currently scores wrong. A corrected fixture using choice 3 scores as expected, but the canonical key remains unchanged pending an authorized item-owner decision. The stored learner-attempt set contains zero Q6403 attempts; no historical score or saved session was rewritten.

Q6403 remains `QUARANTINED_PENDING_AUTHORIZATION`. Its existing Civil Service-only exclusion blocks new selection through normal, subject, random, retry/new-session, and timed/blueprint pools. The canonical board placement and other boards' eligibility remain unchanged. A Developer Question Repository integrity finding now exposes the question, board, `REVIEW_REQUIRED` state, and reason. Existing sessions are not retroactively altered.

Owner decision required: keep the current key and revise the quoted value/stem if the final period is not data, or authorize the exact key `AB-0072.` if punctuation is part of the required value. Do not apply either correction without that decision.

## Links, source references, and dashboard

EXACT_SECTION_LINKS=`1049`
BROKEN_OR_UNRESOLVED_SECTION_ANCHORS=`0`
KNOWN_PENDING_INDEPENDENT_REVIEW=`87`
EXPLICIT_REVIEW_REQUIRED=`4`
UNKNOWN_REVIEW_STATUS=`958`
INDEPENDENT_ACADEMIC_APPROVALS=`0`

All exact relationships remain structurally valid; the 87 pending, 4 review-required, and 958 unknown-status rows are not academically approved. Q1536/Q1537 remain without exact medication-section links. Q4004/Q4005/Q4006 remain demoted to material/source-only relationships. Q1883/Q1919 retain the Act No. 2031 source. Q1925/Q1926/Q1927 retain valid RA 11976 direct references and distinct BIR RR 7-2024 study relationships. There are zero invalid direct source IDs and zero current references to the remaining 404 URLs.

The existing Developer Question Repository now shows the current complete URL snapshot, per-source restricted/inconclusive/dead distinctions, affected-question drill-downs, unknown review status, and the actionable Q6403 hold. It performs no network access on page load. URL reachability remains separate from academic source approval.

## Focused validation

Core application/source regressions below ran on `1afd50095ff211634b160b7e96e2e28e896f15e3`. The final dashboard rendering, workflow evidence, and isolated browser fixture tests ran after the view change on `963606542af3984a1ba260e2f5b55a0b0b1c5232`. The aggregate browser contract run preceded the implementation commit on a working tree whose staged source/test changes became `1afd500`; its workflow files were unchanged.

| Command | Exit | Result |
|---|---:|---|
| `php -d memory_limit=1G tests/batch707-question-remediation-test.php` | 0 | Source IDs, source replacements, full snapshot, link quarantine, preserved data |
| `php -d memory_limit=1G tools/Tests/QuestionQualityDashboardRemediationIntegrationTest.php` | 0 | Current 319/319 snapshot, issue classifications, exact-link totals/anchors, visible Q6403 reason |
| `php -d memory_limit=1G tests/batch707-q6403-selection-path-test.php` | 0 | New normal/subject/random/retry/timed selection paths exclude Q6403 |
| `php -d memory_limit=1G tools/Tests/MultiExamEligibilityTest.php` | 0 | Scoped CSE hold, canonical identity, ten-board eligibility isolation |
| `php -d memory_limit=1G tools/Tests/QuestionStudyReferenceTest.php` | 0 | Source-only versus exact lesson/review relationships |
| `php -d memory_limit=1G tools/Tests/SourceStudyLibraryTest.php` | 0 | Source registry, provenance, study material relationships |
| `php -d memory_limit=1G tools/Tests/QuestionPoolFilterTest.php` | 0 | Pool eligibility and exclusion behavior |
| `php -d memory_limit=1G tools/Tests/BlueprintRequestExecutorTest.php` | 0 | Timed/blueprint request path |
| `php -d memory_limit=1G tools/Tests/QuestionScoreEvaluatorTest.php` | 0 | Scoring contract |
| `php -d memory_limit=1G tools/Tests/QuestionQualityDashboardContractTest.php && php -d memory_limit=1G tools/Tests/QuestionQualityIssueCatalogTest.php` | 0 | Dashboard counts and integrity finding grouping |
| `PYTHONDONTWRITEBYTECODE=1 python -B tests/source-url-checker-dedup-test.py` | 0 | Exact-URL dedup, alias evidence, age/timezone handling, snapshot status label |
| `php -d memory_limit=1G tools/Tests/BrowserWorkflowEvidenceTest.php` | 0 | Manual workflows, provenance and browser evidence contract |
| `npm run test:browser:fixture` | 0 | Private isolated browser fixture |
| `npm run test:browser:contract` | 1 | Four contract cases passed; server lifecycle fixture stopped with `No space left on device` while copying its private fixture |

The aggregate browser contract failure is environmental, not a browser acceptance result. No real browser ran. The ten-board HTTP suite was not rerun; the B706 report marked its prior evidence `REUSED_VALID_EVIDENCE`. This continuation freshly ran the multi-exam eligibility test and no learner HTTP code changed.

## External release gates

The existing `.github/workflows/browser-acceptance.yml` and `.github/workflows/release-gates.yml` both support `workflow_dispatch`. Their workflow evidence test passes. The browser workflow uses Ubuntu 24.04, PHP 8.3, Node 22.23.3, `npm ci`, then installs Chromium and runs the browser suite. The release workflow uses Ubuntu, PHP 8.3 with required extensions, 1 GiB PHP memory for Full Doctor, full Git history, ten-board HTTP, security/privacy checks, and production preflight/smoke. Both record the exact GitHub SHA and upload bounded artifacts. No workflow was added or pushed.

The current Full Doctor storage plan was measured without starting Doctor: fixture `92,398,334` bytes plus its `1.5 GiB` floor and `128 MiB` runtime reserve requires `1,837,228,798` free bytes. The Doctor temp path had `45,150,208` bytes available at final measurement, a `1,792,078,590`-byte shortfall. Full Doctor was not run locally. Hosted Doctor and real browser acceptance are `PREPARED_NOT_EXECUTED` until the branch is published by an authorized user.

## Go / no-go

QUESTION_STRUCTURE_READY=`YES` — canonical IDs, source identities, direct source references, exact link targets, and answer structures remain intact; 1049 exact anchors validate.
QUESTION_CONTENT_REVIEW_REQUIRED=`YES` — reachability does not verify claims, 26 questions have no current 2xx source alternative, and independent academic approvals remain zero.
Q6403_OWNER_DECISION=`REQUIRED`; keep its scoped new-selection exclusion.
ENGINEERING_ALPHA=`BLOCKED_PENDING_HOSTED_BROWSER_AND_FULL_DOCTOR`
PUBLIC_RELEASE=`NOT_APPROVED`

There are no remaining code changes identified by this continuation. The smallest next step is an authorized branch-only push, followed by manual dispatch of both existing workflows against the same branch tip. Separately, an authorized item owner must resolve Q6403 and qualified reviewers must address the outstanding content/source decisions. No push, merge, tag, or deployment was performed.

REPORT_PATH=`/data/data/com.termux/files/home/boardprep-batch-706/docs/reports/batch-707-final-question-integrity.md`
CLIPBOARD_STATUS=`UNVERIFIED`
