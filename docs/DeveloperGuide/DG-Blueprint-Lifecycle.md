# Blueprint Lifecycle

## Overview

Blueprints evolve over time.

Every modification creates a new version.

Blueprints are immutable after publication.

---

# Draft

A newly created blueprint begins as a draft.

Draft blueprints

- may be edited
- may be validated
- cannot generate examinations

---

# Validation

Before publication

the blueprint is validated.

Validation checks include

- percentages total 100%
- required sections exist
- duplicate entries
- invalid references

Validation does not inspect the question bank.

---

# Coverage Analysis

After validation

the question bank is analysed.

Coverage analysis determines

- available questions

- missing domains

- missing difficulties

- potential shortages

Coverage warnings do not prevent publication.

---

# Publication

Publishing creates

a new immutable version.

Only one version may be active.

Publishing automatically archives the previously active version.

---

# Active Blueprint

Only active blueprints are used by the Quiz Engine.

Historical versions remain available.

Historical versions never change.

---

# Archive

Archived blueprints

cannot be edited

cannot generate new examinations

remain available for

- audit

- analytics

- historical examination replay

---

# Generation

Quiz generation always follows

Active Board Blueprint

↓

Active Subject Blueprint

↓

Selection Engine

↓

Final Quiz

---

# Version History

Every blueprint stores

- version

- created date

- published date

- archived date

- author

- notes

Version history provides complete traceability.

---

# Design Principles

Blueprints are immutable.

Blueprints are versioned.

Blueprints are auditable.

Blueprints remain independent from question storage.

