# TB-014 — Adaptive Learning Architecture

## Status

Approved

---

# Purpose

Adaptive Learning personalizes quiz generation without changing the official curriculum.

Blueprints remain authoritative.

Adaptive Learning influences question priority only.

---

# Philosophy

Curriculum determines what must be studied.

Adaptive Learning determines what should be emphasized.

Personalization never overrides curriculum.

---

# Inputs

AdaptiveQuizService receives

- QuizSpecification
- Attempt History
- Weakness Profile
- Candidate Questions

AdaptiveQuizService never loads blueprints directly.

---

# Responsibilities

AdaptiveQuizService may

- Prioritize weak concepts
- Recommend difficulty
- Increase review frequency
- Support spaced repetition
- Improve concept coverage

AdaptiveQuizService never

- Changes blueprint percentages
- Changes curriculum
- Changes question count
- Ignores required domains
- Modifies QuizSpecification

---

# Adaptive Pipeline

QuizSpecification

↓

Candidate Questions

↓

Weakness Analysis

↓

Priority Scoring

↓

QuestionSelectionService

↓

Selected Questions

---

# Priority Rules

Adaptive Learning should prioritize

- Weak concepts
- Weak topics
- Recently missed questions
- Low mastery areas

Adaptive Learning should never violate

- Blueprint distribution
- Domain requirements
- Difficulty distribution
- QuizSpecification

---

# Architectural Rules

Blueprints remain the source of truth.

Adaptive Learning is advisory.

QuestionSelectionService makes the final selection.

AdaptiveQuizService influences priority only.

---

# Future Expansion

The architecture supports

- Spaced repetition
- Personalized study plans
- AI recommendations
- Predictive mastery
- Review scheduling

without changing the quiz generation pipeline.

