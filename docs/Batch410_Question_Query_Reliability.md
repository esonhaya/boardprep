# Batch 410 — Question Query Reliability

## Problem

The developer question editor combined structured filters and keyword search by repeatedly reading the question repository. A filtered request could load the repository once in `QuestionQueryService`, again in `QuestionSearchService::filter()`, and again in `QuestionSearchService::search()`, then intersect search results using whole-array membership. The query service also called `trim()` directly on arbitrary filter values.

## Milestone

- Normalize query inputs at one boundary.
- Read the repository exactly once per `QuestionQueryService::getQuestions()` request.
- Apply search, domain, difficulty, and topic as one deterministic intersection over that snapshot.
- Safely reject malformed repository rows and treat malformed scalar fields as empty text.
- Reuse the same query pipeline from `QuestionSearchService` so legacy search/filter helpers retain compatible semantics without a second implementation.
- Preserve current editor search fields: question text and taxonomy subject/domain/topic/concept IDs.

## Validation

Batch tests cover normalization, malformed data, search matching, structured matching, combined intersections, one-read production behavior, compatibility parity, and maintainability boundaries. Run the full quiz regression and Doctor at the milestone boundary.
