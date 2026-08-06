# ADR-002

## Decision

BoardPrep supports exactly two blueprint levels.

Board Blueprint

Subject Blueprint

No Domain Blueprint exists.

No Topic Blueprint exists.

No Concept Blueprint exists.

## Rationale

Topics and concepts change frequently.

Maintaining blueprint percentages for them would create unnecessary maintenance.

The Quiz Engine automatically balances topics and concepts while respecting blueprint allocations.

Blueprints remain stable while the question bank evolves.

## Consequences

Blueprint authoring becomes significantly simpler.

The Quiz Engine becomes responsible for intelligent balancing.

This decision intentionally favors engine intelligence over configuration complexity.


---

## Additional Decisions

The Blueprint Engine defines examination policy.

The Generation Engine implements examination strategy.

Topic balancing and concept balancing are intentionally algorithmic to minimize blueprint maintenance.


---

## Engine Responsibility

Blueprints remain intentionally simple.

Topic balancing, concept balancing, duplicate prevention, adaptive learning, and shortage recovery are engine responsibilities rather than blueprint responsibilities.

This minimizes blueprint maintenance while allowing the engine to improve independently over time.

