# Development Workflow

## Primary checkout

`~/BoardPrep` is the permanent local development directory. Keep it on the
integrated `main` branch between batches. Create each batch as a branch in this
checkout, for example:

```sh
cd ~/BoardPrep
git switch main
git status --short --branch
git switch -c batch672-content-audit
```

Start from the verified integrated `main` tip. Do not automatically create a
`BoardPrep-batchNNN` worktree or another full clone. Use a separate worktree
only when a specific task needs concurrent code versions; record its purpose
and preserve existing worktrees until their unique changes and backups have
been checked.

## Learner data and tests

Before testing, record the status and hashes of mutable learner state, including
attempts, weakness, question statistics, flashcard state, sessions, and privacy
records. Tests that write these collections must use isolated temporary
storage or a disposable fixture. Never commit test-generated learner state or
replace existing learner files with branch copies. Preserve backups before
changing tracked data.

Do not use `git reset --hard`, `git clean`, force-push, or branch deletion as a
batch cleanup step. Keep unrelated staged, unstaged, untracked, and ignored
work intact.

## Evidence-reuse-first validation

Do not rerun checks for unchanged features just because a new batch has begun.
Reuse a prior result only when the record identifies its tested commit SHA and
the exact relevant production code, canonical content, fixtures/assertions,
dependencies, and runtime assumptions remain unchanged. No changed boundary or
new defect may invalidate the result. Ancestry by itself is not evidence.

For every check, report one of these states: `REUSED_VALID_EVIDENCE`,
`NEWLY_TESTED_PASS`, `NOT_RUN`, `DEPENDENCY_MISSING`, `FAILED`, or
`REVIEW_REQUIRED`. Reused evidence must name its original SHA, test or gate,
unchanged files/contracts, reason it remains applicable, and known limits. New
results must name the exact command, exit code, and tested SHA. Never describe
an older-SHA pass as a test of a newer commit.

Choose the narrowest test that covers the changed boundary. Lesson-only or
subject-only changes call for content, source, section-map, eligibility, and
learner-flow checks for the affected scope. Shared eligibility, scoring,
privacy, or session changes require the corresponding broader regression.
Run the Full Doctor release gate only when its relevant evidence is invalidated,
shared critical functionality changes, a significant integrity defect is
repaired, a candidate is being finalized for release, or an operation's
documented policy requires it. Never weaken mandatory release checks. A passing
focused test does not establish product launch readiness, human acceptance, or
live deployment.

## Integrating a batch

Commit coherent changes on the batch branch and run the relevant content and
learner-flow checks, applying the evidence-reuse policy above to Doctor and
release gates. Inspect branch ancestry and patch
overlap before integration; an older batch branch may already be represented
by the integrated history. Fast-forward `main` only after the branch is
verified and the `~/BoardPrep` worktree is protected by a recovery copy. Check
the remote `main` ref before any push, and use only an ordinary fast-forward
push after release verification.
