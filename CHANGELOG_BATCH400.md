# Batch 400 — Adaptive Quiz Prioritization Extraction

- Extracted adaptive-topic normalization, weakness-topic resolution, question partitioning, ordering, and orchestration from `AdaptiveQuizService`.
- Preserved the non-adaptive contract: original question order is returned unchanged.
- Preserved adaptive behavior: weak-topic questions are returned before normal questions and each group is shuffled.
- Added compatibility for the current keyed weakness storage shape as well as the older list-of-records shape.
- Added focused unit, production-contract, and extraction integration coverage.
