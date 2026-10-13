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

## Integrating a batch

Commit coherent changes on the batch branch and run the relevant content,
learner-flow, Doctor, and release checks. Inspect branch ancestry and patch
overlap before integration; an older batch branch may already be represented
by the integrated history. Fast-forward `main` only after the branch is
verified and the `~/BoardPrep` worktree is protected by a recovery copy. Check
the remote `main` ref before any push, and use only an ordinary fast-forward
push after release verification.
