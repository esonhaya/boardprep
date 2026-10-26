# Batch 717 — First Qualified Reviewer Handoff

## Current state

- Existing Batch 695 proposals: 276; all remain noncanonical.
- Curated pilot: 20 proposals — `9124, 9125, 9126, 9127, 9128, 9140, 9164, 9200, 9204, 9210, 9228, 9238, 9250, 9280, 9290, 9302, 9320, 9348, 9360, 9378`.
- Independently verified real reviewer identities: **0**.
- Real reviewer grants, assignments, source findings, academic decisions, and imports: **0**.
- Qualified review still required for all 41 pilot citation rows.

No reviewer was contacted, created, qualified, assigned, approved, or imported by this batch. A grant is not provisioned until an accountable owner verifies a real person and uses the existing private-storage grant procedure. Developer/admin access alone grants no academic authority. There is no grant-management HTTP endpoint or stored onboarding-preflight state.

## Owner preflight

Complete and retain this checklist in the organization's approved protected operating record before changing the existing `academic-reviewer-authorizations` collection:

1. **Identity:** verify the person over a known direct channel and match them to an already authenticated BoardPrep account. Do not accept an account ID supplied as proof by the person.
2. **Qualification:** verify a current professional, academic, or appointment credential with its issuing institution or authority. Retain only its issuer/reference, verification date, and accountable owner reference. Do not copy identity documents or credential scans into BoardPrep.
3. **Relevant expertise:** confirm experience covers the proposed board and exact subject. The curated pilot covers LET and Architecture across six board/subject pairs:

   | Board | Subject | Pilot proposals |
   | --- | --- | ---: |
   | Architecture | Architecture Utilities and Construction | 4 |
   | LET | Early Childhood Education | 4 |
   | LET | Culture and Arts Education | 3 |
   | LET | Physical Education | 3 |
   | LET | Special Needs Education | 3 |
   | LET | Technical-Vocational Teacher Education | 3 |

4. **Conflicts:** ask about authorship, financial, institutional, supervisory, and personal conflicts for these proposals. Record a minimal conflict check and owner disposition outside learner data. Do not assign an item with an unmanaged conflict.
5. **Capability and scope:** choose exact registered `board_id`, `subject_id`, and only justified capabilities (`REVIEW`, `SOURCE_REVIEW`, `TAXONOMY_RECONCILE`, `STUDY_LINK`, `EDIT_PROPOSAL`, or `IMPORT`). A source capability does not imply academic approval. An academic approval does not imply import authority.
6. **Expiry and revocation:** set a short, explicit future expiry appropriate to the pilot. Recheck scope and qualification before renewal. For revocation, retain the grant with `authorization_status: REVOKED`, record the reason/reference in the approved protected operator audit log, and verify that access fails immediately.
7. **Provision and verify:** use only the private-storage procedure documented in [`ACADEMIC_REVIEW_WORKFLOW.md`](../ACADEMIC_REVIEW_WORKFLOW.md). Confirm active grant status, exact scope, and allowed capability. Test the denial boundary with an isolated synthetic fixture, not a real reviewer impersonation.

Do not create a default reviewer, use a developer/admin role as a qualification, or provision a real grant as a demonstration. The application derives active authorization from the existing expiring grant; this checklist does not write onboarding state.

## Prepare the assignment

Once the owner has completed the checks above, provide the reviewer with a restricted pilot link and only the board/subject assignments their verified scope covers. The reviewer uses **Pilot cohort** and their granted board/subject filter, then **Assigned to me** to resume their work. The current application supports reviewer self-assignment; it has no owner assignment-management route. Reads and preflight do not assign an item.

Before opening a proposal, confirm its B717 evidence version matches the current question. The inspector now shows the exact question version, rationale claims, citation rows, source identity and locator, inspected scope, machine evidence status, limitations, currentness warnings, and required human actions. It explicitly separates source reachability, identity confirmation, content examination, a machine claim candidate, qualified source review, and academic decision. A version or source-registry mismatch is a stop condition until evidence is refreshed.

For each of the 41 citation rows, the qualified source reviewer independently records identity/authority, the exact page or section, content examined, the claim it does and does not support, source scope/currentness, counterevidence, and limitations. A reachable URL, official document title, or B717 machine candidate is not an item-level finding. For Q9228, in particular, the source reviewer must resolve current policy applicability and the missing source support for “document observable facts”; the inspected policy passages do not answer that procedural claim.

The academic approver independently inspects the proposed answer and explanation, examination relevance, board/subject classification, adequate source support, and source currentness. LET and Architecture also require current examination-scope confirmation. The approval is tied to the exact proposal version and the system stores the authenticated reviewer, grant/scope, timestamp, confirmations, before/after state, evidence snapshot, and audit-chain digest.

The approval form now requires an explicit conflict acknowledgement before `APPROVE`. The reviewer confirms that any potential conflict has been disclosed to the owner and no unmanaged conflict remains. If that statement is not true, the reviewer must stop and request reassignment; the acknowledgement is not a substitute for owner conflict review. The confirmation is included in the version-bound decision event, and import preflight rejects an approval lacking it.

## Required pilot decisions and unresolved source work

- **All pilot items:** 20 exact-version academic decisions and 41 independent citation reviews remain pending.
- **Q9228:** DO 40 s. 2012 was examined by targeted OCR on printed pp. 2–7/PDF pp. 3–8; printed p. 7/PDF p. 8 was visually checked. The 2025 Revised Anti-Bullying IRR was separately examined on four pages but is not part of the original B695 source snapshot. DepEd Order 006 s. 2026 is metadata-only because its 21,580,650-byte PDF exceeds the 18,000,000-byte limit. A qualified reviewer must acquire and inspect the relevant official pages by a compliant bounded method, establish current applicability/local procedure, and decide whether the supplemental 2025 IRR should be formally cited. No answer is pre-decided. Sources: [DO 40 official order page](https://www.deped.gov.ph/2012/05/14/do-40-s-2012-deped-child-protection-policy/), [DO 40 PDF](https://www.deped.gov.ph/wp-content/uploads/2012/05/DO_s2012_40.pdf), [2025 Revised Anti-Bullying IRR](https://www.deped.gov.ph/wp-content/uploads/Anti-bullying-IRR-Clean-version-as-of-25-March-2025_1.pdf), and [DO 006 s. 2026 PDF](https://www.deped.gov.ph/wp-content/uploads/DO_s2026_006r.pdf).
- **Q9124–Q9127:** current detailed Architecture examination-program evidence is not established. The newest detailed official program found is June 2026 and is now historical. The October 5–7 2026 schedule entry was Naval Architects, not Architects. Keep all four items blocked for current scope confirmation. See the [PRC official downloads page](https://prc.gov.ph/downloads), [2026 examination schedule](https://prc.gov.ph/2026-schedule-examination), and [schedule index](https://prc.gov.ph/exam-schedules-menu).
- **PRC Resolution 20 s. 2025 Annex:** 16 pilot citation rows have page-context hashes from bounded HTTP range extraction. The full 18,103,170-byte PDF was not downloaded or hashed. A qualified reviewer must confirm exact Annex and item-to-page fit.
- **Other four of six scope-limited items:** Q9229–Q9233 still require per-item interpretation even where machine-resolvable portions were identified.
- **Taxonomy:** one exact registered pilot topic reference, 19 unregistered topic references, and 20 unregistered concept references. Candidate mappings remain recommendations; no taxonomy was activated.
- **Study links:** three exact published-section candidates and 17 missing pilot sections. No links were activated. Optional study enrichment does not establish source support.

See [`batch-717-evidence-verification.json`](batch-717-evidence-verification.json) for exact versions and source rows and [`batch-717-cohort-readiness.json`](batch-717-cohort-readiness.json) for all 276 proposal dependencies.

## Decision, revision, and import receipt

- Use `REQUEST_REVISION` when content needs a provenance-preserving change; the new version invalidates old source reviews and approvals. Use `REJECT` or `DEFER` with a reason where appropriate. Do not silently rewrite content.
- After each real source or academic action, verify the stored event shows the authenticated account, active grant and scope, exact proposal version, timestamp, status transition, rationale, evidence and chained digest. Client-supplied reviewer identity is ignored.
- A second, separately authorized importer must review the exact import preview and confirm one eligible proposal at a time. The importer cannot be the academic approver. Source/taxonomy changes, grant expiry/revocation, or a version change invalidate readiness and must block import.
- Synthetic decisions/imports remain test-only in disposable storage. They do not count as pilot progress.

The present handoff stops before real provisioning because no independently verified reviewer identity or owner qualification record was provided. The next legitimate action is an owner-completed identity/qualification/conflict check, followed by a narrowly scoped expiring grant and item assignments for the verified board/subject.
