# BoardPrep Batch 727 — Dashboard performance and Smart Study Hub

## Outcome

Batch 727 adds a personalized Smart Study Hub to the existing dashboard and reduces dashboard processing for returning learners. Implementation is committed at `702e399a3b059edac530d63e2ca7f1a3a7253074`. Full Doctor ran against that exact application SHA and passed with P0=0 and P1=0.

The release readiness decision is `RELEASE_READY_WITH_NONBLOCKING_DEBT`. Doctor health is `REVIEW` because source traceability retains review signals; it reports no hard source defects or failed health checks. Real Chromium acceptance remains unexecuted on Android/Termux.

## Learner-facing change

The dashboard now presents up to three ordered Next Study actions. It gives recorded mistake review priority when saved history contains incorrect or skipped answers, then uses existing readiness recommendations, then fills remaining space with published Study Library and Flashcards browse actions. New learners see board setup and published study choices with a disclosure that progress begins after completed practice.

Actions are built from the dashboard’s already-loaded readiness and continuity data. Practice links are constrained to understood local routes, bounded question counts, practice mode, and existing board/variant query context. External, protocol-relative, malformed, or duplicate destinations are rejected. Existing active-session resume and latest-result cards remain separate; active-session continuation is explicitly same-session only.

Responsive action cards use a fluid one-column layout at phone widths, wrap long content, preserve visible keyboard focus, and retain touch-sized controls. Static contracts cover 320, 360, 390 and 430 CSS px. No rendered-browser acceptance is claimed.

## Study continuity and safeguards

The existing exam → results → mistakes → recovery → progress path remains on its established services. The isolated Batch 712 HTTP journey passed from a quiz miss through the Mistake Notebook, stored explanation, eligible retry and updated result summary. Full Doctor passed its ten-board HTTP journey for LET, CSE, CLE, NLE, PLE, CE, CPA, ME, REE and Architecture, preserving board/subject attribution and exact published study destinations. Notes CRUD/ownership and Flashcard replay/scope regressions passed; the Flashcard controller read-path test observed one canonical-bank read per request in three runs. No answer key, explanation, published material, academic relationship or scheduling rule was changed by this batch.

The existing historical snapshot-pinned B653, B704 and B705 mismatch evidence was not edited or relabeled in B727. Those archived checks were not rerun; current Full Doctor integrity and eligibility domains passed.

## Dashboard performance

The benchmark used the same isolated HTTP simulator and generated attempt shape at 0, 1, 25, 100 and 500 attempts, with one first request and seven warm requests per workload. The first request followed replacement of the fixture; the OS page cache was not cleared. Warm medians in milliseconds:

| Attempts | First request before → after | Warm median before → after | Change in warm median |
|---:|---:|---:|---:|
| 0 | 161.335 → 158.577 | 177.710 → 158.697 | −10.7% |
| 1 | 1099.517 → 171.271 | 1033.102 → 168.292 | −83.7% |
| 25 | 1067.820 → 178.186 | 1070.860 → 203.400 | −81.0% |
| 100 | 1163.554 → 241.001 | 1163.781 → 259.055 | −77.7% |
| 500 | 1812.918 → 590.618 | 1823.449 → 592.220 | −67.5% |

The final bounded optimization prevents an unregistered legacy/synthetic topic from building the full canonical question-to-topic index just to return a no-exact-link fallback. A read-only check found all 9,123 current canonical question topics registered in taxonomy. Registered-topic relationships remain covered by `AdaptiveStudyEvidenceTest`.

These measurements are local synthetic PHP request processing, not production network latency. PHP child memory, aggregate storage reads, and total canonical-bank reads were not instrumented. Raw and prepared dashboard output equivalence passed for all five attempt counts; full-scope readiness reuses summaries, while mixed-board readiness remains separately scoped.

## Verification and integrity

- Focused dashboard, Smart Study, readiness, mobile/accessibility, Notes, mistake recovery, Flashcard, Q6403 and source-only Q1536/Q1537 checks passed.
- Browser contract suite passed 8/8. Its Termux contract correctly blocks unsupported real-browser acceptance.
- Full Doctor exit 0; valid JSON; release gate PASS; P0=0, P1=0, P2=1, P3=2; 78/78 release flow tests passed.
- The P2 item is nonblocking source-traceability review (8 review signals; zero hard source defects and zero readiness failures). Two P3 items are planning signals for evidence-thinness and Architecture visuals.
- Current security, learner-flow and study-experience domains passed; the full release artifact contains the status of all 78 flow checks.
- Doctor storage requirement was 1,837,243,096 bytes; minimum observed was 2,571,911,168 bytes. Isolated fixture cleanup passed; four pre-existing session directories were left untouched.
- The complete Doctor JSON is preserved in `batch-727-full-doctor.json`; its SHA-256 is `46dd8c3a8c6d28dc1a48da08c8d0eb9595ff8bd703272e46e74c061aaaa843ba`.
- Canonical count remains 9,123; question, answer-key, eligibility, source, study-link, attempts, weakness, statistics, academic decision, source-review and Flashcard-exclusion hashes match the protected baseline. The three pre-existing untracked empty-JSON files remain byte-identical and unstaged.
- All 276 B695 proposals remain pending and noncanonical. No import or production learner mutation was performed.

## Browser and release limitations

The pinned Ubuntu workflow remains at `.github/workflows/browser-acceptance.yml` and uses `ubuntu-24.04`, Node `22.23.3`, and `npm run test:browser`. This Android/Termux ARM64 environment has no desktop Chromium executable. No binary was installed and no remote workflow was dispatched. Browser status is `PREPARED_NOT_EXECUTED`; no screenshots, traces or visual acceptance are claimed.

The Full Doctor result is a local technical gate. It is not human academic approval, browser acceptance, owner approval, or public-release approval. B695 academic review remains deferred.

## Commits

- Application implementation and tested SHA: `702e399a3b059edac530d63e2ca7f1a3a7253074`.
- Final documentation SHA is intentionally reported outside this self-committed document to avoid self-reference.
