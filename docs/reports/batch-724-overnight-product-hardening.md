# BoardPrep Batch 724 — Overnight Product Hardening

## Outcome

B724 is technically complete on `batch-724-overnight-product-hardening`. The application implementation is tested at `371aee2ffc53a989253252d7a91b8c58c014e1af`; no application source changed after its Full Doctor run.

The learner-facing work stays focused on the active exam. Progress now names the exam mode and announces the current question position. Final submission gives truthful pending feedback, prevents repeated client submits, and restores a safe retry if Android returns the review page from its back-forward cache. The server remains responsible for finalization and success. The countdown now updates once per second instead of four times per second and catches up immediately when a learner returns to the page.

## Change evidence

| Area | Previous behavior and cause | Implementation | Verification |
| --- | --- | --- | --- |
| Exam progress | The progress control said “Practice progress” for both modes and exposed no question-position text to assistive technology. | Mode-specific accessible name and “Question X of Y” value text. | 20 view assertions, exam HTTP regression, browser assertion prepared. |
| Final submission | A missing response had no in-page pending/restore guidance, and repeated taps could send duplicate requests. | Accessible pending status, client duplicate guard, and a safe retry message when the page is restored. No success is claimed before the server responds. | Client test, exam session regression, Full Doctor. |
| Countdown | A whole-second display refreshed every 250 ms. | One-second UI update cadence, with immediate focus/visibility/pageshow catch-up. The deadline remains server-authoritative. | Deterministic timer test and exam lifecycle regression. |

The timer cadence moved from 4 scheduled callbacks per second to 1 (75% fewer scheduled UI updates). This is a source/test measurement, not a CPU, battery or latency benchmark. Other performance paths and memory were not benchmarked in B724.

An existing recommendation-context test failed once because it asked the selector for seven items from a three-row synthetic fixture while expecting exactly one result. The selector correctly applied its existing shortage recovery. The test now retains its seven-item URL assertion and uses a one-item eligible pool for the exact-topic/difficulty generation assertion. No production selection policy changed; the corrected test passes.

## Verification

Focused tests passed for exam session lifecycle, retries, deadline handling, exactly-once completion, results, ten-board learner journeys and adaptive eligibility; Notes, mistake recovery, Study Library, Flashcards, concurrency, dashboard reliability, recommendations, CSRF, learner isolation, developer/import gates and quarantine protections also passed. The browser workflow contract suite passed 8/8, its storage fixture passed, PHP/JavaScript syntax checks passed, and `git diff --check` passed.

Full Doctor ran against `371aee2ffc53a989253252d7a91b8c58c014e1af`: valid JSON, exit 0, release PASS, P0=0, P1=0, P2=1, P3=2, repository health REVIEW. Isolated fixture cleanup passed. Doctor required 1,837,243,096 available bytes; with the separately maintained 512 MiB reserve, the pre-run threshold was 2,374,114,008 bytes. Minimum sampled free space was 2,520,174,592 bytes. The complete 2,354,067-byte JSON is stored at `/data/data/com.termux/files/usr/tmp/batch-724-full-doctor-371aee2.json`, SHA-256 `6cc9aa10a901c55053084d86773ecddd09d64905658a8ae5a361728dfc7c441d`.

## Browser and historical evidence

Real browser acceptance is `PREPARED_NOT_EXECUTED`. The browser preflight passed loopback startup/shutdown but blocked browser launch on Android/Termux (`android/arm64`), where the pinned desktop Playwright driver is unsupported and no Chromium executable is available. No screenshot, rendered viewport, screen-reader or visual acceptance is claimed. Static/browser contracts specify 320, 360, 390 and 430 px cases for later supported execution. Remote CI was not dispatched.

B653, B704 and B705 remain historical snapshot expectation mismatches as documented and evidenced in the B723 verification. Their archived expectations were not rewritten. B723 verified the original artifacts and the current canonical/PDF pipeline; B724's current canonical hash, Q6403 quarantine and Q1536/Q1537 source-only restrictions passed. No new current integrity failure was found.

## Data and release limits

The canonical bank remains 9,123 questions. Canonical questions, answer keys, eligibility, sources, study links, attempts, weaknesses, question statistics, academic decisions, source reviews and Flashcard exclusions match the protected baseline. The three pre-existing untracked JSON files remain byte-identical. There were no production learner mutations, academic imports or approvals. All 276 B695 proposals remain pending qualified academic review.

Full Doctor is a local technical release gate only. Academic review, browser acceptance and public release approval remain separate. No push, merge, deployment or remote CI dispatch occurred.
