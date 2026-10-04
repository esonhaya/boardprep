# BoardPrep v1.0.1

BoardPrep v1.0.1 is a production-hardening patch after the immutable v1.0.0
content release.

## Operator changes

- production identity is discoverable without Git metadata;
- the production-only package builder is deterministic and excludes mutable
  learner state, tests, logs, caches, secrets, and development worktrees;
- Doctor preflight validates runtime, configuration, canonical content, present
  mutable JSON stores, storage, database integrity, and release identity;
- backup/verify/restore procedures cover mutable JSON or SQLite state only;
- the non-destructive smoke command and safe public health endpoint remain
  available;
- clean-install, upgrade, rollback, and failure-injection checks are recorded
  in the release manifest and handoff.

Question content, eligibility, taxonomy, study content, flashcards, and
canonical counts are unchanged from v1.0.0.

## Compatibility

No persistent schema migration is required for this patch. Existing mutable
JSON/SQLite state is backward-compatible and must be backed up before update.
The v1.0.0 tag remains immutable.
