# TB-010 — Quiz Development Guidelines

## Status

Approved

---

# Purpose

This document defines the mandatory development rules for all future Quiz Engine work.

Every new quiz feature, refactor, or optimization must be evaluated against these guidelines before implementation.

---

# Development Philosophy

BoardPrep prioritizes:

1. Long-term maintainability
2. Learning effectiveness
3. Architectural consistency
4. Extensibility
5. Performance

Quick implementations must never compromise architecture.

---

# Before Writing Code

Every implementation must answer:

## 1. Does this belong in the current service?

If not:

Extract it.

---

## 2. Is there already a service responsible?

Never duplicate responsibilities.

Extend existing architecture.

---

## 3. Does this violate TB-008?

If yes:

Redesign before implementation.

---

## 4. Will this still make sense two years from now?

If uncertain:

Prefer the simpler architecture.

---

# Service Responsibilities

QuizGenerationService

Responsible only for orchestrating generation.

Never contains business logic.

---

QuestionSelectionService

Owns question selection.

Includes:

- balancing
- diversity
- blueprint distribution
- adaptive preparation
- history prioritization

---

AdaptiveQuizService

Only responsible for adaptive behavior.

Never filters curriculum.

---

QuizScoringService

Only responsible for scoring.

Never selects questions.

---

QuizResultService

Builds learner-facing results.

Never recalculates scores.

---

# Pipeline Integrity

The pipeline order must remain:

Repository

↓

Filter

↓

Selection

↓

Adaptive

↓

History

↓

Shuffle

↓

Limit

↓

Session

No shortcuts.

No duplicated stages.

---

# Feature Checklist

Every new feature should satisfy:

□ Fits existing architecture

□ Has a single responsibility

□ Improves learning

□ Doesn't duplicate another service

□ Doesn't introduce another pipeline

□ Doctor remains healthy

---

# Documentation Rule

Architecture changes are documented before implementation.

Implementation follows documentation.

Never the other way around.

---

# Doctor Rule

Every milestone:

1. Run Doctor

2. Fix regressions

3. Commit

Large features should be split into milestones.

---

# Refactoring Rule

Refactor when:

- responsibility grows
- complexity increases
- duplication appears

Do not refactor purely for aesthetics.

---

# Backwards Compatibility

Existing quizzes should continue functioning whenever possible.

Large architectural changes should preserve current behavior.

---

# Future Rule

Whenever uncertainty exists:

Prefer extending the current architecture over introducing a new one.

