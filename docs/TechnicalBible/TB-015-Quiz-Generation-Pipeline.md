# TB-015 — Quiz Generation Pipeline

Status: Approved

---

# Philosophy

Curriculum is developer-owned.

Study behavior is user-owned.

Users may customize how they study.

Users may never redefine the curriculum.

---

# Hierarchy

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

Quiz Specification

↓

Question Selection Engine

↓

Quiz Session

---

# Responsibilities

## Board Blueprint

Defines the curriculum at the Board level.

Responsible for:

- Subject distribution
- Subject weights
- Subject order
- Active blueprint version

A Board Blueprint never defines:

- Topics
- Concepts
- Difficulty

---

## Subject Blueprint

Defines the curriculum inside one subject.

Responsible for:

- Domain distribution
- Topic distribution
- Concept distribution
- Difficulty distribution
- Coverage rules

A Subject Blueprint never changes Board distribution.

---

## Domain

The Domain is the user's study workspace.

Users may configure:

- Practice Mode
- Adaptive Mode
- Difficulty
- Topic focus
- Concept focus
- Question count

Users never modify curriculum.

---

# Quiz Specification

QuizSpecification is immutable.

It is created by combining:

Board Blueprint

+

Subject Blueprint

+

User selections

The Specification becomes the contract for the Quiz Engine.

---

# Question Selection Engine

The engine fulfills the Quiz Specification.

Responsibilities:

1. Collect candidates
2. Fulfill Board requirements
3. Fulfill Subject requirements
4. Apply Domain filters
5. Apply Adaptive priority
6. Balance Topics
7. Balance Concepts
8. Recover shortages
9. Shuffle
10. Limit

The engine never creates curriculum.

The engine only fulfills curriculum.

---

# Golden Rule

Higher levels define curriculum.

Lower levels constrain curriculum.

Lower levels never redefine higher levels.
