# Question cognitive-intent rubric

`cognitive_intent` describes the reasoning a learner must use to answer the
question. It is separate from the stored difficulty label. Difficulty is not
evidence for a cognitive-intent classification.

| Level | Use when the actual task requires |
| --- | --- |
| `RECALL` | Retrieving or recognizing a fact, term, rule, or procedure without applying it to a new case. |
| `UNDERSTANDING` | Explaining, interpreting, or comparing meaning and relationships. |
| `APPLICATION` | Applying a known rule, method, or calculation to the presented sentence, data, or scenario. |
| `ANALYSIS` | Separating evidence or components and reasoning about their relationships, structure, or implications. |
| `EVALUATION` | Judging alternatives against stated criteria or evidence and supporting the judgment. |

Classify from the complete stem, answer choices, required reasoning, and
explanation. Do not assign a level from keywords, difficulty, board, or item
format alone. A compound label is acceptable only when each named level is
supported by the task. If the task permits more than one defensible reading,
leave the item unclassified and report `INSUFFICIENT_EVIDENCE`.

Existing values such as `apply`, `application`, `analyze`, and `understand` are
legacy spellings of the rubric levels. Doctor maps known aliases for validation;
it does not create or replace a question's label. Unknown values and strong
metadata contradictions are reported for human review. `multistep=true` on a
`RECALL` item is a review signal, not an automatic reclassification.

When a repeated question task is genuinely identical, one reviewed task may
support its content-equivalent canonical copies only after their answer,
explanation, taxonomy, source links, and board eligibility match. Canonical IDs
remain distinct, and learner history is never merged by this rubric.
