# Batch 686 human academic review packet

**State: REVIEW_REQUIRED. No item in this packet has human academic approval.**

Use an independent subject-matter reviewer. Record the reviewer's name and
relevant qualification, date, decision, evidence checked, and any requested
change in the corresponding `storage/academic-review-decisions.json` record.
Do not change `human_signoff=NOT_RECORDED` or a pending state without authentic
review evidence. A requested edit must preserve the original fields and create
a new traceable decision record.

## Questions 616 and 937

| Question | Exact recorded change | Educational reason and evidence | Remaining review |
|---|---|---|---|
| 616 — Civil Service, percentage of a base | Explanation changed from `0.18 × 250 = 45.` to: “18% is 0.18, so multiply the stated base by 0.18: 0.18 × 250 = 45. As a check, 10% of 250 is 25 and 8% is 20; together they give 45.” Answer remains `45`. | OpenStax *Prealgebra 2e*, §6.2, “Solve General Applications of Percent,” explains percent as a proportion of a stated base. Both the direct calculation and 10% + 8% check give 45. The source supports the method, not examination frequency. | Confirm wording, pedagogical clarity, and that the distractors match the stated misconception. Decision `batch685-question-616-rationale` remains pending. |
| 937 — Nursing, purpose of hand hygiene | `cognitive_intent` changed from absent to `understand`. The question, answer, and rationale were not changed. Key remains “it reduces transfer of organisms between the client, staff, and environment.” | The “why” prompt asks for the meaning of the practice; the keyed answer and rationale describe transmission reduction. CDC, “Core Infection Prevention and Control Practices,” subsection “Hand Hygiene,” supplies the supporting infection-control principle. | A nursing reviewer should confirm the clinical phrasing against current Philippine practice and the linked infection-prevention lesson. Decision `batch685-question-937-cognitive-intent` remains pending. |

The source records are registered under `openstax-prealgebra-percent-applications-6-2`
and `cdc-infection-control-basics`. The source-review ledger also contains
bounded records for these questions; it does not supersede human sign-off.

## Batch 686 edits awaiting review

The JSON decision ledger contains the exact before/after values, evidence note,
reason, and validation record for every edit.

- Cognitive intent assigned, pending review: **25** — CE `7045, 1181, 1146,
  1227, 1137, 1138, 1141, 1142, 1143, 1234`; CSE `5413, 5301, 5258, 5281,
  5226`; LET `5993, 5763, 5538, 5548, 5563`; Nursing `864, 930, 897, 895,
  861`.
- Rationale and structured calculation expanded, pending review: **10** —
  `622, 6334, 6353, 5667, 1387, 1397, 3533, 4622, 1589, 1607`.
- The numerical explanations recompute the stated inputs, units, and keyed
  value. Questions `1387`, `1397`, `3533`, `1589`, and `1607` use the explicit
  formula encoded by the stem or standard introductory mechanics/calculus
  reasoning; no unsupported citation is added.
- Questions left unlabelled in the bounded cognitive sample remain visible in
  `storage/cognitive-intent-review-samples.json` as `REVIEW_REQUIRED`; no label
  was inferred for those rows.

All 35 changes remain `REVIEW_REQUIRED` / `APPLIED_PENDING_HUMAN_SIGNOFF` with
`human_signoff=NOT_RECORDED`. The academic reviewer should verify each full
question, choices, answer key, explanation, taxonomy, and applicable source,
then accept, request a correction, or reject the proposed change. Automated
recomputation is not academic approval.

## Duplicate and clone packet

`docs/batches/batch-686-duplicate-triage.json` contains 85 current exact
duplicate clusters, 85 matching normalized findings, three carried-forward
variant groups, and 179 heuristic clone connected components. Every exact
cluster matches the prior Batch 681 review packet and its current authored
question fields remain identical. Those are distinct canonical IDs; all IDs,
eligibility, lesson links, attempts, history, counters, and flashcard
relationships remain intact. The heuristic groups remain
`INSUFFICIENT_EVIDENCE`. Review them as questions, not as an instruction to
merge records.

## Sign-off record template

```text
QUESTION_OR_CLUSTER_ID=
REVIEWER_NAME_AND_QUALIFICATION=
REVIEW_DATE=
DECISION=APPROVE|REQUEST_CHANGE|REJECT
FIELDS_AND_EVIDENCE_REVIEWED=
REVIEWER_NOTES=
AUTHENTIC_APPROVAL_EVIDENCE_REFERENCE=
```
