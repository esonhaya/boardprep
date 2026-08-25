# Batch 399 — Blueprint Allocation Reconciliation Extraction

## Milestone

Extract the deterministic reconciliation phases from `BlueprintAllocationReconciler` while preserving its public API and allocation behavior.

## Production changes

- Isolate target validation.
- Isolate current allocation total calculation.
- Isolate target delta calculation.
- Isolate immutable `SelectionRequest` rebuilding.
- Isolate positive-delta distribution.
- Isolate negative-delta distribution.
- Keep `BlueprintAllocationReconciler::reconcile()` as the orchestration boundary.

## Validation

Targeted collaborator tests plus production-contract/integration tests accompany the extraction. Full Quiz regression and Doctor remain release gates.

## Non-goals

- No change to allocation policy.
- No change to blueprint distribution percentages.
- No UI changes.
- No database changes.
