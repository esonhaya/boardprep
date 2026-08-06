# TB-013 — Question Selection Architecture

## Status

Approved

---

# Purpose

QuestionSelectionService fulfills a Quiz Specification by selecting the best available questions.

It does not understand curriculum.

It does not understand blueprints.

It only fulfills the specification.

---

# Inputs

Quiz Specification

Question Pool

Attempt History

Adaptive Priority

---

# Responsibilities

Question Selection is responsible for:

- Filtering
- Candidate collection
- Topic balancing
- Concept balancing
- Difficulty balancing
- Diversity optimization
- Duplicate prevention
- Shortage recovery

Question Selection is never responsible for:

- Curriculum design
- Blueprint creation
- Session management
- Scoring

---

# Pipeline

Quiz Specification

↓

Collect Candidates

↓

Filter Candidates

↓

Apply Difficulty

↓

Apply Topic Filter

↓

Apply Concept Filter

↓

Balance Topics

↓

Balance Concepts

↓

Prevent Duplicates

↓

Recover Shortages

↓

Finalize Selection

↓

Selected Questions

---

# Design Rules

Selection never edits the Quiz Specification.

Selection returns questions only.

Selection should remain deterministic except where randomization is explicitly requested.

