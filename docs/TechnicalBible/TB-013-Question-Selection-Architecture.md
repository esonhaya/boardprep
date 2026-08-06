# TB-013 — Question Selection Architecture

## Status

Approved

---

# Purpose

QuestionSelectionService fulfills a QuizSpecification by producing the best possible set of questions.

It is the core service of the Quiz Generation pipeline.

It fulfills blueprint requirements while maximizing educational diversity.

---

# Philosophy

Question selection does not create curriculum.

Question selection fulfills curriculum.

Curriculum is determined before selection begins.

---

# Inputs

QuestionSelectionService receives

- QuizSpecification
- Question Repository
- Attempt History
- Weakness Profile

The service never loads blueprints directly.

---

# Responsibilities

QuestionSelectionService is responsible for

- Blueprint fulfillment
- Domain filtering
- Difficulty fulfillment
- Topic balancing
- Concept balancing
- Duplicate prevention
- Question diversity
- History filtering
- Adaptive prioritization
- Shortage recovery
- Final question ordering

---

# Non-Responsibilities

QuestionSelectionService never

- Creates curriculum
- Modifies blueprints
- Builds QuizSpecifications
- Scores quizzes
- Stores attempts
- Generates reports

---

# Selection Pipeline

QuizSpecification

↓

Candidate Collection

↓

Domain Filtering

↓

Difficulty Fulfillment

↓

Topic Balancing

↓

Concept Balancing

↓

History Filtering

↓

Adaptive Prioritization

↓

Duplicate Prevention

↓

Shortage Recovery

↓

Question Ordering

↓

Selected Questions

---

# Selection Rules

The service should maximize

- Blueprint compliance

- Topic diversity

- Concept diversity

- Learning value

The service should minimize

- Duplicate concepts

- Repeated questions

- History repetition

---

# Recovery Rules

Recovery hierarchy

Concept

↓

Topic

↓

Domain

↓

Subject

Recovery never crosses subject boundaries.

Blueprint requirements always have priority.

---

# Determinism

Given the same

- QuizSpecification

- Question Repository

- Blueprint versions

- Random seed

the service should produce identical results.

---

# Architectural Rules

QuestionSelectionService fulfills specifications.

It never creates specifications.

It never owns curriculum.

It never owns blueprint data.

It remains the single implementation responsible for selecting questions.

