# TB-012 — Quiz Specification

## Status

Approved

---

# Purpose

A Quiz Specification is the immutable contract describing a quiz before any questions are selected.

Every generated quiz must begin with a Quiz Specification.

QuestionSelectionService fulfills the specification.

---

# Philosophy

Blueprints define the curriculum.

Users define their study preferences.

Quiz Specification combines both.

Question Selection fulfills it.

---

# Inputs

Board

Subject

Domain

Blueprint

Study Mode

Question Count

Difficulty

Topic Filters

Concept Filters

Adaptive Mode

Shuffle

History Policy

---

# Output

QuizSpecification

This object completely describes the quiz before question selection begins.

---

# Responsibilities

Quiz Specification determines:

- Curriculum scope
- Blueprint version
- Domain scope
- Question count
- Difficulty policy
- Topic filters
- Concept filters
- Study mode
- Adaptive flag
- Shuffle policy

It never contains selected questions.

---

# Lifecycle

Board

↓

Subject

↓

Blueprint

↓

Domain

↓

User Filters

↓

Quiz Specification

↓

Question Selection

↓

Adaptive

↓

Quiz Session

---

# Immutable Rule

After creation, a Quiz Specification must not be modified.

Services consume it.

They do not rewrite it.

---

# Example

QuizSpecification

Board

LET

Subject

Professional Education

Blueprint Version

3

Question Count

40

Difficulty

Mixed

Study Mode

Practice

Domain

Assessment

Topics

Measurement

Evaluation

Concepts

Validity

Reliability

Adaptive

Enabled

Shuffle

Enabled

---

# Architectural Rules

QuestionSelectionService receives a Quiz Specification.

Blueprints create specifications.

Adaptive never edits specifications.

History never edits specifications.

QuizGenerationService orchestrates creation only.

