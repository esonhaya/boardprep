# Batch 397 — Blueprint Executor Extraction

## Milestone

Reduce the complexity of the blueprint execution orchestration without changing selection, recovery, deduplication, reservation, coverage, or result semantics.

## Production path

`BlueprintExecutor::execute()` remains the entry point.

The extracted collaborators own:
1. Request plan construction.
2. Request execution against the shared `SelectionSession`.
3. Coverage analysis and validation.
4. `BlueprintExecutionResult` construction.

## Non-goals

No changes to allocation rules, question selection, shortage recovery, deduplication, coverage semantics, or blueprint data structures.
