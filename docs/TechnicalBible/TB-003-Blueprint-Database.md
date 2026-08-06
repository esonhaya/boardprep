# TB-003 Blueprint Database Architecture

# Overview

BoardPrep stores blueprint definitions separately from questions.

Blueprints describe examination composition and are versioned independently.

Only two blueprint scopes exist:

- Board Blueprint
- Subject Blueprint

No Domain, Topic, or Concept blueprints exist.

---

# Board Blueprint

Purpose

Determines subject allocation.

Example

{
    "id": "...",
    "scope": "board",
    "board": "LET",

    "subjects": [

        {
            "subject": "General Education",
            "percentage": 40
        },

        {
            "subject": "Professional Education",
            "percentage": 40
        },

        {
            "subject": "Major",
            "percentage": 20
        }

    ],

    "version": 1,

    "status": "active"
}

---

# Subject Blueprint

Purpose

Determines domain allocation and difficulty allocation.

Example

{
    "id": "...",

    "scope": "subject",

    "board": "LET",

    "subject": "Professional Education",

    "domains": [

        {
            "domain": "Assessment",
            "percentage": 30
        },

        {
            "domain": "Curriculum",
            "percentage": 30
        },

        {
            "domain": "Teaching Strategies",
            "percentage": 40
        }

    ],

    "difficulty": {

        "easy": 20,

        "medium": 60,

        "hard": 20

    },

    "version": 1,

    "status": "active"
}

---

# Blueprint Rules

Board Blueprint

- Subject percentages must total 100%.

Subject Blueprint

- Domain percentages must total 100%.
- Difficulty percentages must total 100%.

No blueprint stores:

- Topic percentages
- Concept percentages
- Fixed question counts

---

# Question Allocation

Question counts are computed only during quiz generation.

Example

150 Questions

↓

Board Blueprint

↓

Professional Education = 60

↓

Subject Blueprint

↓

Assessment = 18

↓

Difficulty

Easy = 4

Medium = 11

Hard = 3

---

# Engine Responsibilities

The Quiz Engine automatically performs:

- Topic balancing
- Concept balancing
- Duplicate prevention
- Quiz history filtering
- Adaptive prioritization
- Shortage recovery

These are algorithmic behaviors and are intentionally excluded from blueprint configuration.

---

# Versioning

Board Blueprints and Subject Blueprints are versioned independently.

Publishing a new Subject Blueprint does not require publishing a new Board Blueprint.

Only one active version may exist per scope.

---

# Allocation Strategy

Percentage distributions are converted into whole question counts during quiz generation.

BoardPrep uses the Largest Remainder Method to ensure totals remain exact while preserving proportional fairness.

Calculated question counts are temporary runtime values and are never stored in blueprint definitions.


---

# Allocation Algorithm

Blueprint percentages are converted into question counts only during quiz generation.

BoardPrep uses the Largest Remainder Method to preserve proportional fairness while guaranteeing the requested total number of questions.

Calculated counts are temporary runtime values and are never stored.

