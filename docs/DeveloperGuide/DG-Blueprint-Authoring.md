# Blueprint Authoring Guide

## Purpose

Blueprints describe the intended composition of an examination.

Blueprints do not select questions.

Blueprints never reference specific question IDs.

The Quiz Engine performs all question selection.

---

# Supported Blueprint Levels

Board Blueprint

Responsible for subject distribution.

Subject Blueprint

Responsible for

- Domain distribution
- Difficulty distribution

No additional blueprint levels exist.

---

# Percentage Rules

Every distribution must total exactly 100%.

Examples

Subjects

40

40

20

Domains

30

25

45

Difficulty

20

60

20

---

# Things That Never Belong In Blueprints

Topics

Concepts

Question IDs

Question counts

Adaptive logic

Randomization

Duplicate prevention

History filtering

Recovery logic

Those behaviors belong to the Quiz Engine.

---

# Topic Distribution

Topics are balanced automatically.

The engine attempts to evenly utilize available topics inside the selected domain.

Blueprint authors never configure topic percentages.

---

# Concept Distribution

Concepts are balanced after topic balancing.

The engine attempts to maximize concept diversity.

Blueprint authors never configure concept percentages.

---

# Difficulty Distribution

Difficulty percentages apply across the generated questions for the subject.

Example

Easy

20%

Medium

60%

Hard

20%

---

# Versioning

Every blueprint is versioned.

Only one active Board Blueprint may exist.

Only one active Subject Blueprint may exist for each subject.

Historical versions remain available for audit purposes.

---

# Design Philosophy

Blueprints describe policy.

Algorithms provide intelligence.

Keep blueprint files small.

Keep blueprint files stable.

Allow the engine to evolve without requiring blueprint changes.
