# Batch 668 branch reconciliation

## Verified refs

- Batch667 starting branch: `batch667-github-visual-qa` at
  `c5e6369287d7cee2469621d7e5e81246e989001b`.
- `main` and `origin/main`: `0eb9704ba293e0aa3402af5d01a2d3392c005b73`.
- Shared merge base: `42bdfd7581f2b50bae8584707df9ffeab37fb055`.
- There is one main-only commit (Batch 625 Nursing expansion) and 71 commits
  unique to Batch667 after the merge base.
- The integration worktree is based on Batch667. Main, remote main, old tags,
  and existing dirty/untracked files were not modified.

## Integration disposition

`git merge-tree` predicts conflicts in:

- `app/Services/Quiz/Selection/QuestionPoolFilter.php`
- `storage/question-eligibility.json`
- `storage/question-reuse-metadata.json`
- `storage/questions.json`
- `storage/study-materials.json`
- `storage/study-question-links.json`

The Batch 626 lineage record reports 320 question-ID collisions. A spot check
of ID `4163` confirms that main and Batch667 assign that ID to different
board-specific canonical questions (Nursing versus CPA). The data changes
cannot safely be resolved by choosing either file or mechanically combining
the JSON. The content and dependent eligibility/reuse/study-link records need
owner-reviewed reconciliation and stable re-keying first.

No merge/cherry-pick of the main-only content commit was attempted. This is an
intentional deferral, not a claim that the integration branch includes that
newer Nursing content. Completed Batch666/667 UI and browser work is preserved
on the Batch667-derived branch; main remains untouched. The branch is not a
drop-in replacement for main until the canonical data collision is resolved.
