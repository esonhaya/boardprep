# TB-011 — Blueprint Architecture

## Status

Approved

---

# Purpose

Blueprints define the official curriculum distribution.

Blueprints never select individual questions.

Blueprints produce specifications that the Question Selection Engine fulfills.

---

# Blueprint Ownership

Blueprints may only exist on:

- Board
- Subject

Blueprints must never exist on:

- Domain
- Topic
- Concept

This is a permanent architectural rule.

---

# Board Blueprint

A Board Blueprint defines:

- Subjects
- Subject Weight
- Total Question Count
- Blueprint Version

Example

LET

Professional Education ...... 40%

General Education ........... 40%

Specialization .............. 20%

---

# Subject Blueprint

A Subject Blueprint defines:

- Domains
- Domain Weight
- Difficulty Distribution
- Total Question Count
- Blueprint Version

Example

Professional Education

Assessment .............20%

Curriculum .............15%

Research ...............10%

Teaching ...............25%

Psychology .............30%

Difficulty

Easy ........20%

Medium ......50%

Hard ........30%

---

# Domain

Domains never own blueprints.

Domains are learner workspaces.

Users may configure:

- Topics
- Concepts
- Difficulty Override
- Question Count
- Study Mode

---

# Blueprint Responsibilities

Blueprints define:

- Official distribution
- Official weighting
- Difficulty mix

Blueprints never define:

- Question IDs
- Randomization
- Adaptive behavior
- Question selection

---

# Blueprint Versioning

Every Board and Subject has exactly one active blueprint.

Previous versions remain available.

Example

LET

Blueprint V1

Blueprint V2

Blueprint V3

Active = V3

---

# Output

Blueprints produce a Quiz Specification.

They never produce questions.

---

# Architectural Rules

Board owns Board Blueprint.

Subject owns Subject Blueprint.

Domain owns no Blueprint.

Question Selection fulfills Blueprint specifications.

Blueprints describe curriculum.

Selection chooses questions.

Adaptive personalizes results.

