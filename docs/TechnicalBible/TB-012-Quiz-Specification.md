# TB-012 — Quiz Specification

## Status

Approved

---

# Purpose

QuizSpecification is the immutable contract describing a quiz before question selection begins.

Every generated quiz begins with a QuizSpecification.

QuestionSelectionService fulfills the specification.

QuizGenerationService creates and orchestrates it.

---

# Philosophy

Blueprints define curriculum.

Users define study preferences.

QuizSpecification combines both into a single immutable object.

Services consume the specification.

Services never modify it.

---

# Inputs

Blueprint information

- Board
- Subject
- Active Board Blueprint Version
- Active Subject Blueprint Version

Curriculum

- Domain

User configuration

- Study Mode
- Question Count
- Difficulty Preference
- Topic Filters
- Concept Filters
- Shuffle
- Adaptive Enabled
- History Policy

---

# Responsibilities

QuizSpecification defines

- Curriculum scope
- Blueprint versions
- Requested question count
- Difficulty policy
- Topic filters
- Concept filters
- Study mode
- Shuffle policy
- History policy
- Adaptive policy

QuizSpecification never contains

- Questions
- Scores
- Attempts
- Results

---

# Lifecycle

Quiz Request

↓

Blueprint Resolution

↓

Blueprint Distribution

↓

QuizSpecification

↓

QuestionSelectionService

↓

Quiz Assembly

↓

Quiz Session

---

# Immutability

After creation

QuizSpecification must never change.

All services receive the same instance.

No service rewrites its contents.

---

# Architectural Rules

QuestionSelectionService fulfills QuizSpecifications.

Blueprints produce specifications.

Adaptive learning influences prioritization only.

History filtering never modifies the specification.

QuizGenerationService orchestrates the generation process.

