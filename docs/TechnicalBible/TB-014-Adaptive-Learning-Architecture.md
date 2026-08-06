# TB-014 — Adaptive Learning Architecture

## Status

Approved

---

# Purpose

Adaptive Learning personalizes quizzes without changing the curriculum.

Blueprints remain authoritative.

Adaptive only changes question priority.

---

# Inputs

Attempt History

Weaknesses

Mastery

Quiz Specification

Question Candidates

---

# Responsibilities

Adaptive Learning may:

- Prioritize weak concepts
- Recommend difficulty
- Increase review frequency
- Space repetition

Adaptive Learning must never:

- Change blueprint weights
- Change curriculum
- Change question count
- Ignore required domains

---

# Pipeline

Quiz Specification

↓

Question Candidates

↓

Adaptive Priority

↓

Question Selection

↓

Quiz Session

---

# Rules

Blueprint defines what must be studied.

Adaptive defines what should be emphasized.

Curriculum always has higher priority than personalization.

