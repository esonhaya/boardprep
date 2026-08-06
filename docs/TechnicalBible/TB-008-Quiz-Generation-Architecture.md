# TB-008 — Quiz Generation & Study Architecture

## Status

Approved

---

# Purpose

This document defines the permanent architectural rules governing:

- Curriculum hierarchy
- Quiz generation
- Study navigation
- Blueprint ownership
- Study modes
- Question selection

All future quiz-related features must comply with this specification.

---

# Core Principles

## Curriculum First

BoardPrep models the curriculum first.

Quizzes are generated from curriculum data.

The curriculum is the source of truth.

---

## Shallow Navigation

Navigation should remain simple.

Users never navigate deeper than the Domain level.

Everything below Domain is represented as filters.

---

## Single Quiz Pipeline

BoardPrep has one quiz generation pipeline.

Different quiz types configure the pipeline instead of replacing it.

---

## Separation of Responsibilities

Curriculum decides:

- What can be studied.

Study Mode decides:

- How it is studied.

Question Selection decides:

- Which questions are selected.

Scoring decides:

- How performance is evaluated.

---

# Curriculum Hierarchy

```
Board
└── Subject
    └── Domain
        ├── Topic
        │   └── Concept
        └── Questions
```

Every question belongs to exactly one:

- Board
- Subject
- Domain
- Topic
- Concept

---

# Navigation Rules

Users may navigate only to:

- Board
- Subject
- Domain

Topic and Concept are not standalone study pages.

Instead, they are filters inside a Domain workspace.

---

# Blueprint Rules

Blueprints exist only for:

- Board
- Subject

Blueprints never belong to:

- Domain
- Topic
- Concept

Every Board has exactly one active blueprint.

Example:

```
LET

Blueprint V1
Blueprint V2
Blueprint V3

Active = V3
```

Only developers may change the active blueprint.

Historical blueprint versions remain for compatibility.

---

# Study Levels

## Board

Represents the complete official examination.

Available modes:

- Exam
- Practice

Both use the active Board blueprint.

Practice changes the user experience only.

Question distribution remains identical.

---

## Subject

Represents one official examination section.

Available modes:

- Exam
- Practice

Uses the Subject blueprint.

---

## Domain

Represents the learner's workspace.

Available modes:

- Practice
- Adaptive
- Weakness Review
- Mastery
- Review Incorrect

Users configure:

- Topic filters
- Concept filters
- Difficulty
- Question count
- Shuffle

Domains never own blueprints.

---

# Study Modes

Study modes affect quiz behavior.

They never determine curriculum.

## Exam

- Timed
- No feedback
- End-of-exam scoring

## Practice

- Immediate feedback
- Resume supported
- Untimed

## Adaptive

- Prioritize weak concepts
- Adjust difficulty
- Maintain curriculum balance

## Weakness Review

Only previously weak concepts.

## Mastery

Continue until mastery target.

## Review Incorrect

Previously answered incorrectly.

---

# Question Generation Pipeline

```
Question Repository
        ↓
Question Filter
        ↓
Question Selection
        ↓
Adaptive Prioritization
        ↓
History Filter
        ↓
Shuffle
        ↓
Limit
        ↓
Quiz Session
```

Pipeline order should not change without architectural review.

---

# QuestionSelectionService

QuestionSelectionService owns all selection logic.

Responsibilities include:

- Blueprint distribution
- Topic balancing
- Concept balancing
- Difficulty balancing
- Weakness prioritization
- Duplicate concept prevention
- Question diversity
- Shortage recovery
- Final selection

No other service performs these responsibilities.

QuizGenerationService only orchestrates the pipeline.

---

# Question Diversity Rules

Generated quizzes should maximize learning coverage.

Avoid:

- Duplicate questions
- Near-identical questions
- Consecutive questions testing the same concept

Prefer broader concept coverage before repetition.

---

# Difficulty Rules

Difficulty is a learner preference.

It is never part of the curriculum.

Mixed difficulty is the default.

Adaptive mode may override preferred difficulty.

---

# History Rules

Previously answered questions should be deprioritized.

Incorrect questions may reappear depending on study mode.

History influences selection but never overrides blueprint requirements.

---

# Domain Workspace

The Domain page is the learner's primary study workspace.

Everything needed to generate a quiz is configured here.

Future additions should integrate into this workspace without changing navigation.

Examples:

- Years
- Sources
- Authors
- Tags
- Favorites
- Saved Presets
- AI-generated questions

---

# Architectural Rules

The following rules are non-negotiable.

1. Navigation stops at Domain.

2. Board and Subject own blueprints.

3. Domain never owns a blueprint.

4. Topic and Concept are filters, not destinations.

5. QuizGenerationService is an orchestrator only.

6. QuestionSelectionService owns all question selection.

7. New quiz modes configure the existing pipeline rather than introducing new pipelines.

8. Curriculum determines content.

9. Study mode determines experience.

10. Future features should extend existing architecture rather than replacing it.

---

# Future Expansion

The architecture must support without redesign:

- Additional licensure examinations
- Multiple blueprint versions
- AI-generated questions
- Personalized study plans
- Spaced repetition
- Offline study
- Review center customization
- Analytics-driven recommendations

