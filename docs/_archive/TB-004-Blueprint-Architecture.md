# TB-004 Blueprint Architecture

## Philosophy

Blueprints define **what** an examination should contain.

The Quiz Engine decides **how** to assemble it.

Blueprints intentionally remain simple while the engine performs intelligent balancing.

---

# Blueprint Levels

BoardPrep only supports two blueprint levels.

## 1. Board Blueprint

Responsible for subject distribution only.

Example

LET

- General Education .......... 40%
- Professional Education ..... 40%
- Major ...................... 20%

The Board Blueprint never references domains, topics, concepts, or difficulty.

---

## 2. Subject Blueprint

Responsible for:

- Domain Distribution
- Difficulty Distribution

Example

Professional Education

Domains

- Assessment ................. 30%
- Curriculum ................. 25%
- Teaching Strategies ........ 45%

Difficulty

- Easy ....................... 20%
- Medium ..................... 60%
- Hard ....................... 20%

Subject Blueprints do not define topic or concept percentages.

---

# Engine Responsibilities

The Quiz Engine automatically performs:

- Topic balancing
- Concept balancing
- Duplicate prevention
- Quiz history filtering
- Adaptive prioritization
- Weighted randomness
- Shortage recovery

These behaviors are algorithmic and are intentionally not configurable through blueprints.

---

# Topic Balancing

Topics are balanced automatically inside each selected domain.

The engine attempts to evenly distribute questions across available topics while respecting blueprint allocations.

No topic blueprint exists.

---

# Concept Balancing

Concepts are balanced automatically after topic balancing.

Concepts are treated as the finest level of diversity within the generated examination.

---

# Blueprint Execution

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

Selection Engine

↓

Topic Balancing

↓

Concept Balancing

↓

Duplicate Prevention

↓

History Filtering

↓

Adaptive Learning

↓

Shortage Recovery

↓

Final Quiz

---

# Design Principles

- Configuration should remain minimal.
- Intelligence belongs inside the engine.
- Blueprints should remain stable as question banks evolve.
- Adding new topics should never require editing a blueprint.
- Adding new concepts should never require editing a blueprint.

---

# Blueprint Boundaries

Board Blueprints know only:

- Board
- Subjects
- Subject percentages

Board Blueprints never define:

- Domains
- Difficulty
- Topics
- Concepts
- Question IDs

Subject Blueprints know only:

- Subject
- Domains
- Domain percentages
- Difficulty percentages

Subject Blueprints never define:

- Topics
- Concepts
- Recovery rules
- Adaptive rules
- Question IDs


---

# Blueprint Boundaries

Board Blueprints know only

- Board
- Subjects
- Subject percentages

Board Blueprints never define

- Domains
- Difficulty
- Topics
- Concepts
- Question IDs

Subject Blueprints know only

- Subject
- Domains
- Domain percentages
- Difficulty percentages

Subject Blueprints never define

- Topics
- Concepts
- Adaptive rules
- Recovery rules
- Question IDs

