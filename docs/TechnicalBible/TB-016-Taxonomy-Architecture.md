# TB-016 Taxonomy and Reusable Subject Architecture

## Purpose

BoardPrep treats boards, subjects, domains, topics, and concepts as separate
reusable records.

The taxonomy is curriculum metadata. It is not a second blueprint system.

The canonical hierarchy is:

```text
Board
  └── Board-Subject relationship
        └── Subject
              └── Domain
                    └── Topic
                          └── Concept
                                └── Question
```

## Canonical storage

The JSON storage driver uses one collection per root-level file:

```text
storage/
├── boards.json
├── subjects.json
├── board-subjects.json
├── domains.json
├── topics.json
├── concepts.json
├── blueprints.json
└── questions.json
```

The following identifiers are stable primary keys:

- `board.id`
- `subject.id`
- `domain.id`
- `topic.id`
- `concept.id`

References use IDs rather than copying the parent record.

## Board

A board identifies an examination family.

Example:

```json
{
    "id": "let",
    "organization_id": "prc",
    "code": "LET",
    "name": "Licensure Examination for Teachers",
    "description": "...",
    "status": "active",
    "settings": {
        "passing_score": 75,
        "default_mode": "practice"
    }
}
```

A board does **not** own a private copy of its subjects.

## Subject

A subject is a reusable curriculum unit that may participate in multiple
boards.

Example:

```json
{
    "id": "english",
    "code": "ENG",
    "name": "English",
    "description": "...",
    "category": "specialization",
    "status": "active"
}
```

The subject record contains no board ID.

This allows a subject such as English, Mathematics, or General Education to
be reused by future examinations without duplicating its taxonomy.

### Subject categories

The initial vocabulary is:

- `general`
- `professional`
- `specialization`

The category describes the role of a subject, not its board-specific weight.

## Board-Subject relationship

Board participation is modeled separately:

```json
{
    "id": "let-english",
    "board_id": "let",
    "subject_id": "english",
    "settings": {
        "track": "secondary",
        "role": "specialization",
        "required": true,
        "sort_order": 3,
        "blueprint_weight": 40
    }
}
```

This relationship is where board-specific metadata belongs.

The subject remains reusable.

`blueprint_weight` is seed/configuration data for the current prototype. The
authoritative runtime distribution is still owned by the Board Blueprint as
defined by the Blueprint Bible.

## Domain

A domain is a major learning area inside exactly one subject.

```json
{
    "id": "grammar",
    "subject_id": "english",
    "code": "GRAM",
    "name": "Grammar",
    "description": "...",
    "learning": {
        "difficulty": "medium",
        "estimated_study_minutes": 120,
        "mastery_threshold": 80
    }
}
```

Domains do not belong directly to boards.

If a board uses the same subject, it receives the subject's taxonomy through
the subject relationship.

## Topic

A topic is a focused learning area inside exactly one domain.

```json
{
    "id": "subject-verb-agreement",
    "domain_id": "grammar",
    "code": "SUBJVERB",
    "name": "Subject-Verb Agreement",
    "description": "...",
    "learning": {
        "difficulty": "medium",
        "estimated_study_minutes": 60,
        "mastery_threshold": 80,
        "prerequisite_ids": [],
        "learning_objectives": []
    }
}
```

## Concept

A concept is the smallest reusable learning unit in the taxonomy.

```json
{
    "id": "subject-verb-agreement-singular-subjects",
    "topic_id": "subject-verb-agreement",
    "code": "SINGULAR",
    "name": "Singular Subjects",
    "description": "...",
    "learning": {
        "difficulty": "medium",
        "estimated_study_minutes": 20,
        "mastery_threshold": 85,
        "keywords": [],
        "common_misconceptions": []
    }
}
```

## Question relationship

Questions should reference taxonomy through IDs:

```json
{
    "taxonomy": {
        "board_id": "let",
        "subject_id": "english",
        "domain_id": "grammar",
        "topic_id": "subject-verb-agreement",
        "concept_id": "subject-verb-agreement-singular-subjects"
    }
}
```

A question therefore has a complete navigable taxonomy path without copying
taxonomy records.

## Responsibility boundaries

### BoardController

Owns board management UI actions.

It does not contain curriculum definitions.

### SubjectController

Owns reusable subject management.

It does not contain board-specific subject weighting.

### BoardViewService

Builds board presentation data and resolves board-subject relationships.

### SubjectViewService

Builds subject presentation data.

### Taxonomy storage

Taxonomy storage resolves:

```text
subject → domains
domain  → topics
topic   → concepts
board   → subject relationships
```

### Blueprint

Blueprints decide examination composition.

They do not create taxonomy.

The established Blueprint rules remain:

- Board Blueprint → subject distribution.
- Subject Blueprint → domain distribution and difficulty.
- No Domain Blueprint.
- No Topic Blueprint.
- No Concept Blueprint.
- Topic and concept balancing remain quiz-engine behavior.

## LET Phase 2 seed

The initial LET taxonomy includes reusable subjects for:

- General Education
- Professional Education
- English
- Filipino
- Mathematics
- Science
- Social Studies
- Elementary Education
- Early Childhood Education
- Special Needs Education

The initial secondary LET prototype relationship includes:

- General Education
- Professional Education
- English specialization

The seed also includes reusable Civil Service-oriented subjects so future
board support does not require a second subject model:

- Verbal Ability
- Numerical Ability
- General Information
- Clerical Ability

## English Phase 1 taxonomy

English has the most complete initial taxonomy because it is the first
specialization target.

Its domains include:

- Grammar
- Reading Comprehension
- Vocabulary
- Literature
- Writing
- Oral Communication
- Language Use
- Research and Information Literacy

Topics and concepts are seeded beneath these domains for question-authoring
and future learning analytics.

## Seed data is not an official examination specification

The initial taxonomy is BoardPrep curriculum seed data intended to support the
Phase 1/Phase 2 implementation.

It must not be treated as an official PRC or CSC Table of Specifications.
Official examination coverage and percentages must be verified against the
current authoritative examination specification before production use.

## Design rule

Do not create:

```text
LET English
LET Mathematics
LET General Education
```

as separate subject copies.

Create:

```text
English
Mathematics
General Education
```

once, then connect them to boards through `board-subjects`.

This is the rule that keeps the curriculum reusable as BoardPrep expands.
