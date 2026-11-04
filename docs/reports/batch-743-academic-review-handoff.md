# Batch 743 — Academic review handoff

Prepared 2026-11-04 from the repository's current, read-only evidence snapshots. This is an evidence index and reviewer checklist. It does not make an academic finding, approve a proposal, grant reviewer access, or authorize importing content.

## Current evidence inventory

| Evidence | Current count/status | Snapshot SHA-256 |
| --- | ---: | --- |
| Batch 713 cohort disposition | 276 proposals, question IDs 9124–9399; 276 pending independent academic review and item-level source review; 0 canonical ID matches; 0 exact stem matches | `a8a33dbe48a04fafdf89e6c9ecb78213d92e2ba0dd38ff941c763aac1f2611b9` |
| Batch 716 pilot packets | 20 exact-version pilot proposals; 20 `PENDING_REVIEW`; 20 `NOT_APPROVED`; 0 real approvals; 0 real imports | `1136340d87a136c9ac204044a7d0d10bb332b9fc8a1f93a1de5c3450f08d4a60` |
| Batch 716 source evidence matrix | 41 citation rows across 12 unique sources; 40 content-examined flags; all 41 qualified source reviews pending | `2b30edc3125c35f3e35e609968048f178000480e16c022f4cae6812df624af8c` |
| Batch 717 reviewer handoff | Detailed version, source, scope, conflict, review, and import controls | `a0f4c57e0a4a7d53308846c0ffbccc815826de35ee0b844a7eeffaf1490d55d9` |
| Current canonical question bank | 9,123 records; no proposal promotion recorded in the cohort snapshot | `73cdc775cd7240b57898868d67902d833db1171f947c8dcc89081ddaf46fb4be` |
| Current eligibility mapping | Snapshot unchanged from the cohort evidence | `611f01ca29421c0c890c351c503ab262e87bc551269d78427b9a0889260933a4` |
| Current source registry | 330 records; 12 sources referenced by the pilot | `7c640636dcd3ddd0f865106ba4506a5af5dcd4a02a2074c065f695da5ef2c271` |
| Current study-question links | Snapshot unchanged from the cohort evidence | `0e0aebad84afd09e38e477f277bb8086e52eb9b4a2e591c6de41118060a5663d` |

The pilot covers 4 Architecture proposals and 16 LET proposals across Early Childhood Education (4), Culture and Arts Education (3), Physical Education (3), Special Needs Education (3), and Technical-Vocational Teacher Education (3). Exact question IDs: `9124, 9125, 9126, 9127, 9128, 9140, 9164, 9200, 9204, 9210, 9228, 9238, 9250, 9280, 9290, 9302, 9320, 9348, 9360, 9378`.

Across the wider 276-item cohort, the current disposition snapshot lists 258 missing exact study sections, 270 unregistered proposed topics, and 276 unregistered proposed concepts. These are integration dependencies, not automatic academic rejections. No topic, concept, study link, eligibility mapping, question, or answer key was activated for this handoff.

Storage also contains 37 historical academic decision rows, 100 source review rows, and 8 source-review adjudication rows. They are not evidence that these 20 exact-version pilot packets were approved. The current proposal workflow collection has zero rows. Reviewer grants are held in private application storage; this repository snapshot does not establish whether a qualified reviewer has a current grant. An owner must verify identity, qualification, scope, expiry, conflict handling, and assignment before access is provisioned.

## Reviewer assignment and evidence procedure

Use the existing [Batch 717 reviewer handoff](batch-717-reviewer-handoff.md), [Batch 716 evidence matrix](batch-716-source-evidence-matrix.json), and [Batch 716 pilot packets](batch-716-pilot-review-packets.json). Before review, independently recompute the packet and source fingerprints; any question version or source snapshot mismatch makes the old evidence stale and requires refresh.

For each assigned proposal, qualified reviewers should record:

1. The exact question ID and proposal version reviewed, reviewer identity, qualification and evidence, scoped authorization, assignment, conflict disclosure, and review date.
2. The exact source identity and version, retrieval date, page/section/table locator, content actually examined, claim supported and unsupported, source limits, currentness, and counterevidence checked.
3. Independent answer correctness, distractor quality, ambiguity, explanation support, calculations and unit handling where applicable, board/subject fit, current examination scope, and recommended changes.
4. Any duplicate candidate and how it was checked against the current bank and other proposals. A similarity signal is not a duplicate decision.
5. A version-bound decision (`APPROVE`, `REQUEST_REVISION`, `REJECT`, or `DEFER`) with rationale and evidence; revision must invalidate older reviews. Approval and import remain separate capabilities and actions.

Do not infer support from a reachable URL, title, machine match, or author-reported citation. Preserve unknown and partial support explicitly. Do not write proposed changes into canonical files as part of review.

## Specific handoff flags already recorded

- All 20 pilot proposals still require exact-version subject-expert review, and all 41 citation rows still require independent qualified source review.
- One of the 41 citation rows lacks a content-examined finding. The Batch 717 handoff identifies the outstanding DO 40 s. 2012 source work for Q9228, including current applicability and the unsupported procedural claim; the reviewer must inspect the applicable official source and decide source fit independently.
- Q9124–Q9127 rely on a June 2026 Architecture examination program that the Batch 717 snapshot records as historical. Recheck current official examination scope before any scope approval.
- The Batch 717 handoff records bounded page-context extraction for 16 PRC Resolution 20 s. 2025 Annex citation rows, but not a downloaded and hashed full PDF. Verify the exact Annex and item-to-page fit independently.
- The reviewer must resolve the recorded missing/unregistered taxonomy and study-section dependencies through the authorized governance path. Do not infer approval or create mappings from this handoff.

These flags are inherited from the hashed historical review evidence; recheck official source status at the time of human review. This report does not extend or replace the Batch 717 research.

## Human ownership still required

Academic review status is **PENDING**. Owner action is needed to identify qualified subject and source reviewers, verify conflicts and scope, make assignments, and provision narrowly scoped expiring grants through the established private-storage procedure. There is no evidence here of reviewer assignment, owner authorization, academic approval, or import authorization. All proposed academic changes must remain proposals until the separate human review and import controls complete.
