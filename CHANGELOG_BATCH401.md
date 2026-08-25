# Batch 401 — Question Balancing Extraction

- Extracted difficulty resolution and filtering from `QuestionBalancingService`.
- Extracted topic normalization/grouping, per-topic shuffling, and round-robin assembly.
- Preserved mixed-difficulty behavior and explicit difficulty filtering.
- Preserved randomized ordering within topic groups and round-robin topic interleaving.
- Added focused collaborator tests, production contract coverage, and extraction integration coverage.
