# Batch 408 — Question Quality Reporting Reliability

Keeps Repository Health as the quality source of truth while making the Question Quality dashboard consume its current issue-code contract reliably.

## Production changes
- replaces the long inline `QuestionQualityService::analyze()` switch with a report presenter and focused grouping collaborators;
- maps the current `missing-choices` and `duplicate-choices` validator codes instead of stale singular forms;
- preserves unknown/new issue codes in `byCode` and `unclassifiedIssues` so future validators are not silently dropped;
- adds a compact priority/severity summary to the existing developer quality dashboard without duplicating validation logic.

The legacy response bucket names remain available for compatibility.
