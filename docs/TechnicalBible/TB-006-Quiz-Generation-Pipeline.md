# TB-006 Quiz Generation Pipeline

## Purpose

This document defines the canonical quiz generation pipeline.

Every quiz generation mode must follow this pipeline.

Practice Mode

Exam Mode

Adaptive Mode

Timed Mode

Review Mode

All modes share the same generation engine.

---

# Pipeline

Quiz Request

↓

Quiz Specification

↓

Board Blueprint

↓

Subject Allocation

↓

Subject Blueprint

↓

Domain Allocation

↓

Difficulty Allocation

↓

Question Selection

↓

Topic Balancing

↓

Concept Balancing

↓

Duplicate Prevention

↓

Quiz History Filter

↓

Adaptive Prioritization

↓

Shortage Recovery

↓

Coverage Validation

↓

Quiz Assembly

↓

Quiz Delivery

---

# Responsibilities

Quiz Specification

Defines the requested quiz.

Board Blueprint

Determines subject percentages.

Subject Blueprint

Determines

- domain percentages

- difficulty percentages

Selection Engine

Produces candidate questions.

Balancing

Produces diverse examinations.

Recovery

Repairs shortages.

Coverage

Measures blueprint fulfillment.

Assembly

Produces the final examination.

---

# Architectural Rules

The pipeline always executes in this order.

Blueprints never directly select questions.

Question repositories never understand blueprints.

Selection services never load blueprints.

Assembly services coordinate the pipeline.

Every stage performs exactly one responsibility.

---

# Future Extensions

Additional quiz modes must extend the pipeline.

They must not replace it.

Examples

Practice Mode

Exam Mode

Mock Board Exam

Adaptive Study

Custom Quiz

All future modes reuse the same architecture.



---

# Generation Engine

The Generation Engine contains

- Question Selection
- Topic Balancer
- Concept Balancer
- Adaptive Prioritization
- Quiz History Filter
- Shortage Recovery

Generation is responsible only for producing candidate questions.

Assembly is responsible for constructing the final quiz.

Validation verifies blueprint fulfillment after assembly.

