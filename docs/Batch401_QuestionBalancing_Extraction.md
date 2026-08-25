# Batch 401 — Question Balancing Extraction

## Goal

Reduce the balancing service's orchestration complexity without changing its observable quiz-selection behavior.

## Production pipeline

`QuestionBalancingService::balance()` now coordinates:

1. difficulty resolution
2. difficulty filtering
3. topic grouping
4. per-topic shuffling
5. round-robin assembly

## Compatibility

The default remains `mixed`, explicit difficulty values remain case-insensitive, missing topics continue to use `__unknown__`, and questions continue to be emitted one per topic per round until all groups are exhausted.

## Validation

Focused collaborator tests, a production contract test, extraction integration coverage, syntax checks, the full quiz simulation, and Doctor validation are required before commit.
