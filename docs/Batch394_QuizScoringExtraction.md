# Batch 394 — Quiz Scoring Extraction

## Milestone

Extract the cohesive mechanics inside `QuizScoringService::calculate()` without changing its public API.

## Production path

`QuizScoringService::calculate()` remains the compatibility entry point and now delegates to:

- `AnswerNormalizer`
- `QuestionScoreEvaluator`
- `ScoreAccumulator`
- `ResultRecordFactory`

`checkAnswer()` remains publicly compatible.

## Contract

Preserve:

- correct / incorrect / unanswered counts
- percentage calculation
- answer-letter normalization (`A`–`D`)
- result record shape
- `QuizScoringService::calculate()` signature
- `QuizScoringService::checkAnswer()` signature
