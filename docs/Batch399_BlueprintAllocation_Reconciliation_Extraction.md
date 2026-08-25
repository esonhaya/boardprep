# Batch 399 — Blueprint Allocation Reconciliation Extraction

## Objective

Reduce the responsibility concentration of `BlueprintAllocationReconciler::reconcile()` without changing its public contract or allocation semantics.

## Architecture

The existing reconciler remains the public boundary.

The deterministic phases are separated into focused collaborators:

1. `BlueprintAllocationTargetGuard`
2. `BlueprintAllocationTotalCalculator`
3. `BlueprintAllocationDeltaCalculator`
4. `BlueprintAllocationRequestFactory`
5. `BlueprintAllocationIncrementer`
6. `BlueprintAllocationDecrementer`

The incrementer preserves round-robin positive reconciliation. The decrementer preserves the existing non-negative reduction behavior and failure condition.

## Completion criterion

The extracted collaborators, production contract, production integration, full Quiz regression, and Doctor checks must remain green.
