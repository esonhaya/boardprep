# Batch 696 Branch Integration Plan

## Branch boundary

- Main baseline: `f657b89629a0262d3ebca0ce99baaf551268acf8`.
- Batch 695 academic-review branch: `batch-695-academic-review` at `c3d6b217aed480ebda1c81c1900f907a749befe3`.
- Batch 696: `batch-696-study-depth`, derived from the verified main baseline; it was not based on Batch 695.
- No branch merge is part of this batch. Batch 695 IDs 9124–9399 remain pending independent academic review.

The following are record-level merge rules for a future reviewed integration. Never resolve a conflict by taking an entire file from one branch.

## File-by-file reconciliation

| File | Record key | Batch 696 contribution | Batch 695 preservation rule | Conflict handling |
|---|---|---|---|---|
| `storage/study-lessons.json` | `id` | 25 partial lessons with unique lesson IDs and source/scope metadata | Preserve all B695 lessons and pending review metadata | Union only distinct IDs. For same ID, compare complete section content, source review, scope, and status; keep neither side silently. Require an explicit editorial resolution. |
| `storage/study-materials.json` | `id` | 25 published material records paired to the new lessons and PDFs | Preserve B695 material IDs, source chains, exams, and review records | Union distinct IDs. For a collision, compare title, scope, source IDs, PDF pairing, and status field by field. Do not overwrite review state or broaden exam scope. |
| `storage/study-question-links.json` | `id`, then `(question_id, material_id, section_id)` | 24 explicit B696 links to verified lesson sections; no B695 question IDs are referenced | Preserve B695 question links and question-specific semantic review state | Union distinct link IDs only after verifying current eligibility and section existence. Reconcile duplicate pairs without replacing source IDs or review status. Do not attach one destination’s link to another subject placement. |
| `storage/sources.json` | `id` | No source registry records added in B696; lessons reuse existing records | Preserve new or updated B695 source evidence and its verification status | Keep disjoint source IDs. If an ID collides, compare URL, authority, version/date, status, notes, and content hash. Never upgrade `official_reference` or pending status automatically. |
| `storage/taxonomy/topics.json` | `id` | No taxonomy edits in B696 | Preserve B695 topic nodes and provenance | Union only non-colliding IDs. Require taxonomy review for an ID collision or incompatible parent. |
| `storage/taxonomy/concepts.json` | `id` | No taxonomy edits in B696 | Preserve B695 concept nodes and provenance | Union only non-colliding IDs. Compare topic parent, label, learning metadata, and aliases before any duplicate consolidation. |
| `public/study-resources/manifest.json` | `lesson_id` and path | Adds 25 distinct lesson PDF entries with generator version, lesson hash, and PDF SHA-256 | Preserve every B695 PDF entry and byte hash | Merge by lesson ID and path; verify file bytes against each branch’s own manifest before combining. Never regenerate or replace a B695 PDF during reconciliation. A same-path collision requires a new path or an explicit reviewed content resolution. |

## Reconciliation sequence

1. Fetch both branches and verify their expected commits and clean worktrees before preparing an integration worktree.
2. Keep question records and question eligibility separate from study-record reconciliation. Verify that B695 question IDs remain unapproved and that canonical question identities and usage counters do not change.
3. Export record IDs and hashes from both branch versions of the seven listed files. Classify distinct IDs, identical records, and conflicting same-ID records. Save the comparison before editing.
4. Merge disjoint lesson/material/source/taxonomy records using their record keys. Resolve duplicate relationships only when board, subject, topic, concept, source, lesson, and section all agree.
5. For each question link, re-run production eligibility for its target board-subject, verify that the lesson and section exist, and preserve `semantic_review_status`. A source URL or taxonomy match alone is not sufficient.
6. Combine PDF manifest entries only after checking each file’s SHA-256 and generator metadata. Preserve branch-specific PDFs and their hashes.
7. Run source-readiness, question-study link, PDF manifest, full question identity/usage, and review-status checks on the combined candidate. Have a reviewer resolve academic conflicts; do not mark any pending item approved automatically.
8. Commit the reconciliation on a dedicated integration branch. Do not push directly to main as part of the Batch 696 content branch.

## Current verification

Batch 696 has no B695 record IDs, did not alter source or taxonomy registries, and did not touch question or learner data. Main remains at the verified baseline in this checkpoint. Batch 695 remains on its separate academic-review branch.
