# Blueprint Execution Guide

## Overview

The Blueprint Engine converts percentage-based blueprint definitions into a fully assembled examination.

Blueprints never contain fixed question counts.

Question counts are calculated dynamically for every quiz.

---

# Execution Pipeline

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

Final Quiz

---

# Stage 1

Board Blueprint

Input

Board

Output

Subject allocations.

Example

LET

General Education

Professional Education

Major

The output remains percentage-based until quiz generation.

---

# Stage 2

Subject Blueprint

Responsible for

- Domain distribution
- Difficulty distribution

Example

Professional Education

Assessment

Curriculum

Teaching Strategies

Easy

Medium

Hard

Topics and concepts are intentionally excluded.

---

# Stage 3

Allocation

Percentages become question counts.

Example

150 Questions

↓

Professional Education

40%

↓

60 Questions

↓

Assessment

30%

↓

18 Questions

↓

Difficulty

Easy

Medium

Hard

---

# Stage 4

Question Selection

The Selection Engine attempts to satisfy every allocation.

Selection is performed using

- Domain filtering
- Topic balancing
- Concept balancing
- Difficulty distribution
- Duplicate prevention

---

# Stage 5

Adaptive Learning

If Adaptive Mode is enabled

Weak topics receive higher priority.

Blueprint allocations remain authoritative.

Adaptive learning adjusts selection only within blueprint constraints.

---

# Stage 6

History Filtering

Previously used questions are avoided whenever possible.

If insufficient unused questions exist

History may be relaxed by the recovery system.

---

# Stage 7

Shortage Recovery

Recovery hierarchy

Concept

↓

Topic

↓

Domain

↓

Subject

The engine never crosses subject boundaries.

---

# Stage 8

Coverage Validation

The generated examination is compared against the requested blueprint.

Validation includes

- Subject coverage
- Domain coverage
- Difficulty coverage

Coverage reports may be used by developer tools.

---

# Design Principles

Blueprints define policy.

The Quiz Engine implements strategy.

Configuration should remain minimal.

Algorithms should provide intelligence.

Blueprints should remain stable even as the question bank grows.

Adding new topics or concepts should never require blueprint changes.

---

# Allocation Method

Blueprint percentages represent intent.

The engine converts percentages into question counts using the Largest Remainder Method.

This guarantees:

- Exact total question count
- Fair proportional allocation
- Stable blueprint definitions


---

# Allocation

Blueprint percentages express intent.

The engine converts percentages into question counts using the Largest Remainder Method.

Counts are runtime values only.

Blueprints remain unchanged regardless of quiz size.

