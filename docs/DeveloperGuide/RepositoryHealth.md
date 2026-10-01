# Repository Health

Repository Health is the canonical deterministic repository check used by
developers, CI, batch evidence tools, and the future developer dashboard.

## Run it

```sh
php tools/doctor.php
php tools/doctor.php --health
php tools/doctor.php --health --json
php tools/doctor.php --health --json --persist
```

The ordinary Doctor command runs the existing HayaDoctor-compatible static
Doctor path. `--health` runs the BoardPrep `RepositoryHealthEngine` against one
repository snapshot. `--persist` writes the canonical snapshot to
`storage/repository-health.json`; `--json` writes the complete machine-readable
result to stdout. Historical batch audits may consume this result, but the
health command does not depend on them.

The CI contract is intentionally small: exit `0` when no deterministic FAIL
exists, exit `1` when at least one deterministic FAIL exists, and exit `2` for
an invalid CLI invocation. WARN and REVIEW are visible but do not fail CI by
themselves.

## Result vocabulary

- `PASS` means the deterministic invariant is satisfied.
- `WARN` means the repository is valid but has a planning or maintenance
  signal, such as an active thin leaf.
- `FAIL` means a deterministic integrity contract is violated.
- `REVIEW` means semantic or editorial judgment is required; it is not a
  fabricated deterministic failure.

Every issue has a stable code, severity/status, category, board where known,
entity type and ID, evidence, and suggested action. The `action_queue` and
`affected_ids` fields make exact entities retrievable without grepping storage.
The developer page at `/developer/doctor` filters the bounded on-page queue by
severity, board, category, and entity type. `/api/doctor.php?health=1` exposes
the persisted JSON snapshot for tooling and future dashboard consumers.

The snapshot contains portfolio totals, per-board summaries, check ownership,
issue breakdowns, flashcard metrics, and the same action queue. It is refreshed
by a developer or CI action rather than on learner requests.

## Source-readiness expansion gate

The Criminologist source-first workflow uses the permanent
`SOURCE_READINESS` contract. Its 37 records live in
`storage/source-readiness.json`; the contract schema is
`storage/schemas/source-readiness.schema.json`. Each active leaf has one record
with resolved source IDs, study-material IDs, supported depth, allowed and
excluded scope, deterministic threshold deficits, evidence-bounded headroom,
and one of `READY`, `READY_WITH_LIMITS`, `REVIEW`, or `DEFER`.

The health snapshot exposes the records under
`board_summaries.criminologist.source_readiness`. A `READY` or
`READY_WITH_LIMITS` record is an authoring permission, not a quota. `REVIEW`
and `DEFER` leaves remain visible with exact reasons and cannot receive
automatic expansion questions. New Criminologist questions are checked by
`QuestionService` for readiness, depth, scope, source/study chain, structured
content, and the existing downstream validators; ordinary edits to existing
questions are not made dependent on this planning contract.

Validate the durable Batch641 evidence with:

```sh
php -d memory_limit=1024M tools/batch641-criminologist-expansion-audit.php --audit
```

The audit tool consumes the permanent services; it is not the owner of the
readiness schema or validation rules.

## Deterministic versus semantic checks

Structural references, schema contracts, eligibility mappings, card identity,
derivability, source/study linkage, calculation metadata contradictions, and
count/reachability invariants can be deterministic FAIL checks. Taxonomy
granularity, source quality, semantic duplication, and editorial fit remain
WARN or REVIEW signals unless a concrete structural contract is violated.

## HayaDoctor boundary

HayaDoctor supplies the reusable generic `Tools\\Doctor` contracts and static
Doctor implementation. BoardPrep-specific Repository Health remains the single
canonical health engine and composes the existing BoardPrep validators and
selection logic. The parent repository pins the HayaDoctor revision that
contains the contracts required by BoardPrep's Doctor classes.
