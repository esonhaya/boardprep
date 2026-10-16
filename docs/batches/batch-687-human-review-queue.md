# Batch 687 human academic sign-off queue

**Status:** 37 edits remain pending human approval. This is a handoff, not a claim of independent review.

## Reviewer procedure

1. An independent reviewer selects a relevant item and checks the entire question, all choices, keyed answer, explanation, sources, and proposed before/after fields.
2. Record reviewer name/alias, qualifications, date, question ID, decision (`APPROVE`, `REQUEST_CHANGES`, or `REJECT`), reasoning, and any requested correction in the blank decision fields in the JSON handoff. Do not edit the audit trail to imply approval before the reviewer responds.
3. An authorized editor applies the recorded decision and updates the existing decision ledger with the evidence. Keep rejected or requested-change proposals visible. Passing automated tests is not academic approval.

Q616 and Q937 are included. All 37 records retain their existing `REVIEW_REQUIRED`, `APPLIED_PENDING_HUMAN_SIGNOFF`, and `NOT_RECORDED` states. No reviewer identity, credential, date, signature, or approval was supplied or invented.

## 1. Question 616 — civil-service — RATIONALE_TOO_EMPTY_TO_SUPPORT_KEY

**Question:** Using the supplied figures, what amount equals the stated percentage of the base?

**Choices:**
1. 35
2. 50
3. 45
4. 55

**Current key:** 45

**Current explanation:** 18% is 0.18, so multiply the stated base by 0.18: 0.18 × 250 = 45. As a check, 10% of 250 is 25 and 8% is 20; together they give 45.

**Proposed changes:**
- `explanation`: `"0.18 × 250 = 45."` → `"18% is 0.18, so multiply the stated base by 0.18: 0.18 × 250 = 45. As a check, 10% of 250 is 25 and 8% is 20; together they give 45."`

**Educational reasoning:** The original calculation was correct but too compressed to show percent conversion, use of the stated base, or an independent mental check.

**Validation currently recorded:** PASS: 0.18 × 250 = 45; 10% of 250 is 25 and 8% is 20, totaling 45; rationale now explains the conversion and base.

**Potential ambiguity / required check:** Independently verify the answer, formula/units, choices, and whether the added steps teach rather than overstate the method.

**Recommended expertise:** Assessment editor experienced in public-service numerical/English examination items

**Source evidence:**
- `openstax-prealgebra-percent-applications-6-2` — Prealgebra 2e, Section 6.2: Solve General Applications of Percent; OpenStax, Rice University; locator: Section 6.2, Solve General Applications of Percent; worked percent applications and checking against the stated base.; [source](https://openstax.org/books/prealgebra-2e/pages/6-2-solve-general-applications-of-percent) (status: `verified_reference`).
- `openstax-prealgebra-proportions-chapter6-5` — Prealgebra 2e, Section 6.5: Solve Proportions and Their Applications; OpenStax; locator: No question-level locator recorded; [source](https://openstax.org/books/prealgebra-2e/pages/6-5-solve-proportions-and-their-applications) (status: `verified_reference`).

**Approval state:** `REVIEW_REQUIRED` / `APPLIED_PENDING_HUMAN_SIGNOFF` / `NOT_RECORDED`

**Reviewer decision (leave blank until authentic response):**

```text
REVIEWER_IDENTITY=
QUALIFICATIONS=
REVIEW_DATE=
DECISION=APPROVE|REQUEST_CHANGES|REJECT
RATIONALE=
REQUESTED_CHANGES=
```

## 2. Question 937 — nursing — MISSING_COGNITIVE_INTENT

**Question:** A client asks why hand hygiene is performed before and after contact. What is the best answer?

**Choices:**
1. it reduces transfer of organisms between the client, staff, and environment
2. it sterilizes the entire room
3. it eliminates the need for gloves
4. it is required only after visible dirt

**Current key:** it reduces transfer of organisms between the client, staff, and environment

**Current explanation:** Hand hygiene interrupts transmission before and after contact; it does not sterilize a room.

**Proposed changes:**
- `cognitive_intent`: `null` → `"understand"`

**Educational reasoning:** The item asks the learner to explain why hand hygiene is performed; selecting the transmission-reduction purpose requires understanding, and the key and explanation support that reading.

**Validation currently recorded:** PASS: question wording, keyed answer, and rationale align with the stated understanding intent and the CDC Standard Precautions reference.

**Potential ambiguity / required check:** Confirm the task demand and cognitive-intent rubric fit from the complete stem/options/key; do not infer from topic or answer form.

**Recommended expertise:** Registered nurse educator with current Philippine nursing/public-health practice expertise

**Source evidence:**
- `cdc-infection-control-basics` — Core Infection Prevention and Control Practices; U.S. Centers for Disease Control and Prevention; locator: CDC Core Infection Prevention and Control Practices, “Standard Precautions” and “Hand Hygiene” subsections.; [source](https://www.cdc.gov/infection-control/hcp/core-practices/) (status: `periodically_updated`).

**Approval state:** `REVIEW_REQUIRED` / `APPLIED_PENDING_HUMAN_SIGNOFF` / `NOT_RECORDED`

**Reviewer decision (leave blank until authentic response):**

```text
REVIEWER_IDENTITY=
QUALIFICATIONS=
REVIEW_DATE=
DECISION=APPROVE|REQUEST_CHANGES|REJECT
RATIONALE=
REQUESTED_CHANGES=
```

## 3. Question 7045 — civil-engineering — MISSING_COGNITIVE_INTENT

**Question:** A plan length of 6 cm is drawn at a representative fraction of 1:5000. What ground length is represented in meters?

**Choices:**
1. 336 m
2. 300 m
3. 264 m
4. 600 m

**Current key:** 300 m

**Current explanation:** Use the stated unit-scale relationship. Substitution of the supplied values gives 300 m; the distractors represent common denominator, sign, or factor errors.

**Proposed changes:**
- `cognitive_intent`: `null` → `"apply"`

**Educational reasoning:** The stem requires use of an explicit rule or procedure on supplied values or a stated case; this describes the demonstrated task, not topic or answer format. Stem: A plan length of 6 cm is drawn at a representative fraction of 1:5000. What ground length is represented in meters?

**Validation currently recorded:** Label maps to existing rubric; human confirmation remains required.

**Potential ambiguity / required check:** Confirm the task demand and cognitive-intent rubric fit from the complete stem/options/key; do not infer from topic or answer form.

**Recommended expertise:** Licensed civil engineer and discipline educator; verify formula, units, and engineering context

**Source evidence:**
- No source identifier is registered on this decision; review using the supplied item and demonstrate the independent reasoning or add a verified authoritative citation before approval.

**Approval state:** `REVIEW_REQUIRED` / `APPLIED_PENDING_HUMAN_SIGNOFF` / `NOT_RECORDED`

**Reviewer decision (leave blank until authentic response):**

```text
REVIEWER_IDENTITY=
QUALIFICATIONS=
REVIEW_DATE=
DECISION=APPROVE|REQUEST_CHANGES|REJECT
RATIONALE=
REQUESTED_CHANGES=
```

## 4. Question 1181 — civil-engineering — MISSING_COGNITIVE_INTENT

**Question:** A rectangular excavation is 12 m long, 4 m wide, and 1.5 m deep. Volume is:

**Choices:**
1. 72 m³
2. 48 m³
3. 18 m³
4. 288 m³

**Current key:** 72 m³

**Current explanation:** Volume = 12×4×1.5 = 72 m³.

**Proposed changes:**
- `cognitive_intent`: `null` → `"apply"`

**Educational reasoning:** The stem requires use of an explicit rule or procedure on supplied values or a stated case; this describes the demonstrated task, not topic or answer format. Stem: A rectangular excavation is 12 m long, 4 m wide, and 1.5 m deep. Volume is:

**Validation currently recorded:** Label maps to existing rubric; human confirmation remains required.

**Potential ambiguity / required check:** Confirm the task demand and cognitive-intent rubric fit from the complete stem/options/key; do not infer from topic or answer form.

**Recommended expertise:** Licensed civil engineer and discipline educator; verify formula, units, and engineering context

**Source evidence:**
- No source identifier is registered on this decision; review using the supplied item and demonstrate the independent reasoning or add a verified authoritative citation before approval.

**Approval state:** `REVIEW_REQUIRED` / `APPLIED_PENDING_HUMAN_SIGNOFF` / `NOT_RECORDED`

**Reviewer decision (leave blank until authentic response):**

```text
REVIEWER_IDENTITY=
QUALIFICATIONS=
REVIEW_DATE=
DECISION=APPROVE|REQUEST_CHANGES|REJECT
RATIONALE=
REQUESTED_CHANGES=
```

## 5. Question 1146 — civil-engineering — MISSING_COGNITIVE_INTENT

**Question:** A footing carries 900 kN on soil allowable pressure 150 kPa. Minimum plan area is:

**Choices:**
1. 6 m²
2. 0.167 m²
3. 1350 m²
4. 750 m²

**Current key:** 6 m²

**Current explanation:** Area = load/pressure = 900 kN ÷ 150 kN/m² = 6 m².

**Proposed changes:**
- `cognitive_intent`: `null` → `"apply"`

**Educational reasoning:** The stem requires use of an explicit rule or procedure on supplied values or a stated case; this describes the demonstrated task, not topic or answer format. Stem: A footing carries 900 kN on soil allowable pressure 150 kPa. Minimum plan area is:

**Validation currently recorded:** Label maps to existing rubric; human confirmation remains required.

**Potential ambiguity / required check:** Confirm the task demand and cognitive-intent rubric fit from the complete stem/options/key; do not infer from topic or answer form.

**Recommended expertise:** Licensed civil engineer and discipline educator; verify formula, units, and engineering context

**Source evidence:**
- No source identifier is registered on this decision; review using the supplied item and demonstrate the independent reasoning or add a verified authoritative citation before approval.

**Approval state:** `REVIEW_REQUIRED` / `APPLIED_PENDING_HUMAN_SIGNOFF` / `NOT_RECORDED`

**Reviewer decision (leave blank until authentic response):**

```text
REVIEWER_IDENTITY=
QUALIFICATIONS=
REVIEW_DATE=
DECISION=APPROVE|REQUEST_CHANGES|REJECT
RATIONALE=
REQUESTED_CHANGES=
```

## 6. Question 1227 — civil-engineering — MISSING_COGNITIVE_INTENT

**Question:** A saturated soil has total stress 180 kPa and pore pressure 70 kPa. Effective stress is:

**Choices:**
1. 110 kPa
2. 250 kPa
3. 70 kPa
4. 2.57 kPa

**Current key:** 110 kPa

**Current explanation:** Effective stress equals total stress minus pore pressure: 180−70=110 kPa.

**Proposed changes:**
- `cognitive_intent`: `null` → `"apply"`

**Educational reasoning:** The stem requires use of an explicit rule or procedure on supplied values or a stated case; this describes the demonstrated task, not topic or answer format. Stem: A saturated soil has total stress 180 kPa and pore pressure 70 kPa. Effective stress is:

**Validation currently recorded:** Label maps to existing rubric; human confirmation remains required.

**Potential ambiguity / required check:** Confirm the task demand and cognitive-intent rubric fit from the complete stem/options/key; do not infer from topic or answer form.

**Recommended expertise:** Licensed civil engineer and discipline educator; verify formula, units, and engineering context

**Source evidence:**
- No source identifier is registered on this decision; review using the supplied item and demonstrate the independent reasoning or add a verified authoritative citation before approval.

**Approval state:** `REVIEW_REQUIRED` / `APPLIED_PENDING_HUMAN_SIGNOFF` / `NOT_RECORDED`

**Reviewer decision (leave blank until authentic response):**

```text
REVIEWER_IDENTITY=
QUALIFICATIONS=
REVIEW_DATE=
DECISION=APPROVE|REQUEST_CHANGES|REJECT
RATIONALE=
REQUESTED_CHANGES=
```

## 7. Question 1137 — civil-engineering — MISSING_COGNITIVE_INTENT

**Question:** A simply supported beam carries a 10 kN point load at midspan over 6 m. What is the reaction at each support?

**Choices:**
1. 5 kN
2. 10 kN
3. 3 kN
4. 60 kN

**Current key:** 5 kN

**Current explanation:** Symmetry gives equal reactions: (10 kN)/2 = 5 kN.

**Proposed changes:**
- `cognitive_intent`: `null` → `"apply"`

**Educational reasoning:** The stem requires use of an explicit rule or procedure on supplied values or a stated case; this describes the demonstrated task, not topic or answer format. Stem: A simply supported beam carries a 10 kN point load at midspan over 6 m. What is the reaction at each support?

**Validation currently recorded:** Label maps to existing rubric; human confirmation remains required.

**Potential ambiguity / required check:** Confirm the task demand and cognitive-intent rubric fit from the complete stem/options/key; do not infer from topic or answer form.

**Recommended expertise:** Licensed civil engineer and discipline educator; verify formula, units, and engineering context

**Source evidence:**
- No source identifier is registered on this decision; review using the supplied item and demonstrate the independent reasoning or add a verified authoritative citation before approval.

**Approval state:** `REVIEW_REQUIRED` / `APPLIED_PENDING_HUMAN_SIGNOFF` / `NOT_RECORDED`

**Reviewer decision (leave blank until authentic response):**

```text
REVIEWER_IDENTITY=
QUALIFICATIONS=
REVIEW_DATE=
DECISION=APPROVE|REQUEST_CHANGES|REJECT
RATIONALE=
REQUESTED_CHANGES=
```

## 8. Question 1234 — civil-engineering — MISSING_COGNITIVE_INTENT

**Question:** A seepage head drops 6 m over a flow length of 30 m. Hydraulic gradient is:

**Choices:**
1. 0.20
2. 5.0
3. 36
4. 0.02

**Current key:** 0.20

**Current explanation:** Hydraulic gradient is head loss per flow length: i = Δh/L = 6 m ÷ 30 m = 0.20, a dimensionless ratio. It is distinct from hydraulic conductivity k, which appears separately in Darcy flow.

**Proposed changes:**
- `cognitive_intent`: `null` → `"apply"`

**Educational reasoning:** The stem requires use of an explicit rule or procedure on supplied values or a stated case; this describes the demonstrated task, not topic or answer format. Stem: A seepage head drops 6 m over a flow length of 30 m. Hydraulic gradient is:

**Validation currently recorded:** Label maps to existing rubric; human confirmation remains required.

**Potential ambiguity / required check:** Confirm the task demand and cognitive-intent rubric fit from the complete stem/options/key; do not infer from topic or answer form.

**Recommended expertise:** Licensed civil engineer and discipline educator; verify formula, units, and engineering context

**Source evidence:**
- No source identifier is registered on this decision; review using the supplied item and demonstrate the independent reasoning or add a verified authoritative citation before approval.

**Approval state:** `REVIEW_REQUIRED` / `APPLIED_PENDING_HUMAN_SIGNOFF` / `NOT_RECORDED`

**Reviewer decision (leave blank until authentic response):**

```text
REVIEWER_IDENTITY=
QUALIFICATIONS=
REVIEW_DATE=
DECISION=APPROVE|REQUEST_CHANGES|REJECT
RATIONALE=
REQUESTED_CHANGES=
```

## 9. Question 1138 — civil-engineering — MISSING_COGNITIVE_INTENT

**Question:** A 12 kN force acts perpendicular to a lever arm 2.5 m long. Find the moment about the pivot.

**Choices:**
1. 30 kN·m
2. 4.8 kN·m
3. 14.5 kN·m
4. 24 kN·m

**Current key:** 30 kN·m

**Current explanation:** The moment is Fd = 12 × 2.5 = 30 kN·m.

**Proposed changes:**
- `cognitive_intent`: `null` → `"apply"`

**Educational reasoning:** The stem requires use of an explicit rule or procedure on supplied values or a stated case; this describes the demonstrated task, not topic or answer format. Stem: A 12 kN force acts perpendicular to a lever arm 2.5 m long. Find the moment about the pivot.

**Validation currently recorded:** Label maps to existing rubric; human confirmation remains required.

**Potential ambiguity / required check:** Confirm the task demand and cognitive-intent rubric fit from the complete stem/options/key; do not infer from topic or answer form.

**Recommended expertise:** Licensed civil engineer and discipline educator; verify formula, units, and engineering context

**Source evidence:**
- No source identifier is registered on this decision; review using the supplied item and demonstrate the independent reasoning or add a verified authoritative citation before approval.

**Approval state:** `REVIEW_REQUIRED` / `APPLIED_PENDING_HUMAN_SIGNOFF` / `NOT_RECORDED`

**Reviewer decision (leave blank until authentic response):**

```text
REVIEWER_IDENTITY=
QUALIFICATIONS=
REVIEW_DATE=
DECISION=APPROVE|REQUEST_CHANGES|REJECT
RATIONALE=
REQUESTED_CHANGES=
```

## 10. Question 1141 — civil-engineering — MISSING_COGNITIVE_INTENT

**Question:** A rectangular section is 100 mm wide and 200 mm deep. Its second moment of area about the centroidal horizontal axis is:

**Choices:**
1. 66.7 × 10^6 mm⁴
2. 2.0 × 10^6 mm⁴
3. 133.3 × 10^6 mm⁴
4. 6.67 × 10^3 mm⁴

**Current key:** 66.7 × 10^6 mm⁴

**Current explanation:** For a rectangle I = bd³/12 = 100(200³)/12 = 66.7 million mm⁴.

**Proposed changes:**
- `cognitive_intent`: `null` → `"apply"`

**Educational reasoning:** The stem requires use of an explicit rule or procedure on supplied values or a stated case; this describes the demonstrated task, not topic or answer format. Stem: A rectangular section is 100 mm wide and 200 mm deep. Its second moment of area about the centroidal horizontal axis is:

**Validation currently recorded:** Label maps to existing rubric; human confirmation remains required.

**Potential ambiguity / required check:** Confirm the task demand and cognitive-intent rubric fit from the complete stem/options/key; do not infer from topic or answer form.

**Recommended expertise:** Licensed civil engineer and discipline educator; verify formula, units, and engineering context

**Source evidence:**
- No source identifier is registered on this decision; review using the supplied item and demonstrate the independent reasoning or add a verified authoritative citation before approval.

**Approval state:** `REVIEW_REQUIRED` / `APPLIED_PENDING_HUMAN_SIGNOFF` / `NOT_RECORDED`

**Reviewer decision (leave blank until authentic response):**

```text
REVIEWER_IDENTITY=
QUALIFICATIONS=
REVIEW_DATE=
DECISION=APPROVE|REQUEST_CHANGES|REJECT
RATIONALE=
REQUESTED_CHANGES=
```

## 11. Question 1142 — civil-engineering — MISSING_COGNITIVE_INTENT

**Question:** A beam has bending moment 8 kN·m and section modulus 400 cm³. The bending stress is:

**Choices:**
1. 20 MPa
2. 2 MPa
3. 200 MPa
4. 3.2 MPa

**Current key:** 20 MPa

**Current explanation:** 8 kN·m = 8,000,000 N·mm and 400 cm³ = 400,000 mm³; σ=M/Z=20 MPa.

**Proposed changes:**
- `cognitive_intent`: `null` → `"apply"`

**Educational reasoning:** The stem requires use of an explicit rule or procedure on supplied values or a stated case; this describes the demonstrated task, not topic or answer format. Stem: A beam has bending moment 8 kN·m and section modulus 400 cm³. The bending stress is:

**Validation currently recorded:** Label maps to existing rubric; human confirmation remains required.

**Potential ambiguity / required check:** Confirm the task demand and cognitive-intent rubric fit from the complete stem/options/key; do not infer from topic or answer form.

**Recommended expertise:** Licensed civil engineer and discipline educator; verify formula, units, and engineering context

**Source evidence:**
- No source identifier is registered on this decision; review using the supplied item and demonstrate the independent reasoning or add a verified authoritative citation before approval.

**Approval state:** `REVIEW_REQUIRED` / `APPLIED_PENDING_HUMAN_SIGNOFF` / `NOT_RECORDED`

**Reviewer decision (leave blank until authentic response):**

```text
REVIEWER_IDENTITY=
QUALIFICATIONS=
REVIEW_DATE=
DECISION=APPROVE|REQUEST_CHANGES|REJECT
RATIONALE=
REQUESTED_CHANGES=
```

## 12. Question 1143 — civil-engineering — MISSING_COGNITIVE_INTENT

**Question:** A circular shaft transmits 600 N·m through a radius of 25 mm. The torque force couple at the rim is:

**Choices:**
1. 24 kN
2. 15 kN
3. 1.5 kN
4. 240 N

**Current key:** 24 kN

**Current explanation:** T = Fr, so F = 600/0.025 = 24,000 N.

**Proposed changes:**
- `cognitive_intent`: `null` → `"apply"`

**Educational reasoning:** The stem requires use of an explicit rule or procedure on supplied values or a stated case; this describes the demonstrated task, not topic or answer format. Stem: A circular shaft transmits 600 N·m through a radius of 25 mm. The torque force couple at the rim is:

**Validation currently recorded:** Label maps to existing rubric; human confirmation remains required.

**Potential ambiguity / required check:** Confirm the task demand and cognitive-intent rubric fit from the complete stem/options/key; do not infer from topic or answer form.

**Recommended expertise:** Licensed civil engineer and discipline educator; verify formula, units, and engineering context

**Source evidence:**
- No source identifier is registered on this decision; review using the supplied item and demonstrate the independent reasoning or add a verified authoritative citation before approval.

**Approval state:** `REVIEW_REQUIRED` / `APPLIED_PENDING_HUMAN_SIGNOFF` / `NOT_RECORDED`

**Reviewer decision (leave blank until authentic response):**

```text
REVIEWER_IDENTITY=
QUALIFICATIONS=
REVIEW_DATE=
DECISION=APPROVE|REQUEST_CHANGES|REJECT
RATIONALE=
REQUESTED_CHANGES=
```

## 13. Question 5413 — civil-service — MISSING_COGNITIVE_INTENT

**Question:** A code sequence increases by 4: 3, 7, 11, 15. What is next?

**Choices:**
1. 20
2. 23
3. 19
4. 18

**Current key:** 19

**Current explanation:** Adding four to 15 gives 19.

**Proposed changes:**
- `cognitive_intent`: `null` → `"apply"`

**Educational reasoning:** The stem requires use of an explicit rule or procedure on supplied values or a stated case; this describes the demonstrated task, not topic or answer format. Stem: A code sequence increases by 4: 3, 7, 11, 15. What is next?

**Validation currently recorded:** Label maps to existing rubric; human confirmation remains required.

**Potential ambiguity / required check:** Confirm the task demand and cognitive-intent rubric fit from the complete stem/options/key; do not infer from topic or answer form.

**Recommended expertise:** Assessment editor experienced in public-service numerical/English examination items

**Source evidence:**
- No source identifier is registered on this decision; review using the supplied item and demonstrate the independent reasoning or add a verified authoritative citation before approval.

**Approval state:** `REVIEW_REQUIRED` / `APPLIED_PENDING_HUMAN_SIGNOFF` / `NOT_RECORDED`

**Reviewer decision (leave blank until authentic response):**

```text
REVIEWER_IDENTITY=
QUALIFICATIONS=
REVIEW_DATE=
DECISION=APPROVE|REQUEST_CHANGES|REJECT
RATIONALE=
REQUESTED_CHANGES=
```

## 14. Question 5301 — civil-service — MISSING_COGNITIVE_INTENT

**Question:** A unit records 11, 17, and 23 completed cases over three days. What is the daily average?

**Choices:**
1. 12 completed cases
2. 28 completed cases
3. 17 completed cases
4. 51 completed cases

**Current key:** 17 completed cases

**Current explanation:** Add the three observations and divide by three.

**Proposed changes:**
- `cognitive_intent`: `null` → `"apply"`

**Educational reasoning:** The stem requires use of an explicit rule or procedure on supplied values or a stated case; this describes the demonstrated task, not topic or answer format. Stem: A unit records 11, 17, and 23 completed cases over three days. What is the daily average?

**Validation currently recorded:** Label maps to existing rubric; human confirmation remains required.

**Potential ambiguity / required check:** Confirm the task demand and cognitive-intent rubric fit from the complete stem/options/key; do not infer from topic or answer form.

**Recommended expertise:** Assessment editor experienced in public-service numerical/English examination items

**Source evidence:**
- No source identifier is registered on this decision; review using the supplied item and demonstrate the independent reasoning or add a verified authoritative citation before approval.

**Approval state:** `REVIEW_REQUIRED` / `APPLIED_PENDING_HUMAN_SIGNOFF` / `NOT_RECORDED`

**Reviewer decision (leave blank until authentic response):**

```text
REVIEWER_IDENTITY=
QUALIFICATIONS=
REVIEW_DATE=
DECISION=APPROVE|REQUEST_CHANGES|REJECT
RATIONALE=
REQUESTED_CHANGES=
```

## 15. Question 5258 — civil-service — MISSING_COGNITIVE_INTENT

**Question:** A desk logs 23 intake requests in one batch and 19 in another. How many are logged in all?

**Choices:**
1. 51 intake requests
2. 4 intake requests
3. 437 intake requests
4. 42 intake requests

**Current key:** 42 intake requests

**Current explanation:** Add the two stated counts.

**Proposed changes:**
- `cognitive_intent`: `null` → `"apply"`

**Educational reasoning:** The stem requires use of an explicit rule or procedure on supplied values or a stated case; this describes the demonstrated task, not topic or answer format. Stem: A desk logs 23 intake requests in one batch and 19 in another. How many are logged in all?

**Validation currently recorded:** Label maps to existing rubric; human confirmation remains required.

**Potential ambiguity / required check:** Confirm the task demand and cognitive-intent rubric fit from the complete stem/options/key; do not infer from topic or answer form.

**Recommended expertise:** Assessment editor experienced in public-service numerical/English examination items

**Source evidence:**
- No source identifier is registered on this decision; review using the supplied item and demonstrate the independent reasoning or add a verified authoritative citation before approval.

**Approval state:** `REVIEW_REQUIRED` / `APPLIED_PENDING_HUMAN_SIGNOFF` / `NOT_RECORDED`

**Reviewer decision (leave blank until authentic response):**

```text
REVIEWER_IDENTITY=
QUALIFICATIONS=
REVIEW_DATE=
DECISION=APPROVE|REQUEST_CHANGES|REJECT
RATIONALE=
REQUESTED_CHANGES=
```

## 16. Question 5226 — civil-service — MISSING_COGNITIVE_INTENT

**Question:** The notice says a release is contingent on a signed log, but the log is missing. What is the safest interpretation?

**Choices:**
1. the release is automatically approved
2. the word contingent means prohibited
3. the missing log is irrelevant
4. the release condition has not yet been shown to be met

**Current key:** the release condition has not yet been shown to be met

**Current explanation:** The context makes contingent a condition that still requires evidence.

**Proposed changes:**
- `cognitive_intent`: `null` → `"analyze"`

**Educational reasoning:** The stem requires comparing evidence, inferring a consequence, or prioritizing interacting case cues. Stem: The notice says a release is contingent on a signed log, but the log is missing. What is the safest interpretation?

**Validation currently recorded:** Label maps to existing rubric; human confirmation remains required.

**Potential ambiguity / required check:** Confirm the task demand and cognitive-intent rubric fit from the complete stem/options/key; do not infer from topic or answer form.

**Recommended expertise:** Assessment editor experienced in public-service numerical/English examination items

**Source evidence:**
- No source identifier is registered on this decision; review using the supplied item and demonstrate the independent reasoning or add a verified authoritative citation before approval.

**Approval state:** `REVIEW_REQUIRED` / `APPLIED_PENDING_HUMAN_SIGNOFF` / `NOT_RECORDED`

**Reviewer decision (leave blank until authentic response):**

```text
REVIEWER_IDENTITY=
QUALIFICATIONS=
REVIEW_DATE=
DECISION=APPROVE|REQUEST_CHANGES|REJECT
RATIONALE=
REQUESTED_CHANGES=
```

## 17. Question 5281 — civil-service — MISSING_COGNITIVE_INTENT

**Question:** A unit reports 20, 24, and 30 resolved requests over three days. Which statement is supported?

**Choices:**
1. the average proves causation
2. no comparison is possible
3. resolution increased across the three reported days
4. resolution decreased each day

**Current key:** resolution increased across the three reported days

**Current explanation:** The values rise from 20 to 24 to 30; no causal claim is supplied.

**Proposed changes:**
- `cognitive_intent`: `null` → `"analyze"`

**Educational reasoning:** The stem requires comparing evidence, inferring a consequence, or prioritizing interacting case cues. Stem: A unit reports 20, 24, and 30 resolved requests over three days. Which statement is supported?

**Validation currently recorded:** Label maps to existing rubric; human confirmation remains required.

**Potential ambiguity / required check:** Confirm the task demand and cognitive-intent rubric fit from the complete stem/options/key; do not infer from topic or answer form.

**Recommended expertise:** Assessment editor experienced in public-service numerical/English examination items

**Source evidence:**
- No source identifier is registered on this decision; review using the supplied item and demonstrate the independent reasoning or add a verified authoritative citation before approval.

**Approval state:** `REVIEW_REQUIRED` / `APPLIED_PENDING_HUMAN_SIGNOFF` / `NOT_RECORDED`

**Reviewer decision (leave blank until authentic response):**

```text
REVIEWER_IDENTITY=
QUALIFICATIONS=
REVIEW_DATE=
DECISION=APPROVE|REQUEST_CHANGES|REJECT
RATIONALE=
REQUESTED_CHANGES=
```

## 18. Question 5993 — let — MISSING_COGNITIVE_INTENT

**Question:** In Culture and Arts Education specialization, A performance allocates 4 minutes to setup and 6 minutes to the work. What is the total planned time?

**Choices:**
1. 46 minutes
2. 10 minutes
3. 2 minutes
4. 24 minutes

**Current key:** 10 minutes

**Current explanation:** Add the two planned intervals: 4 + 6 = 10 minutes.

**Proposed changes:**
- `cognitive_intent`: `null` → `"apply"`

**Educational reasoning:** The stem requires use of an explicit rule or procedure on supplied values or a stated case; this describes the demonstrated task, not topic or answer format. Stem: In Culture and Arts Education specialization, A performance allocates 4 minutes to setup and 6 minutes to the work. What is the total planned time?

**Validation currently recorded:** Label maps to existing rubric; human confirmation remains required.

**Potential ambiguity / required check:** Confirm the task demand and cognitive-intent rubric fit from the complete stem/options/key; do not infer from topic or answer form.

**Recommended expertise:** Teacher educator or language/education assessment specialist

**Source evidence:**
- No source identifier is registered on this decision; review using the supplied item and demonstrate the independent reasoning or add a verified authoritative citation before approval.

**Approval state:** `REVIEW_REQUIRED` / `APPLIED_PENDING_HUMAN_SIGNOFF` / `NOT_RECORDED`

**Reviewer decision (leave blank until authentic response):**

```text
REVIEWER_IDENTITY=
QUALIFICATIONS=
REVIEW_DATE=
DECISION=APPROVE|REQUEST_CHANGES|REJECT
RATIONALE=
REQUESTED_CHANGES=
```

## 19. Question 5763 — let — MISSING_COGNITIVE_INTENT

**Question:** Which statement best applies developmental observation in this situation: a learner meets an unfamiliar example during guided practice

**Choices:**
1. Record the observable behavior, context, and time before making an interpretation.
2. Ignore the controlling criterion and choose the most familiar response.
3. Apply an unrelated rule without checking the facts or purpose.
4. Treat a single surface feature as decisive even when the context contradicts it.

**Current key:** Record the observable behavior, context, and time before making an interpretation.

**Current explanation:** Record the observable behavior, context, and time before making an interpretation. The controlling rule is that observation is purposeful, documented, and interpreted in context rather than based on a single impression.

**Proposed changes:**
- `cognitive_intent`: `null` → `"apply"`

**Educational reasoning:** The stem requires use of an explicit rule or procedure on supplied values or a stated case; this describes the demonstrated task, not topic or answer format. Stem: Which statement best applies developmental observation in this situation: a learner meets an unfamiliar example during guided practice

**Validation currently recorded:** Label maps to existing rubric; human confirmation remains required.

**Potential ambiguity / required check:** Confirm the task demand and cognitive-intent rubric fit from the complete stem/options/key; do not infer from topic or answer form.

**Recommended expertise:** Teacher educator or language/education assessment specialist

**Source evidence:**
- No source identifier is registered on this decision; review using the supplied item and demonstrate the independent reasoning or add a verified authoritative citation before approval.

**Approval state:** `REVIEW_REQUIRED` / `APPLIED_PENDING_HUMAN_SIGNOFF` / `NOT_RECORDED`

**Reviewer decision (leave blank until authentic response):**

```text
REVIEWER_IDENTITY=
QUALIFICATIONS=
REVIEW_DATE=
DECISION=APPROVE|REQUEST_CHANGES|REJECT
RATIONALE=
REQUESTED_CHANGES=
```

## 20. Question 5538 — let — MISSING_COGNITIVE_INTENT

**Question:** Which statement best applies evidence-based inference in this situation: a learner meets an unfamiliar example during guided practice

**Choices:**
1. Choose the conclusion that follows from the stated evidence without adding an unstated motive.
2. Treat personal experience as evidence from the passage.
3. Choose the most dramatic possibility even when the text does not support it.
4. Convert an inference into a directly stated fact.

**Current key:** Choose the conclusion that follows from the stated evidence without adding an unstated motive.

**Current explanation:** Choose the conclusion that follows from the stated evidence without adding an unstated motive. The controlling rule is that an inference must be a reasonable conclusion supported by details in the text.

**Proposed changes:**
- `cognitive_intent`: `null` → `"analyze"`

**Educational reasoning:** The stem requires comparing evidence, inferring a consequence, or prioritizing interacting case cues. Stem: Which statement best applies evidence-based inference in this situation: a learner meets an unfamiliar example during guided practice

**Validation currently recorded:** Label maps to existing rubric; human confirmation remains required.

**Potential ambiguity / required check:** Confirm the task demand and cognitive-intent rubric fit from the complete stem/options/key; do not infer from topic or answer form.

**Recommended expertise:** Teacher educator or language/education assessment specialist

**Source evidence:**
- No source identifier is registered on this decision; review using the supplied item and demonstrate the independent reasoning or add a verified authoritative citation before approval.

**Approval state:** `REVIEW_REQUIRED` / `APPLIED_PENDING_HUMAN_SIGNOFF` / `NOT_RECORDED`

**Reviewer decision (leave blank until authentic response):**

```text
REVIEWER_IDENTITY=
QUALIFICATIONS=
REVIEW_DATE=
DECISION=APPROVE|REQUEST_CHANGES|REJECT
RATIONALE=
REQUESTED_CHANGES=
```

## 21. Question 5548 — let — MISSING_COGNITIVE_INTENT

**Question:** Which statement best applies Pangunahing diwa in this situation: a learner meets an unfamiliar example during guided practice

**Choices:**
1. Piliin ang pahayag na sumasaklaw sa mahahalagang detalye ng talata.
2. Ignore the controlling criterion and choose the most familiar response.
3. Apply an unrelated rule without checking the facts or purpose.
4. Treat a single surface feature as decisive even when the context contradicts it.

**Current key:** Piliin ang pahayag na sumasaklaw sa mahahalagang detalye ng talata.

**Current explanation:** Piliin ang pahayag na sumasaklaw sa mahahalagang detalye ng talata. The controlling rule is that the main idea states the central point supported by the important details.

**Proposed changes:**
- `cognitive_intent`: `null` → `"understand"`

**Educational reasoning:** The stem asks the learner to explain or recognize the meaning of a stated principle in context. Stem: Which statement best applies Pangunahing diwa in this situation: a learner meets an unfamiliar example during guided practice

**Validation currently recorded:** Label maps to existing rubric; human confirmation remains required.

**Potential ambiguity / required check:** Confirm the task demand and cognitive-intent rubric fit from the complete stem/options/key; do not infer from topic or answer form.

**Recommended expertise:** Teacher educator or language/education assessment specialist

**Source evidence:**
- No source identifier is registered on this decision; review using the supplied item and demonstrate the independent reasoning or add a verified authoritative citation before approval.

**Approval state:** `REVIEW_REQUIRED` / `APPLIED_PENDING_HUMAN_SIGNOFF` / `NOT_RECORDED`

**Reviewer decision (leave blank until authentic response):**

```text
REVIEWER_IDENTITY=
QUALIFICATIONS=
REVIEW_DATE=
DECISION=APPROVE|REQUEST_CHANGES|REJECT
RATIONALE=
REQUESTED_CHANGES=
```

## 22. Question 5563 — let — MISSING_COGNITIVE_INTENT

**Question:** Which statement best applies Tauhan at motibasyon in this situation: a learner meets an unfamiliar example during guided practice

**Choices:**
1. Gamitin ang kilos at pananalita ng tauhan bilang patunay sa motibasyon.
2. Ignore the controlling criterion and choose the most familiar response.
3. Apply an unrelated rule without checking the facts or purpose.
4. Treat a single surface feature as decisive even when the context contradicts it.

**Current key:** Gamitin ang kilos at pananalita ng tauhan bilang patunay sa motibasyon.

**Current explanation:** Gamitin ang kilos at pananalita ng tauhan bilang patunay sa motibasyon. The controlling rule is that a character interpretation should be grounded in actions, dialogue, and stated circumstances.

**Proposed changes:**
- `cognitive_intent`: `null` → `"analyze"`

**Educational reasoning:** The stem requires comparing evidence, inferring a consequence, or prioritizing interacting case cues. Stem: Which statement best applies Tauhan at motibasyon in this situation: a learner meets an unfamiliar example during guided practice

**Validation currently recorded:** Label maps to existing rubric; human confirmation remains required.

**Potential ambiguity / required check:** Confirm the task demand and cognitive-intent rubric fit from the complete stem/options/key; do not infer from topic or answer form.

**Recommended expertise:** Teacher educator or language/education assessment specialist

**Source evidence:**
- No source identifier is registered on this decision; review using the supplied item and demonstrate the independent reasoning or add a verified authoritative citation before approval.

**Approval state:** `REVIEW_REQUIRED` / `APPLIED_PENDING_HUMAN_SIGNOFF` / `NOT_RECORDED`

**Reviewer decision (leave blank until authentic response):**

```text
REVIEWER_IDENTITY=
QUALIFICATIONS=
REVIEW_DATE=
DECISION=APPROVE|REQUEST_CHANGES|REJECT
RATIONALE=
REQUESTED_CHANGES=
```

## 23. Question 861 — nursing — MISSING_COGNITIVE_INTENT

**Question:** A family with an infant stores kerosene in an unlabelled bottle on the floor. What should the home visit emphasize?

**Choices:**
1. locked, labelled storage out of the child’s reach
2. a new feeding schedule
3. routine blood-pressure screening only
4. keeping the bottle under a chair

**Current key:** locked, labelled storage out of the child’s reach

**Current explanation:** Safe storage removes a preventable poisoning hazard; the other choices do not address the observed risk.

**Proposed changes:**
- `cognitive_intent`: `null` → `"apply"`

**Educational reasoning:** The stem requires use of an explicit rule or procedure on supplied values or a stated case; this describes the demonstrated task, not topic or answer format. Stem: A family with an infant stores kerosene in an unlabelled bottle on the floor. What should the home visit emphasize?

**Validation currently recorded:** Label maps to existing rubric; human confirmation remains required.

**Potential ambiguity / required check:** Confirm the task demand and cognitive-intent rubric fit from the complete stem/options/key; do not infer from topic or answer form.

**Recommended expertise:** Registered nurse educator with current Philippine nursing/public-health practice expertise

**Source evidence:**
- No source identifier is registered on this decision; review using the supplied item and demonstrate the independent reasoning or add a verified authoritative citation before approval.

**Approval state:** `REVIEW_REQUIRED` / `APPLIED_PENDING_HUMAN_SIGNOFF` / `NOT_RECORDED`

**Reviewer decision (leave blank until authentic response):**

```text
REVIEWER_IDENTITY=
QUALIFICATIONS=
REVIEW_DATE=
DECISION=APPROVE|REQUEST_CHANGES|REJECT
RATIONALE=
REQUESTED_CHANGES=
```

## 24. Question 864 — nursing — MISSING_COGNITIVE_INTENT

**Question:** A nurse compares the number of new tuberculosis cases in two districts during one month. What measure is being considered?

**Choices:**
1. incidence
2. prevalence
3. case-fatality proportion
4. service utilization

**Current key:** incidence

**Current explanation:** Incidence counts new cases during a specified period; prevalence includes existing cases.

**Proposed changes:**
- `cognitive_intent`: `null` → `"understand"`

**Educational reasoning:** The stem asks the learner to explain or recognize the meaning of a stated principle in context. Stem: A nurse compares the number of new tuberculosis cases in two districts during one month. What measure is being considered?

**Validation currently recorded:** Label maps to existing rubric; human confirmation remains required.

**Potential ambiguity / required check:** Confirm the task demand and cognitive-intent rubric fit from the complete stem/options/key; do not infer from topic or answer form.

**Recommended expertise:** Registered nurse educator with current Philippine nursing/public-health practice expertise

**Source evidence:**
- No source identifier is registered on this decision; review using the supplied item and demonstrate the independent reasoning or add a verified authoritative citation before approval.

**Approval state:** `REVIEW_REQUIRED` / `APPLIED_PENDING_HUMAN_SIGNOFF` / `NOT_RECORDED`

**Reviewer decision (leave blank until authentic response):**

```text
REVIEWER_IDENTITY=
QUALIFICATIONS=
REVIEW_DATE=
DECISION=APPROVE|REQUEST_CHANGES|REJECT
RATIONALE=
REQUESTED_CHANGES=
```

## 25. Question 897 — nursing — MISSING_COGNITIVE_INTENT

**Question:** A parent asks why a healthy newborn receives vitamin K. What explanation is best?

**Choices:**
1. it helps prevent bleeding while the newborn’s clotting reserve is developing
2. it guarantees the newborn will not become ill
3. it replaces all feeding support
4. it is given only when bleeding is visible

**Current key:** it helps prevent bleeding while the newborn’s clotting reserve is developing

**Current explanation:** The explanation connects the prophylaxis to newborn physiology without overstating its effect.

**Proposed changes:**
- `cognitive_intent`: `null` → `"understand"`

**Educational reasoning:** The stem asks the learner to explain or recognize the meaning of a stated principle in context. Stem: A parent asks why a healthy newborn receives vitamin K. What explanation is best?

**Validation currently recorded:** Label maps to existing rubric; human confirmation remains required.

**Potential ambiguity / required check:** Confirm the task demand and cognitive-intent rubric fit from the complete stem/options/key; do not infer from topic or answer form.

**Recommended expertise:** Registered nurse educator with current Philippine nursing/public-health practice expertise

**Source evidence:**
- No source identifier is registered on this decision; review using the supplied item and demonstrate the independent reasoning or add a verified authoritative citation before approval.

**Approval state:** `REVIEW_REQUIRED` / `APPLIED_PENDING_HUMAN_SIGNOFF` / `NOT_RECORDED`

**Reviewer decision (leave blank until authentic response):**

```text
REVIEWER_IDENTITY=
QUALIFICATIONS=
REVIEW_DATE=
DECISION=APPROVE|REQUEST_CHANGES|REJECT
RATIONALE=
REQUESTED_CHANGES=
```

## 26. Question 895 — nursing — MISSING_COGNITIVE_INTENT

**Question:** A postpartum client saturates a pad rapidly and feels dizzy. Which finding makes this urgent?

**Choices:**
1. rapid bleeding with dizziness suggests possible hemodynamic compromise
2. mild afterpains while breastfeeding
3. fatigue after a sleepless night
4. a request for water

**Current key:** rapid bleeding with dizziness suggests possible hemodynamic compromise

**Current explanation:** The combination of rapid loss and dizziness requires immediate assessment and response.

**Proposed changes:**
- `cognitive_intent`: `null` → `"analyze"`

**Educational reasoning:** The stem requires comparing evidence, inferring a consequence, or prioritizing interacting case cues. Stem: A postpartum client saturates a pad rapidly and feels dizzy. Which finding makes this urgent?

**Validation currently recorded:** Label maps to existing rubric; human confirmation remains required.

**Potential ambiguity / required check:** Confirm the task demand and cognitive-intent rubric fit from the complete stem/options/key; do not infer from topic or answer form.

**Recommended expertise:** Registered nurse educator with current Philippine nursing/public-health practice expertise

**Source evidence:**
- No source identifier is registered on this decision; review using the supplied item and demonstrate the independent reasoning or add a verified authoritative citation before approval.

**Approval state:** `REVIEW_REQUIRED` / `APPLIED_PENDING_HUMAN_SIGNOFF` / `NOT_RECORDED`

**Reviewer decision (leave blank until authentic response):**

```text
REVIEWER_IDENTITY=
QUALIFICATIONS=
REVIEW_DATE=
DECISION=APPROVE|REQUEST_CHANGES|REJECT
RATIONALE=
REQUESTED_CHANGES=
```

## 27. Question 930 — nursing — MISSING_COGNITIVE_INTENT

**Question:** A postoperative client has shallow breathing after receiving analgesia. Which assessment is most important?

**Choices:**
1. respiratory rate, depth, effort, and level of consciousness
2. the client’s appetite
3. the color of the meal tray
4. the time of the next bath

**Current key:** respiratory rate, depth, effort, and level of consciousness

**Current explanation:** Respiratory assessment detects possible opioid-related compromise before it worsens.

**Proposed changes:**
- `cognitive_intent`: `null` → `"apply"`

**Educational reasoning:** The stem requires use of an explicit rule or procedure on supplied values or a stated case; this describes the demonstrated task, not topic or answer format. Stem: A postoperative client has shallow breathing after receiving analgesia. Which assessment is most important?

**Validation currently recorded:** Label maps to existing rubric; human confirmation remains required.

**Potential ambiguity / required check:** Confirm the task demand and cognitive-intent rubric fit from the complete stem/options/key; do not infer from topic or answer form.

**Recommended expertise:** Registered nurse educator with current Philippine nursing/public-health practice expertise

**Source evidence:**
- No source identifier is registered on this decision; review using the supplied item and demonstrate the independent reasoning or add a verified authoritative citation before approval.

**Approval state:** `REVIEW_REQUIRED` / `APPLIED_PENDING_HUMAN_SIGNOFF` / `NOT_RECORDED`

**Reviewer decision (leave blank until authentic response):**

```text
REVIEWER_IDENTITY=
QUALIFICATIONS=
REVIEW_DATE=
DECISION=APPROVE|REQUEST_CHANGES|REJECT
RATIONALE=
REQUESTED_CHANGES=
```

## 28. Question 622 — civil-service — RATIONALE_TOO_EMPTY_TO_SUPPORT_KEY

**Question:** How much of the budget is allocated to supplies?

**Choices:**
1. 420
2. 320
3. 400
4. 450

**Current key:** 420

**Current explanation:** 35% is 0.35, so 0.35 × 1,200 = 420. Check: 30% is 360 and 5% is 60; 360 + 60 = 420.

**Proposed changes:**
- `explanation`: `"0.35 × 1,200 = 420."` → `"35% is 0.35, so 0.35 × 1,200 = 420. Check: 30% is 360 and 5% is 60; 360 + 60 = 420."`
- `calculation`: `null` → `{"operation": "multiply", "values": [0.35, 1200], "expected_value": 420}`

**Educational reasoning:** The supplied base and percentage recompute to 420; OpenStax Prealgebra 2e §6.2 explains the percent-of-base relationship.

**Validation currently recorded:** Added explicit formula, substitution, units and answer check; structured numerical audit must recompute. Human academic sign-off required.

**Potential ambiguity / required check:** Independently verify the answer, formula/units, choices, and whether the added steps teach rather than overstate the method.

**Recommended expertise:** Assessment editor experienced in public-service numerical/English examination items

**Source evidence:**
- `openstax-prealgebra-percent-applications-6-2` — Prealgebra 2e, Section 6.2: Solve General Applications of Percent; OpenStax, Rice University; locator: Section 6.2, Solve General Applications of Percent; worked percent applications and checking against the stated base.; [source](https://openstax.org/books/prealgebra-2e/pages/6-2-solve-general-applications-of-percent) (status: `verified_reference`).

**Approval state:** `REVIEW_REQUIRED` / `APPLIED_PENDING_HUMAN_SIGNOFF` / `NOT_RECORDED`

**Reviewer decision (leave blank until authentic response):**

```text
REVIEWER_IDENTITY=
QUALIFICATIONS=
REVIEW_DATE=
DECISION=APPROVE|REQUEST_CHANGES|REJECT
RATIONALE=
REQUESTED_CHANGES=
```

## 29. Question 6334 — civil-service — RATIONALE_TOO_EMPTY_TO_SUPPORT_KEY

**Question:** A vehicle covers 210 km in 3.5 hours. What is its average speed?

**Choices:**
1. 70 km/h
2. 56 km/h
3. 73.5 km/h
4. 60 km/h

**Current key:** 60 km/h

**Current explanation:** Average speed = distance ÷ time = 210 km ÷ 3.5 h = 60 km/h. Check: 60 km/h × 3.5 h = 210 km.

**Proposed changes:**
- `explanation`: `"210 ÷ 3.5 = 60 km/h."` → `"Average speed = distance ÷ time = 210 km ÷ 3.5 h = 60 km/h. Check: 60 km/h × 3.5 h = 210 km."`
- `calculation`: `{"operation": "distance_time", "inputs": [210, 3.5], "expected": 60}` → `{"operation": "distance_time", "inputs": [210, 3.5], "expected": 60, "unit": "km/h", "rounding": "exact"}`

**Educational reasoning:** The stated distance/time gives 60; multiplying back returns 210 km. Units reduce to km/h.

**Validation currently recorded:** Added explicit formula, substitution, units and answer check; structured numerical audit must recompute. Human academic sign-off required.

**Potential ambiguity / required check:** Independently verify the answer, formula/units, choices, and whether the added steps teach rather than overstate the method.

**Recommended expertise:** Assessment editor experienced in public-service numerical/English examination items

**Source evidence:**
- `openstax-prealgebra-2e` — Prealgebra 2e; OpenStax; locator: No question-level locator recorded; [source](https://openstax.org/details/books/prealgebra-2e) (status: `verified_reference`).

**Approval state:** `REVIEW_REQUIRED` / `APPLIED_PENDING_HUMAN_SIGNOFF` / `NOT_RECORDED`

**Reviewer decision (leave blank until authentic response):**

```text
REVIEWER_IDENTITY=
QUALIFICATIONS=
REVIEW_DATE=
DECISION=APPROVE|REQUEST_CHANGES|REJECT
RATIONALE=
REQUESTED_CHANGES=
```

## 30. Question 6353 — civil-service — RATIONALE_TOO_EMPTY_TO_SUPPORT_KEY

**Question:** An intake rate of 8 cases per hour continues for 8.5 hours. How many cases are processed at that pace?

**Choices:**
1. 60
2. 68
3. 56
4. 72

**Current key:** 68

**Current explanation:** Processed cases = rate × time = 8 cases/h × 8.5 h = 68 cases. The hours cancel, leaving a count.

**Proposed changes:**
- `explanation`: `"8 × 8.5 = 68 cases."` → `"Processed cases = rate × time = 8 cases/h × 8.5 h = 68 cases. The hours cancel, leaving a count."`
- `calculation`: `{"operation": "rate_times_time", "inputs": [8, 8.5], "expected": 68}` → `{"operation": "rate_times_time", "inputs": [8, 8.5], "expected": 68, "rounding": "exact"}`

**Educational reasoning:** The stated rate multiplied by duration gives 68, and hours cancel. Direct arithmetic and dimensional check.

**Validation currently recorded:** Added explicit formula, substitution, units and answer check; structured numerical audit must recompute. Human academic sign-off required.

**Potential ambiguity / required check:** Independently verify the answer, formula/units, choices, and whether the added steps teach rather than overstate the method.

**Recommended expertise:** Assessment editor experienced in public-service numerical/English examination items

**Source evidence:**
- `openstax-prealgebra-2e` — Prealgebra 2e; OpenStax; locator: No question-level locator recorded; [source](https://openstax.org/details/books/prealgebra-2e) (status: `verified_reference`).

**Approval state:** `REVIEW_REQUIRED` / `APPLIED_PENDING_HUMAN_SIGNOFF` / `NOT_RECORDED`

**Reviewer decision (leave blank until authentic response):**

```text
REVIEWER_IDENTITY=
QUALIFICATIONS=
REVIEW_DATE=
DECISION=APPROVE|REQUEST_CHANGES|REJECT
RATIONALE=
REQUESTED_CHANGES=
```

## 31. Question 5667 — let — RATIONALE_TOO_EMPTY_TO_SUPPORT_KEY

**Question:** A cyclist travels 2.4 km in 8 minutes. What is the average speed in km/min?

**Choices:**
1. 0.3 km/min
2. 0.8 km/min
3. 1.6 km/min
4. 19.2 km/min

**Current key:** 0.3 km/min

**Current explanation:** Average speed = distance ÷ time = 2.4 km ÷ 8 min = 0.30 km/min. Converting 8 min to 8/60 h gives the equivalent 18 km/h.

**Proposed changes:**
- `explanation`: `"2.4 ÷ 8 = 0.3 km/min."` → `"Average speed = distance ÷ time = 2.4 km ÷ 8 min = 0.30 km/min. Converting 8 min to 8/60 h gives the equivalent 18 km/h."`
- `calculation`: `{"expression": "2.4 / 8", "result": 0.3, "unit": "km/min"}` → `{"expression": "2.4 / 8", "result": 0.3, "unit": "km/min"}`

**Educational reasoning:** The distance/time calculation and unit conversion independently agree.

**Validation currently recorded:** Added explicit formula, substitution, units and answer check; structured numerical audit must recompute. Human academic sign-off required.

**Potential ambiguity / required check:** Independently verify the answer, formula/units, choices, and whether the added steps teach rather than overstate the method.

**Recommended expertise:** Teacher educator or language/education assessment specialist

**Source evidence:**
- `openstax-university-physics-volume-1` — University Physics Volume 1; OpenStax, Rice University; locator: No question-level locator recorded; [source](https://openstax.org/details/books/university-physics-volume-1) (status: `verified_reference`).

**Approval state:** `REVIEW_REQUIRED` / `APPLIED_PENDING_HUMAN_SIGNOFF` / `NOT_RECORDED`

**Reviewer decision (leave blank until authentic response):**

```text
REVIEWER_IDENTITY=
QUALIFICATIONS=
REVIEW_DATE=
DECISION=APPROVE|REQUEST_CHANGES|REJECT
RATIONALE=
REQUESTED_CHANGES=
```

## 32. Question 1387 — mechanical-engineering — RATIONALE_TOO_EMPTY_TO_SUPPORT_KEY

**Question:** For f(x)=x²+3x, what is the instantaneous rate of change at x=2?

**Choices:**
1. 7
2. 5
3. 10
4. 13

**Current key:** 7

**Current explanation:** For f(x)=x²+3x, f′(x)=2x+3. At x=2, 2(2)+3=7, so the instantaneous rate is 7.

**Proposed changes:**
- `explanation`: `"f′(x)=2x+3, so f′(2)=7."` → `"For f(x)=x²+3x, f′(x)=2x+3. At x=2, 2(2)+3=7, so the instantaneous rate is 7."`
- `calculation`: `null` → `{"formula": "quadratic_derivative_at_x", "formula_family": "quadratic-derivative", "inputs": {"a": 1, "b": 3, "x": 2}, "expected_value": 7, "rounding": "exact"}`

**Educational reasoning:** Differentiate the stated polynomial term-by-term and substitute x=2; the derivative evaluates to 7. Stem provides all required data.

**Validation currently recorded:** Added explicit formula, substitution, units and answer check; structured numerical audit must recompute. Human academic sign-off required.

**Potential ambiguity / required check:** Independently verify the answer, formula/units, choices, and whether the added steps teach rather than overstate the method.

**Recommended expertise:** Licensed mechanical engineer and discipline educator; verify formula and units

**Source evidence:**
- No source identifier is registered on this decision; review using the supplied item and demonstrate the independent reasoning or add a verified authoritative citation before approval.

**Approval state:** `REVIEW_REQUIRED` / `APPLIED_PENDING_HUMAN_SIGNOFF` / `NOT_RECORDED`

**Reviewer decision (leave blank until authentic response):**

```text
REVIEWER_IDENTITY=
QUALIFICATIONS=
REVIEW_DATE=
DECISION=APPROVE|REQUEST_CHANGES|REJECT
RATIONALE=
REQUESTED_CHANGES=
```

## 33. Question 1397 — mechanical-engineering — RATIONALE_TOO_EMPTY_TO_SUPPORT_KEY

**Question:** A flywheel accelerates uniformly from 2 to 10 rad/s in 4 s. Its angular acceleration is:

**Choices:**
1. 2 rad/s²
2. 3 rad/s²
3. 8 rad/s²
4. 12 rad/s²

**Current key:** 2 rad/s²

**Current explanation:** Angular acceleration = (ωf−ωi)/t = (10−2) rad/s ÷ 4 s = 2 rad/s². The unit check is (rad/s)/s.

**Proposed changes:**
- `explanation`: `"α=(10−2)/4=2 rad/s²."` → `"Angular acceleration = (ωf−ωi)/t = (10−2) rad/s ÷ 4 s = 2 rad/s². The unit check is (rad/s)/s."`
- `calculation`: `null` → `{"formula": "angular_acceleration", "formula_family": "uniform-angular-acceleration", "inputs": {"initial_angular_velocity": 2, "final_angular_velocity": 10, "time": 4}, "expected_value": 2, "unit": "rad/s2", "rounding": "exact"}`

**Educational reasoning:** Given uniform change, (10−2)/4=2; dividing rad/s by seconds yields rad/s².

**Validation currently recorded:** Added explicit formula, substitution, units and answer check; structured numerical audit must recompute. Human academic sign-off required.

**Potential ambiguity / required check:** Independently verify the answer, formula/units, choices, and whether the added steps teach rather than overstate the method.

**Recommended expertise:** Licensed mechanical engineer and discipline educator; verify formula and units

**Source evidence:**
- No source identifier is registered on this decision; review using the supplied item and demonstrate the independent reasoning or add a verified authoritative citation before approval.

**Approval state:** `REVIEW_REQUIRED` / `APPLIED_PENDING_HUMAN_SIGNOFF` / `NOT_RECORDED`

**Reviewer decision (leave blank until authentic response):**

```text
REVIEWER_IDENTITY=
QUALIFICATIONS=
REVIEW_DATE=
DECISION=APPROVE|REQUEST_CHANGES|REJECT
RATIONALE=
REQUESTED_CHANGES=
```

## 34. Question 3533 — mechanical-engineering — RATIONALE_TOO_EMPTY_TO_SUPPORT_KEY

**Question:** An FMEA entry has severity 8, occurrence 3, and detection 4. What risk-priority number follows from RPN=SOD?

**Choices:**
1. 96
2. 15
3. 32
4. 128

**Current key:** 96

**Current explanation:** Using the relationship in the stem, RPN = severity × occurrence × detection = 8 × 3 × 4 = 96. RPN is a prioritization index, not a probability.

**Proposed changes:**
- `explanation`: `"RPN=8(3)(4)=96."` → `"Using the relationship in the stem, RPN = severity × occurrence × detection = 8 × 3 × 4 = 96. RPN is a prioritization index, not a probability."`
- `calculation`: `null` → `{"formula": "multiply", "values": [8, 3, 4], "expected_value": 96, "rounding": "exact"}`

**Educational reasoning:** The stem supplies RPN=SOD; multiplication gives 96. This explanation does not claim an external standard was reviewed.

**Validation currently recorded:** Added explicit formula, substitution, units and answer check; structured numerical audit must recompute. Human academic sign-off required.

**Potential ambiguity / required check:** Independently verify the answer, formula/units, choices, and whether the added steps teach rather than overstate the method.

**Recommended expertise:** Licensed mechanical engineer and discipline educator; verify formula and units

**Source evidence:**
- No source identifier is registered on this decision; review using the supplied item and demonstrate the independent reasoning or add a verified authoritative citation before approval.

**Approval state:** `REVIEW_REQUIRED` / `APPLIED_PENDING_HUMAN_SIGNOFF` / `NOT_RECORDED`

**Reviewer decision (leave blank until authentic response):**

```text
REVIEWER_IDENTITY=
QUALIFICATIONS=
REVIEW_DATE=
DECISION=APPROVE|REQUEST_CHANGES|REJECT
RATIONALE=
REQUESTED_CHANGES=
```

## 35. Question 4622 — psychometrician — RATIONALE_TOO_EMPTY_TO_SUPPORT_KEY

**Question:** An item is answered correctly by 48 of 60 examinees. What percentage answered correctly?

**Choices:**
1. 12%
2. 20%
3. 88%
4. 80%

**Current key:** 80%

**Current explanation:** Percent correct = correct responses ÷ all examinees × 100 = 48 ÷ 60 × 100 = 80%. The denominator is all 60 examinees.

**Proposed changes:**
- `explanation`: `"48 ÷ 60 × 100 = 80%."` → `"Percent correct = correct responses ÷ all examinees × 100 = 48 ÷ 60 × 100 = 80%. The denominator is all 60 examinees."`
- `calculation`: `{"family": "percentage", "params": {"part": 48, "whole": 60}, "expected": 80, "unit": "%", "rounding": "Answer choice uses exact or stated nearest practical value; tolerance 0.011."}` → `{"family": "percentage", "params": {"part": 48, "whole": 60}, "expected": 80, "unit": "%", "rounding": "Answer choice uses exact or stated nearest practical value; tolerance 0.011."}`

**Educational reasoning:** 48/60×100 recomputes to 80%; independent arithmetic matches the key. OpenStax provides general statistics background.

**Validation currently recorded:** Added explicit formula, substitution, units and answer check; structured numerical audit must recompute. Human academic sign-off required.

**Potential ambiguity / required check:** Independently verify the answer, formula/units, choices, and whether the added steps teach rather than overstate the method.

**Recommended expertise:** Licensed psychometrician or measurement specialist; verify psychometric terminology and formulas

**Source evidence:**
- `openstax-introductory-statistics-2e` — Introductory Statistics 2e; OpenStax; Illowsky and Dean; locator: OpenStax Introductory Statistics 2e, §1.3 “Frequency, Frequency Tables, and Levels of Measurement,” relative frequency: frequency divided by total outcomes; fractions, decimals, or percents.; [source](https://openstax.org/books/introductory-statistics-2e/pages/index) (status: `verified_reference`).

**Approval state:** `REVIEW_REQUIRED` / `APPLIED_PENDING_HUMAN_SIGNOFF` / `NOT_RECORDED`

**Reviewer decision (leave blank until authentic response):**

```text
REVIEWER_IDENTITY=
QUALIFICATIONS=
REVIEW_DATE=
DECISION=APPROVE|REQUEST_CHANGES|REJECT
RATIONALE=
REQUESTED_CHANGES=
```

## 36. Question 1589 — registered-electrical-engineering — RATIONALE_TOO_EMPTY_TO_SUPPORT_KEY

**Question:** What angular frequency corresponds to a 60 Hz waveform?

**Choices:**
1. 60 rad/s
2. 188.5 rad/s
3. 377.0 rad/s
4. 1200 rad/s

**Current key:** 377.0 rad/s

**Current explanation:** Angular frequency is ω=2πf. At 60 Hz, ω=2π(60)=376.991… rad/s, which rounds to 377.0 rad/s.

**Proposed changes:**
- `explanation`: `"ω=2πf=2π(60)≈377 rad/s."` → `"Angular frequency is ω=2πf. At 60 Hz, ω=2π(60)=376.991… rad/s, which rounds to 377.0 rad/s."`
- `calculation`: `null` → `{"formula": "angular_frequency_hz", "formula_family": "angular-frequency", "inputs": {"frequency_hz": 60}, "expected_value": 376.99111843077515, "unit": "rad/s", "rounding": "1 decimal"}`

**Educational reasoning:** The stated frequency maps to ω=2πf; result rounds to the keyed one-decimal value.

**Validation currently recorded:** Added explicit formula, substitution, units and answer check; structured numerical audit must recompute. Human academic sign-off required.

**Potential ambiguity / required check:** Independently verify the answer, formula/units, choices, and whether the added steps teach rather than overstate the method.

**Recommended expertise:** Licensed electrical engineer; verify engineering formula, units, and standard applicability

**Source evidence:**
- No source identifier is registered on this decision; review using the supplied item and demonstrate the independent reasoning or add a verified authoritative citation before approval.

**Approval state:** `REVIEW_REQUIRED` / `APPLIED_PENDING_HUMAN_SIGNOFF` / `NOT_RECORDED`

**Reviewer decision (leave blank until authentic response):**

```text
REVIEWER_IDENTITY=
QUALIFICATIONS=
REVIEW_DATE=
DECISION=APPROVE|REQUEST_CHANGES|REJECT
RATIONALE=
REQUESTED_CHANGES=
```

## 37. Question 1607 — registered-electrical-engineering — RATIONALE_TOO_EMPTY_TO_SUPPORT_KEY

**Question:** A 20 kN tensile force acts on a 200 mm² support. What is the average stress?

**Choices:**
1. 10 MPa
2. 50 MPa
3. 400 MPa
4. 100 MPa

**Current key:** 100 MPa

**Current explanation:** Average tensile stress = force ÷ area = 20,000 N ÷ 200 mm² = 100 N/mm² = 100 MPa.

**Proposed changes:**
- `explanation`: `"Stress=F/A=100 MPa."` → `"Average tensile stress = force ÷ area = 20,000 N ÷ 200 mm² = 100 N/mm² = 100 MPa."`
- `calculation`: `null` → `{"formula": "force_over_area", "formula_family": "axial-stress", "inputs": {"force": 20000, "area": 200}, "expected_value": 100, "unit": "MPa", "rounding": "exact"}`

**Educational reasoning:** 20 kN is 20,000 N; division gives 100 N/mm², numerically equal to 100 MPa. Units and key agree.

**Validation currently recorded:** Added explicit formula, substitution, units and answer check; structured numerical audit must recompute. Human academic sign-off required.

**Potential ambiguity / required check:** Independently verify the answer, formula/units, choices, and whether the added steps teach rather than overstate the method.

**Recommended expertise:** Licensed electrical engineer; verify engineering formula, units, and standard applicability

**Source evidence:**
- No source identifier is registered on this decision; review using the supplied item and demonstrate the independent reasoning or add a verified authoritative citation before approval.

**Approval state:** `REVIEW_REQUIRED` / `APPLIED_PENDING_HUMAN_SIGNOFF` / `NOT_RECORDED`

**Reviewer decision (leave blank until authentic response):**

```text
REVIEWER_IDENTITY=
QUALIFICATIONS=
REVIEW_DATE=
DECISION=APPROVE|REQUEST_CHANGES|REJECT
RATIONALE=
REQUESTED_CHANGES=
```
