# TB-011 — Blueprint Architecture

## Status

Approved

---

# Purpose

Blueprints define the official examination structure.

Blueprints describe curriculum policy.

Blueprints never select questions.

Question selection is performed by the Quiz Engine.

---

# Blueprint Levels

BoardPrep supports exactly two blueprint levels.

- Board Blueprint
- Subject Blueprint

No Domain Blueprint exists.

No Topic Blueprint exists.

No Concept Blueprint exists.

---

# Board Blueprint

A Board Blueprint defines

- Subject distribution

Example

General Education ...... 40%

Professional Education . 40%

Major .................. 20%

Board Blueprints never define

- Domains
- Difficulty
- Topics
- Concepts
- Question IDs

---

# Subject Blueprint

A Subject Blueprint defines

- Domain distribution

- Difficulty distribution

Example

Assessment ............. 30%

Curriculum ............. 25%

Teaching ............... 45%

Difficulty

Easy ................... 20%

Medium ................. 60%

Hard ................... 20%

Subject Blueprints never define

- Topics
- Concepts
- Recovery
- Adaptive behavior
- Question IDs

---

# Blueprint Versioning

Each Board has one active Board Blueprint.

Each Subject has one active Subject Blueprint.

Previous versions remain available for

- replay

- auditing

- analytics

Generated quizzes record the blueprint versions used.

---

# Blueprint Responsibilities

Blueprints define

- curriculum distribution

- weighting

- difficulty

Blueprints never define

- runtime allocation

- randomization

- adaptive learning

- duplicate prevention

- question history

- shortage recovery

---

# Runtime Allocation

Question counts are calculated only during quiz generation.

Blueprint percentages represent intent.

The Quiz Engine converts percentages into question counts.

Derived counts are never persisted.

---

# Output

Blueprints produce a QuizSpecification.

The Quiz Engine fulfills that specification.

Blueprints never produce questions.

