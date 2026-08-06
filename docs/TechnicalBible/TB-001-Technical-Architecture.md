# TB-001 Technical Architecture

# Quiz Engine Architecture

The Quiz Engine is responsible for assembling examinations from blueprint definitions.

Blueprints describe examination policy.

The engine implements examination strategy.

---

# Engine Layers

Quiz Specification

↓

Blueprint Resolution

↓

Blueprint Distribution

↓

Exam Assembly

↓

Question Selection

↓

Recovery

↓

Coverage Validation

↓

Quiz Delivery

---

# Design Principles

• Percentage-driven architecture

• Two blueprint levels only

• Engine-driven balancing

• Versioned blueprints

• Stateless generation

• Deterministic allocation

• Intelligent recovery

---

# Blueprint Philosophy

Board Blueprints define subject allocation.

Subject Blueprints define

- Domain allocation

- Difficulty allocation

Everything else is determined algorithmically.



---

# Architectural Invariants

The Quiz Engine may evolve.

Blueprints should rarely change.

Blueprints define policy.

The engine defines strategy.

Question counts are derived at runtime.

Derived values are never persisted.

Adaptive learning operates only within blueprint constraints.

Generated quizzes record the Board Blueprint version and Subject Blueprint version used during generation.

