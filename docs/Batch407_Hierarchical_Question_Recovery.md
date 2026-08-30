# Batch 407: Hierarchical Question Recovery

## Goal
Make shortage recovery follow the taxonomy hierarchy already represented by `SelectionRequest` while removing the large `RecoveryCandidateService::candidates()` hotspot.

## Production behavior
Recovery now attempts the most specific available request scope first and widens only when the pool cannot satisfy the requested count:

`concept → topic → domain → subject`

Missing request dimensions are skipped. Recovery never crosses the requested subject and only active/approved questions are eligible.

## Architecture
`RecoveryCandidateService` remains the public production entry point. Context resolution, status policy, scope matching, scope planning, and filtering are delegated to focused recovery collaborators.

## Validation
Focused contracts cover field resolution, scope matching, hierarchy planning, inactive-question exclusion, production entry points, autoloading, shortage recovery, and method/file maintainability.
