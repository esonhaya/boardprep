# TB-008 — Quiz Generation & Study Architecture

## Status

Approved

---

# Purpose

This document defines the permanent architecture governing

- Curriculum
- Quiz generation
- Study navigation
- Study modes
- Blueprint ownership
- Quiz assembly

All future quiz features must comply with this specification.

---

# Core Principles

## Curriculum First

Curriculum defines what may be studied.

The Quiz Engine determines how questions are assembled.

---

## Two Blueprint Levels

BoardPrep supports only

- Board Blueprint
- Subject Blueprint

Topics and Concepts never own blueprints.

---

## Single Quiz Pipeline

Every quiz mode uses the same generation pipeline.

Quiz modes configure behavior.

They never replace the pipeline.

---

## Separation of Responsibilities

Blueprints define policy.

The Quiz Engine implements strategy.

QuestionSelectionService fulfills QuizSpecifications.

Scoring evaluates performance.

---

# Curriculum Hierarchy

Board

↓

Subject

↓

Domain

↓

Topic

↓

Concept

↓

Questions

Every question belongs to exactly one

- Board
- Subject
- Domain
- Topic
- Concept

---

# Navigation

Users navigate only to

- Board
- Subject
- Domain

Topic and Concept remain filters inside a Domain workspace.

---

# Study Levels

## Board

Uses the active Board Blueprint.

Available modes

- Exam
- Practice

---

## Subject

Uses the active Subject Blueprint.

Available modes

- Exam
- Practice

---

## Domain

Developer-defined curriculum.

User-configurable workspace.

Available modes

- Practice
- Adaptive
- Weakness Review
- Mastery
- Review Incorrect

Users may configure

- Topic filters
- Concept filters
- Difficulty preference
- Question count
- Shuffle

---

# Quiz Generation

Quiz Request

↓

QuizSpecification

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

QuestionSelectionService fulfills the QuizSpecification.

Responsibilities

- Blueprint fulfillment
- Topic balancing
- Concept balancing
- Diversity
- Duplicate prevention
- History filtering
- Adaptive prioritization
- Shortage recovery

QuestionSelectionService never

- Creates curriculum
- Modifies blueprints
- Scores quizzes
- Persists attempts

---

# Study Modes

Study modes influence behavior.

They never change curriculum.

Adaptive learning may influence priority.

It never overrides blueprint requirements.

---

# Architectural Rules

1. Navigation ends at Domain.

2. Board Blueprints own subject distribution.

3. Subject Blueprints own domain and difficulty distribution.

4. Topic balancing is algorithmic.

5. Concept balancing is algorithmic.

6. Question counts are calculated at runtime.

7. Generated quizzes store blueprint versions.

8. Blueprint percentages represent intent.

9. The Quiz Engine may evolve while blueprints remain stable.

10. Future quiz modes extend the existing pipeline.

