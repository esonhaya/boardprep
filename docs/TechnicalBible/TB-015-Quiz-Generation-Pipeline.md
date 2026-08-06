# TB-015 — Quiz Generation Pipeline

## Status

Approved

---

# Purpose

This document defines the canonical quiz generation pipeline used by every BoardPrep quiz.

All quiz modes reuse this pipeline.

Only configuration changes.

The pipeline does not.

---

# Philosophy

Curriculum is developer-owned.

Study behavior is user-owned.

Blueprints define policy.

The Quiz Engine implements strategy.

---

# Curriculum Hierarchy

Board

↓

Board Blueprint

↓

Subject

↓

Subject Blueprint

↓

Domain

↓

Topic

↓

Concept

↓

Questions

---

# Blueprint Responsibilities

## Board Blueprint

Responsible for

- Subject distribution

Board Blueprints never define

- Domains
- Difficulty
- Topics
- Concepts
- Question IDs

---

## Subject Blueprint

Responsible for

- Domain distribution
- Difficulty distribution

Subject Blueprints never define

- Topics
- Concepts
- Question IDs
- Recovery rules
- Adaptive rules

---

# Quiz Specification

QuizSpecification is the immutable contract describing the requested quiz.

It combines

- Active Board Blueprint
- Active Subject Blueprint
- User configuration

Once created it is never modified.

---

# Quiz Generation Pipeline

Quiz Request

↓

Quiz Specification

↓

Blueprint Resolution

↓

Blueprint Distribution

↓

QuestionSelectionService

↓

Topic Balancing

↓

Concept Balancing

↓

History Filter

↓

Adaptive Prioritization

↓

Shortage Recovery

↓

Coverage Validation

↓

Quiz Assembly

↓

Quiz Session

---

# QuestionSelectionService

QuestionSelectionService fulfills the Quiz Specification.

Responsibilities include

- Blueprint fulfillment
- Domain filtering
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

# Allocation Rules

Blueprint percentages represent intent.

Question counts are calculated during generation.

BoardPrep uses the Largest Remainder Method for proportional allocation.

Calculated counts are runtime values only.

---

# Architectural Rules

1. Navigation ends at Domain.

2. Board Blueprints own subject distribution.

3. Subject Blueprints own domain and difficulty distribution.

4. Topics and Concepts are balanced algorithmically.

5. Adaptive learning never overrides blueprint requirements.

6. Recovery never crosses subject boundaries.

7. Every generated quiz stores the blueprint versions used.

