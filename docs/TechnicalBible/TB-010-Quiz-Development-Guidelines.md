# TB-010 — Quiz Development Guidelines

## Status

Approved

---

# Purpose

This document defines the mandatory development rules for every Quiz Engine feature.

Implementation must follow the Technical Bible.

Architecture changes must be documented before code changes.

---

# Development Philosophy

BoardPrep prioritizes

1. Maintainability
2. Simplicity
3. Extensibility
4. Learning effectiveness
5. Architectural consistency

Architecture always takes priority over convenience.

---

# Responsibility Rules

Every service owns exactly one responsibility.

Business logic belongs inside Services.

Controllers orchestrate.

Repositories persist data.

DTOs transport data.

---

# Core Quiz Services

## QuizGenerationService

Responsible only for orchestration.

It coordinates the quiz generation pipeline.

It never performs question selection.

---

## QuestionSelectionService

Responsible for fulfilling a QuizSpecification.

Responsibilities include

- Blueprint fulfillment
- Difficulty fulfillment
- Topic balancing
- Concept balancing
- Duplicate prevention
- Diversity optimization
- History filtering
- Adaptive prioritization
- Shortage recovery

QuestionSelectionService never

- Creates curriculum
- Modifies blueprints
- Scores quizzes
- Persists attempts

---

## QuizScoringService

Responsible only for scoring.

Never selects questions.

---

## QuizResultService

Responsible only for learner-facing results.

Never recalculates scores.

---

## AdaptiveQuizService

Responsible only for adaptive prioritization.

Never changes curriculum.

Never changes blueprint distribution.

---

# Architectural Rules

Every new feature must

- Reuse the existing quiz pipeline.
- Respect blueprint authority.
- Preserve QuizSpecification immutability.
- Maintain single responsibility.

Never introduce a second quiz pipeline.

---

# Development Checklist

Before merging code verify

□ Technical Bible updated

□ Single responsibility maintained

□ No duplicated logic

□ Doctor passes

□ Regression checks pass

□ Architecture remains consistent

---

# Doctor Rule

Every milestone

1. Run Doctor

2. Review regressions

3. Commit

---

# Refactoring Rule

Refactor when

- Responsibilities grow
- Duplication appears
- Complexity increases

Do not refactor solely for aesthetics.

---

# Golden Rule

When implementation and documentation disagree,

the Technical Bible is the source of truth.

