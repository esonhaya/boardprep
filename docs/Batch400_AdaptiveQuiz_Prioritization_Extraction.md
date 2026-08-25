# Batch 400 — Adaptive Quiz Prioritization Extraction

## Goal

Reduce the 98-line `AdaptiveQuizService::prioritize()` method into a small production boundary while preserving its observable behavior.

## Production path

`AdaptiveQuizService::prioritize()` now delegates to `AdaptivePriorityBuilder`, which coordinates:

1. `AdaptiveWeaknessTopicResolver`
2. `AdaptiveQuestionPartitioner`
3. `AdaptiveQuestionOrderer`

`AdaptiveTopicNormalizer` provides one normalization rule for learner topics and question topics.

## Compatibility

The weakness resolver accepts both:

- keyed weakness records: `Topic => stats`
- legacy records containing a `topic` field

This matches the current `WeaknessStorageService` representation without changing that storage layer.

## Validation

The batch includes focused collaborator tests, a production contract test, extraction integration coverage, syntax checks, the full quiz simulation, and Doctor validation.
