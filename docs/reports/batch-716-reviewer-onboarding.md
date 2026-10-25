# Batch 716 — Owner Checklist for Academic Reviewer Onboarding

## Current readiness

- Genuine reviewer identities independently verified for this pilot: **0**
- Real reviewer grants: **0**
- Real proposal assignments or decisions: **0**
- Source-review decisions: **0 of 41 citations**
- Academic approvals/imports: **0 / 0**

The application has no reviewer-grant HTTP endpoint. This is intentional. A
developer or administrator account is not a qualification, and signing into
the workbench does not grant academic authority. The checklist below is an
owner-operated process using the existing private grant collection described
in [`ACADEMIC_REVIEW_WORKFLOW.md`](../ACADEMIC_REVIEW_WORKFLOW.md). It does not
create a reviewer record or retain qualification documents.

## Readiness states

These are operational checklist states, not synthetic identities persisted by
the application. Only an owner-created `ACTIVE` grant becomes an application
authorization. Expiry is derived from its timestamp; revocation retains the
grant record with `authorization_status: REVOKED`.

| State | Owner action | Can review? |
| --- | --- | --- |
| `IDENTITY_UNVERIFIED` | Match the person to an existing authenticated account through a trusted, direct channel. | No |
| `QUALIFICATION_PENDING` | Check current professional/academic qualification, relevant subject expertise, and conflicts using an authoritative issuing body or institution. | No |
| `OWNER_VERIFIED` | An accountable owner has checked identity and qualification; retain only a minimal nonsecret evidence reference and verification date outside learner data. | No |
| `SCOPE_READY` | Select only the examination board, subject, and individual capabilities justified by the verified qualification; decide assignment limits and an expiry. | No |
| `ACTIVE_GRANT` | An owner provisions and records a future-expiring grant against the person's authenticated account. Recheck every request and assignment against that scope. | Yes, within active scope and capability |
| `EXPIRED` | The grant expiry has passed. Do not extend it without repeating owner checks. | No |
| `REVOKED` | Owner changes the retained grant status to `REVOKED` and records the revocation event in the organization's approved protected operator audit log. The application has no grant-management or revocation-history UI. | No, immediately |

Do not use a developer/admin label, an email domain, AI output, a source URL,
or a self-declared credential as proof of subject qualification. Do not store
credential scans, identity documents, passwords, or unnecessary personal
information in `qualification_reference` or the review collection.

## Owner provisioning checklist

Complete these checks before a grant is written:

1. **Identity:** verify the individual through a known direct channel; map to
   the account ID of an existing authenticated account. Never accept an
   account ID from the reviewer as proof of identity.
2. **Qualification:** verify the relevant professional registration,
   appointment, or academic qualification with its issuing body or institution
   and check that it is current. Record only a minimal reference, verification
   date, and accountable owner reference; keep source documents in the
   organization's approved records system, not application storage.
3. **Expertise and conflicts:** confirm the person's experience covers the
   exact board and subject and ask about authorship, financial, institutional,
   and personal conflicts for this cohort. Reassign conflicted items.
4. **Capability:** grant only needed values among `REVIEW`, `SOURCE_REVIEW`,
   `TAXONOMY_RECONCILE`, `STUDY_LINK`, `EDIT_PROPOSAL`, and `IMPORT`. A
   subject expert does not automatically become a source adjudicator or
   importer. Use different authorized accounts for academic approval and
   import; the import gate enforces that separation.
5. **Scope:** use exact registered `board_id` and `subject_id` values. Do not
   grant all-board access to solve queue visibility. Assign only items covered
   by that scope.
6. **Expiry:** set an explicit, reviewed future timestamp. Re-verify identity,
   qualification, scope, conflicts, and capability before renewal.
7. **Provisioning record:** using the existing private-storage operator
   procedure, create one `academic-reviewer-authorizations` record with the
   existing account ID, nonempty qualification type/reference, owner actor,
   server timestamp, expiry, and narrow scopes. Keep the storage directory
   outside `public/`; do not add a public or developer route.
8. **Verification:** confirm the authenticated account sees only its granted
   board/subject queue, can perform only its granted capability, and receives
   a denial outside scope. Use a synthetic fixture for testing; never make a
   real reviewer impersonation test.
9. **Revocation:** on expiry, conflict, changed scope, departure, or concern,
   set the existing record to `REVOKED`, record the action in the approved
   protected operator audit log, and verify subsequent review/source decisions
   and import preflight fail closed. Keep prior decisions attributed to the
   grant that was active when they were recorded.

The application grant contains a qualification reference, not a duplicate
credential dossier. The owner remains responsible for the real verification
and the organizational audit record. This batch has provisioned no real
grant.

## Pilot review packet procedure

Use the restricted nonproduction workbench at
`/developer/academic-review?pilot=1`, filtered to the grant's board and
subject. The exact 20-item packet export is
[`batch-716-pilot-review-packets.json`](batch-716-pilot-review-packets.json);
the per-citation provenance matrix is
[`batch-716-source-evidence-matrix.json`](batch-716-source-evidence-matrix.json),
and current taxonomy/study candidates are in the read-only
[`batch-716-taxonomy-study-governance.json`](batch-716-taxonomy-study-governance.json)
worksheet.
The private inspector can print the visible item packet. Print output hides
reviewer/account identifiers and mutation forms. Do not send an exported
packet through a public channel; it contains proposed question content and
source evidence.

For each citation, independently record identity/authority, exact locator,
material examined, claim fit, scope, currentness, limitations, and any
contradictory evidence through the existing `SOURCE_REVIEW` workflow. Then
record the question decision against the exact proposal version. A machine
page inspection or a completed-looking packet is not a human source finding,
an answer-key validation, an academic decision, or import authorization.

The PRC page-range inspection now covers the pilot's cited pages in the
Resolution 20 linked PDF, but the complete 18,103,170-byte PDF was not
downloaded or hashed. Every one of its 16 citation rows still needs a
qualified reviewer to confirm exact Annex/page mapping. The four Architecture
items also need current examination-program evidence; the cited June 2026
program is historical. Q9228 still needs a qualified reviewer to reconcile
the scanned DO 40 source and later applicable school-protection guidance.
The direct PRC recheck found January, June, and October 2026 calendar dates,
with no linked 2027 detailed subject program at retrieval. The official
[schedule index](https://prc.gov.ph/exam-schedules-menu) and
[2026 schedule](https://prc.gov.ph/2026-schedule-examination) are linked in
the source matrix. The official [DepEd child-protection policy index](https://www.deped.gov.ph/deped-policies-and-child-protection-related-laws/)
lists DO 40 (2012), DO 55 (2013), and DO 3 (2021), but the linked DO 40 PDF
is scanned and yielded no extractable text; the 2025 division memo returned
HTTP 502.
