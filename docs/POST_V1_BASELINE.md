# BoardPrep v1 Post-Release Baseline

This document is the maintenance starting point after the v1 release. Future
work starts from the local `boardprep-v1` branch or a later integrated `main`,
never from `boardprep-v1-rc`, the historical `pre-v1-main-backup`, or a
Batch-specific worktree.

## Release baseline

- Release content baseline: `4753c03c11d5808c2ea80a9ab414ff73bd767d32`
- Product identity: `BoardPrep v1.0.0` (`v1.0.0` remains immutable)
- Global canonical/effective/runtime: `8962 / 9422 / 9746`
- Native board counts: LET 1788; CSE 1553; Nursing 878; Criminologist 451;
  Civil Engineering 922; Mechanical Engineering 511; REE 842; Architecture
  484; Psychometrician 401; CPA 1132
- Repository Health: `REVIEW`, fail entities `0`
- Release gate: `PASS`, P0/P1/P2/P3 `0/0/1/2`
- Hard defects: source/study `0`, flashcards `0`, calculation contradictions
  `0`, duplicate effective `0`, double weighting `0`
- Safety invariants: Nursing dosage `24/16/8`; CPA CY2029 leakage `0`

The remaining review debt is nonblocking: conservative source-fit review,
evidence-exhausted content planning signals, the deferred Psychometrician
mental-health-law source review, and the Architecture visual-question
capability backlog.

## Fast maintenance audit

Run from a committed checkout with the repository's PHP runtime:

```sh
env APP_ENV=testing APP_DEBUG=false php -d memory_limit=1024M tools/doctor.php --health --portfolio --json
env APP_ENV=testing APP_DEBUG=false php -d memory_limit=1024M tools/doctor.php --release --json
APP_ENV=production APP_DEBUG=false php tools/doctor.php --preflight --json
APP_ENV=production APP_DEBUG=false php tools/doctor.php --smoke --json
php tools/Tests/RuntimeConfigurationTest.php
php tools/Tests/ReleaseReadinessContractTest.php
php tools/Tests/ReleasePackageTest.php
```

Use the permanent Doctor/Developer Health summaries as the source of current
state. The Batch657 projection remains a compact release-candidate audit
orchestrator; it is not a replacement health framework.

The machine-readable deployment profile is
[`config/deployment-profile.json`](../config/deployment-profile.json). Build a
production-only package with `php tools/release.php`. Snapshot mutable state
with `php tools/backup.php --output=/path/to/backup`; verify a non-destructive
restore plan with `php tools/restore.php --input=/path/to/backup
--destination=/path/to/restore --dry-run`.

## Future work categories

Each post-v1 batch chooses one primary category:

`BUGFIX`, `CURRENTNESS_UPDATE`, `OFFICIAL_SCOPE_UPDATE`,
`EVIDENCE_BACKED_CONTENT`, `FEATURE`, `MAINTENANCE`, `PERFORMANCE`, or
`SECURITY`.

Question expansion is justified only by new official scope, new authoritative
evidence, a true uncovered competency, a distinct proposition family, learner
evidence, or a validated visual capability. Under-10/under-20 counts and
arbitrary bank-size targets are not authorization.

## Deferred backlog

- Architecture visual-question capability: `POST_RELEASE_FEATURE`.
- Periodic currentness and conservative source-fit review: `MAINTENANCE`.
- Evidence-backed content expansion: `POST_RELEASE_CONTENT`, only when its
  evidence and proposition contract is complete.

Currentness changes should trigger a focused board review, not a full portfolio
reaudit. Existing readiness closure metadata remains authoritative, and closed
boards are not reopened by numeric thinness alone.

## Release handoff

Deployment, backup, smoke, and rollback instructions are maintained in
[`RELEASE_RUNBOOK_V1.md`](RELEASE_RUNBOOK_V1.md). Keep the previous release
reference and persistent-storage backup before deployment. Do not push a branch
or tag without an explicit release decision.
