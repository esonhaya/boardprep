# Batch 395 — Quiz Result Production Extraction

## Milestone
Extract the cohesive result-building mechanics from `QuizResultService::build()` while preserving its public API and production behavior.

## Production changes
- Extract session input reading.
- Extract attempt construction.
- Extract the persisted-attempt guard.
- Extract attempt persistence.
- Extract result response construction.
- Keep `QuizResultService::build()` as the production orchestration entry point.

## Contract preserved
- `QuizResultService::build(): array`
- scoring remains delegated to `QuizScoringService`
- attempts persist only once per quiz session
- enriched attempts still use `QuizLearningContextService`
- response shape remains `summary` + `review`
