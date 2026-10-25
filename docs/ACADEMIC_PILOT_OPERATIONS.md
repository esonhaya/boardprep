# Academic Pilot Operations

## Current pilot package

The Batch 715 package selects 20 existing Batch 695 proposals for a first
qualified-human review pilot. It is available from the restricted workbench at
`/developer/academic-review?pilot=1`. Board and subject filters still apply to
the pilot-only queue, and the reviewer sees only scopes covered by their
current grant. The queue is paginated; the package is not a bulk decision or
import action.

The durable cohort and item-level machine-prepared evidence are in:

- `docs/reports/batch-715-pilot-cohort.json`
- `docs/reports/batch-715-review-readiness.json`
- `docs/reports/batch-716-pilot-review-packets.json`
- `docs/reports/batch-716-source-evidence-matrix.json`
- `docs/reports/batch-716-taxonomy-study-governance.json`

The `question_version` binds each dossier to the exact B695 proposal snapshot.
If the proposal is revised, the prepared dossier becomes historical context;
the reviewer must recheck evidence against the current version. Original
content, later revisions, rationales, and decisions remain separately
inspectable.

## Evidence states

Treat these as separate findings:

- `url_reachable`: the locator responded during a bounded check.
- `source_identity_confirmed`: the authority and source identity were checked.
- `content_examined`: relevant source content was directly inspected.
- `claim_support_candidate`: a machine-prepared pointer to content that may
  support the stated claim; it is not a correctness finding.
- `expert_source_review`: independent qualified source review, still pending
  until an authorized source reviewer records it.
- `academic_approval`: a separate qualified review of the question, answer,
  explanation, relevance, and classification.

Read every locator, quoted or summarized passage, scope warning, currentness
warning, and contradiction noted in the dossier. A reachable source, official
title, curriculum excerpt, or B695 annotation alone does not establish that
the proposed answer is correct. If the relevant page, PDF, annex, rule, or
item-level locator could not be examined, preserve the unavailable or
`CONTENT_NOT_EXAMINED` finding and request the source material or a better
locator. Do not fill missing evidence by inference.

## Reviewer qualification and assignment

An owner verifies the person's identity, qualification, appointment, and
conflicts outside this workflow, then provisions an expiring
`academic-reviewer-authorizations` grant using the existing private-storage
procedure in `docs/ACADEMIC_REVIEW_WORKFLOW.md`. Scope the grant to exact board,
subject, and capability values. Do not create grants for this pilot until a
real reviewer has been independently qualified. Developer/admin membership
is not academic authority. Keep import authorization separate from both
academic approval and source review; the importer must be a different
authorized account from the approving reviewer.

In the workbench, filter to **Pilot cohort**, choose the authorized board and
subject, then use **Assigned to me** to resume assigned work. The queue shows
the proposal state, source preparation signals, and unresolved taxonomy or
study mapping. Open a proposal to inspect its current version, original and
revised content, answer and explanation, source snapshots, limitations,
taxonomy candidates, study relationship, decision history, and next eligible
action. Reads do not create an assignment or decision.

## Item review sequence

For each item independently:

1. Assign it to yourself and start the version-bound review.
2. Inspect every cited source and decide the source finding separately. Record
   a precise locator, what was examined, what claim it supports, currentness,
   scope, and limits. Mark unavailable sources honestly.
3. Reconcile taxonomy only to a currently registered and board/subject-valid
   mapping. Similarity suggestions are candidates, not active taxonomy.
   Missing entries remain governance requests until that governance process
   approves them.
4. Resolve a study link only when the exact published section exists and fits
   the question. Related material is optional context; missing sections are
   not created or represented as exact support.
5. Record `APPROVE`, `REQUEST_REVISION`, `REJECT`, or `DEFER` with a reason.
   Approval is bound to the displayed proposal version and does not publish it.
6. For an approved, fully gated item, a separately authorized importer opens
   the exact preflight preview and confirms only that one item.

If an answer is ambiguous, sources conflict, an exam program is historical,
source scope is incomplete, or required evidence is unavailable, defer or
request revision. Do not approve merely to advance the queue.

## Import and recovery

The import preflight is read-only and reports specific blockers. An eligible
item still needs an individual CSRF-protected confirmation from a separate
import-authorized account. The import journal stages the canonical question
and optional exact study link. It prepares the link while the question remains
`draft`; the question's activation is the final learner-visibility boundary.
Before that boundary, a failed or recovered operation rolls back records that
the journal proves it owns. After an audited canonical activation, recovery
completes the journal and any recorded link idempotently.

The pilot package does not add real reviewer grants, decisions, approvals,
canonical questions, taxonomy entries, or study links. Verify the current
review state in the workbench before beginning a real pilot; all 20 items are
expected to remain pending until independently authorized people act.

## Batch 716 source recheck and packet export

The B716 matrix reconciles the 41 pilot citation rows with 12 current
registered source identities. Forty rows have a recorded content-examination
locator. The remaining row is the Q9228 DepEd Order 40 PDF: the official file
is scanned with no text layer, so its relevant policy content is explicitly
`CONTENT_NOT_EXAMINED` even though its identity and official-index entry were
checked. The official PRC Resolution 20 linked PDF's previously unexamined
pilot sections were inspected using bounded HTTP byte ranges.
That document is 18,103,170 bytes, so the complete binary was not downloaded
or hashed. Direct page text was examined for pages 1–12 and 65–66; the matrix
records the page and Annex label for each affected proposal and preserves the
document's title/Annex-label ambiguity. The inspection is exam-scope context,
not a verdict on any answer. All 41 citations still require independent
qualified source review and all 20 proposals still require academic review.
The official PRC calendar was directly rechecked on 2026-10-25 UTC. It lists
Architecture examination dates in January, June, and October 2026; the
October event had passed at retrieval, and the schedule index did not link a
2027 detailed subject program. These dates do not replace current scope
evidence for the four Architecture proposals. See the
[PRC schedule index](https://prc.gov.ph/exam-schedules-menu) and
[2026 examination schedule](https://prc.gov.ph/2026-schedule-examination).
For Q9228, the official [DepEd child-protection policy index](https://www.deped.gov.ph/deped-policies-and-child-protection-related-laws/)
was reachable and lists DO 40 (2012), DO 55 (2013), and DO 3 (2021). The
official DO 40 PDF remains scanned with no extractable text, and its
item-level procedural support is unresolved.

The governance worksheet rechecks current taxonomy IDs, existing B715
suggestions, and published lesson section IDs. It records 19 unregistered
topic proposal instances (17 distinct topic IDs), one exact registered topic
candidate, four existing candidate edges (three nonexact), 20 unregistered
concepts, three exact published section candidates, and 17 missing sections.
It creates no taxonomy record, lesson, study link, assignment, or review
decision.

The private inspector displays the supplemental rows beside the existing
source dossier. Its **Print review packet** action prints the visible proposal,
provenance, blockers and decision history; print CSS suppresses reviewer
account identifiers, forms, mutation actions, and import confirmation. The
JSON packets provide bounded per-item export with question version, question,
proposed answer/explanation, citation rows, taxonomy and study candidates,
selection rationale, and reviewer questions. Treat exports as restricted
proposal content and share only through the organization's approved reviewer
channel. The complete result and validation handoff is
[`batch-716-overnight-academic-handoff.md`](reports/batch-716-overnight-academic-handoff.md);
exact test commands and result evidence are in
[`batch-716-verification.json`](reports/batch-716-verification.json). See
[`reports/batch-716-reviewer-onboarding.md`](reports/batch-716-reviewer-onboarding.md)
for the owner checklist. No genuine account, grant, source adjudication,
academic decision, or import was added by B716.
