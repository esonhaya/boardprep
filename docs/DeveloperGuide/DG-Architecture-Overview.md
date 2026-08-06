# Architecture Overview

BoardPrep follows a layered quiz architecture.

Quiz Request

↓

Quiz Specification

↓

Blueprint Engine

↓

Selection Engine

↓

Recovery Engine

↓

Coverage Engine

↓

Quiz Assembly

↓

Quiz Delivery

Blueprints define policy.

The engine provides intelligence.

Each module owns a single responsibility.

No module performs multiple architectural roles.


---

# Updated Engine Layout

Quiz Request

↓

Specification Engine

↓

Blueprint Engine

↓

Generation Engine

    ├── Question Selection
    ├── Topic Balancer
    ├── Concept Balancer
    ├── Adaptive Prioritization
    ├── History Filter
    └── Shortage Recovery

↓

Assembly Engine

↓

Validation Engine

↓

Delivery Engine


---

# Final Engine Layout

Quiz Request

↓

Specification Engine

↓

Blueprint Engine

↓

Generation Engine

    ├── Question Selection
    ├── Topic Balancer
    ├── Concept Balancer
    ├── Adaptive Prioritization
    ├── Quiz History Filter
    └── Shortage Recovery

↓

Assembly Engine

↓

Validation Engine

↓

Delivery Engine

