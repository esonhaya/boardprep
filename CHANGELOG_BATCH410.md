# Batch 410 Changelog

## Question Query Reliability

- Replaced repeated repository reads in the question editor query path with a single-snapshot query pipeline.
- Added defensive query-filter normalization and safe question/taxonomy value reading.
- Centralized structured and keyword matching.
- Migrated `QuestionSearchService` search/filter compatibility methods onto the shared pipeline.
- Added focused production-path and regression tests for combined query behavior.
