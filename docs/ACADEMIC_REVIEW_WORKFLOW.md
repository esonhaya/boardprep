# Academic Review and Controlled Question Import

## Scope and safety boundary

The Batch 695 inventory contains 276 noncanonical proposals (question IDs
9124–9399). They remain outside learner pools until an authorized academic
review and a separate, individually confirmed import both succeed. The review
software does not create approvals, infer correctness from a source title, or
publish a proposal when the reviewer clicks **Approve**.

The workbench is available at `/developer/academic-review` only in a
nonproduction application environment with the existing account session
available. All routes are registered inside the nonproduction route block.
Every mutation also checks the authenticated account, its exact board/subject
scope, capability, CSRF token, and current proposal version. A form-supplied
reviewer ID or role is ignored.

The Batch 715 20-item pilot is a read-only, version-bound package exposed by
the **Pilot cohort** queue filter. It preserves the same board/subject scope
filters, assignment rules, and page bound as the full review queue. The
proposal inspector presents the current question beside a machine-prepared
evidence dossier and identifies whether the dossier still matches the current
proposal version. The dossier distinguishes URL reachability, source identity,
direct content inspection, candidate item-level support, expert source review,
and academic approval. It is not a review decision. See
[`ACADEMIC_PILOT_OPERATIONS.md`](ACADEMIC_PILOT_OPERATIONS.md) for the human
pilot procedure and package limits.

## Provisioning a qualified reviewer

There is intentionally no reviewer-grant web route. An application owner must
verify the person's real qualification and identity out of band, then provision
an `academic-reviewer-authorizations` record in the application's private
storage. Keep the evidence reference durable and non-sensitive; do not store
identity documents, passwords, or credential scans in this collection. Do not
use a developer/admin role as a qualification.

The record shape is:

```json
{
  "id": "grant-unique-id",
  "account_id": "existing-authenticated-account-id",
  "authorization_status": "ACTIVE",
  "qualification_type": "SUBJECT_EXPERT",
  "qualification_reference": "owner-verified credential or appointment reference",
  "authorized_by": "existing owner/operator account id",
  "authorized_at": "2026-10-25T00:00:00Z",
  "expires_at": "2027-10-25T00:00:00Z",
  "scopes": [
    {
      "board_id": "let",
      "subject_id": "english",
      "capabilities": ["REVIEW", "SOURCE_REVIEW", "TAXONOMY_RECONCILE", "STUDY_LINK", "EDIT_PROPOSAL"]
    }
  ]
}
```

Use only an account created through the existing authentication system. Grant
the narrowest board, subject, and capability scope needed, set a future
expiration, and preserve the grant record and its history when revoking it
(change its status to `REVOKED`; do not delete it). Import capability should be
provisioned separately to a second account. The importer cannot import a
proposal they approved. For Nursing approval, the reviewer must also have a
clinical/regulatory qualification type accepted by the service and confirm the
clinical-safety check. No grant or approval is preloaded by this change.

When `DB_DRIVER=json` or `DB_DRIVER=sqlite`, these review collections use the
private `APP_STORAGE_PATH` JSON storage. SQLite routes only the learner
collections already defined by the application. With MySQL, the existing
storage adapter persists the collections there. The application rejects a
review authorization store located under `public/`.

## Question review states

The primary state moves through:

```text
PENDING_REVIEW → ASSIGNED → IN_REVIEW → APPROVED
                                      ↘ REVISION_REQUESTED
                                      ↘ REJECTED
                                      ↘ DEFERRED
```

Deferred work can be assigned again. A requested revision is recorded as a new
version while the original proposal and earlier decisions remain in history.
Saving that revision returns the proposal to `PENDING_REVIEW`, clears mappings
and source decisions tied to the prior version, and requires a new independent
review. Decisions record the authenticated account, grant and qualification
reference, exact board/subject scope, version, rationale, source evidence,
server timestamp, before/after states, confirmations, and a chained event
digest.

Academic approval requires explicit confirmations of question accuracy,
answer, explanation, exam relevance, subject classification, source support,
and required currentness. It does not imply that the separate source review is
complete or that the question is import-ready.

LET and Architecture approvals also require the reviewer to confirm the
proposal against the current official examination scope or program. Nursing
approvals require the separate clinical-safety confirmation and an active
clinical qualification. These checks are validated again during import
preflight; a missing confirmation blocks both approval or import as applicable.

## Separate source and taxonomy decisions

Source findings are stored independently from the question's state. Reviewers
must distinguish URL reachability, source identity, direct content examination,
item-level support, currentness, and subject-expert approval. A supported
finding requires an exact locator, rationale, scope, and the identity/content/
currentness checks. The review captures the source registry record hash; a
later registry edit blocks import until the evidence is reviewed again. A
source reviewer whose scoped grant is no longer active cannot satisfy the
import gate.

Taxonomy reconciliation can map a proposal only to currently registered
board/subject/domain/topic/concept relationships. A proposed missing label is
recorded as `AWAITING_TAXONOMY_GOVERNANCE`; this workflow does not insert it in
active taxonomy. Taxonomy is required for import and eligibility. Study links
are separate: an exact link must resolve to a published lesson section in the
same board and subject. `UNRESOLVED` and `OPTIONAL_ENRICHMENT` remain explicit
without creating a link. Pending/staged links are hidden from learner-facing
study navigation.

## Controlled import

The proposal inspector shows the current canonical record (if any), proposed
content, source record snapshots and review history, taxonomy and study
findings, revision before/after content, warnings, and the exact import
preflight. Preflight is a read-only GET. It rejects an item unless:

- The latest proposal version has a current `APPROVED` decision with all
  required checks and a still-active matching reviewer grant.
- Each cited source has complete item-level review with a still-active matching
  source-review grant, and its registered source record has not changed.
- The reviewed taxonomy is valid and matches the proposal's board and subject.
- The proposal ID is unused by canonical content and learner history, and no
  exact normalized question stem already exists.
- The importer has an active `IMPORT` grant in the same scope and is not the
  academic approver.

An authorized importer must inspect the exact canonical question preview and
submit a CSRF-protected confirmation for that individual proposal. The signed
preview token binds the importer, question payload, and proposal version.
Approval by itself never imports.

JSON collections are written through the existing lock and atomic-replacement
storage implementation. The workflow serializes mutations under a private
lock and journals the staged question and optional study link. It prepares the
exact approved link while the canonical question remains a draft, then
activates the question as the final learner-visibility boundary and records
the complete import audit. Failed operations roll back owned staged records.
If a process stops mid-import, the next authorized import submission recovers
the pending journal before proceeding; recovery never deletes records it cannot
prove belong to that operation. Repeated submissions and repeated recovery are
idempotent. Existing SQLite learner collections remain behind the same
`StorageRouter`; tests exercise JSON and SQLite persistence in isolated
storage.

## Verification and current cohort

Use the focused tests listed in `docs/DEVELOPMENT_WORKFLOW.md`, including
`tools/Tests/Batch714AcademicReviewWorkflowTest.php`. Synthetic approvals and
imports are created only in a disposable fixture. No real Batch 695 proposal is
approved or imported by the test. The B695 original checkout is preserved and
is not a storage source for the live workbench; the recovered proposal records
and their provenance are read from the existing B713 integration artifacts.

At the Batch 714 technical milestone, all 276 real proposals are expected to
remain pending until actual qualified humans review them. That is not a
technical failure and must not be reported as academic approval.
