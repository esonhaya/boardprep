# Batch 398 — Blueprint Distribution Extraction

## Milestone

Extract deterministic blueprint distribution preparation and validation into focused collaborators while preserving the public `BlueprintDistributionService::distribute()` boundary.

## Production changes

- Normalize distribution requests.
- Allocate request counts through a focused allocator.
- Build final distribution output through a result factory.
- Add diagnostics and validation collaborators.
- Preserve the existing service as the orchestration boundary.

## Validation

Targeted extraction, contract, and integration tests accompany the production changes. Full QuizTest and Doctor validation remain the release gate.

## Non-goals

- No new blueprint behavior.
- No changes to question selection.
- No UI changes.
- No database changes.
