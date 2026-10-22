# Batch 706 Nursing Independent Review Packet

**Review state:** `NOT_REVIEWED` for every item. No reviewer identity, credential, date, signature, decision, or approval evidence is supplied by this packet.

**Baseline:** Batch 705 final `2a679c8ca04d59bdae1d7828eb5368e099f95c79`<br>
**Branch:** `batch-706-nursing-safety`<br>
**Primary backlog:** [Batch 705’s 106-record queue](batch-705-academic-review-queue.md) and [source claim ledger](batch-705-source-claim-ledger.json).<br>
**Batch 706 records:** [critical mapping decisions](batch-706-critical-mapping-decisions.json), [defect review](batch-706-defect-review.json), and [source-to-claim ledger](batch-706-source-claim-ledger.json).

## How to complete a review

1. Open the canonical question or lesson record named by `ITEM_ID`; check the current production status and the linked source text.
2. Review only within your professional remit. Separate a mathematical calculation from medication-order validity; separate warning-sign recognition from diagnosis or a facility response protocol.
3. Choose one human decision: `ACCEPT`, `ACCEPT_WITH_CORRECTION`, `CHANGES_REQUIRED`, `REJECT`, or `INSUFFICIENT_EVIDENCE`.
4. Record the reviewer’s name, role/credential, decision date, specific feedback, correction status, approval evidence, and follow-up in the controlled review record. Leave the production decision at `NOT_REVIEWED` until the authorized evidence is recorded.
5. Do not approve a source merely because its title or metadata is present. Identify the precise passage and any jurisdiction/currentness limit.

No approval UI or alternate workflow is introduced. The existing admin/security boundary is not expanded.

## Prioritized first pass

These primary assignments partition the 74 existing P0 queue records into unique review items. The identifiers point to the full stems, options, rationales, source notes, exact-link state, and existing blank decision fields in the Batch 705 queue. Cross-specialty referrals below reuse those same item IDs and are not additional findings.

### A. Medication and calculations — 14 P0 records

`Q1517`, `Q1536`, `Q1537`, `Q4094`, `Q4096`, `Q4097`, `Q4102`, `Q5104`, `Q5187`, `Q1529`, `SRC-05-openrn-nursingskills-weight-based-calculations-511`, `SRC-06-openrn-nursingdosagecalculations-weight-based-6-1`, `SRC-10-openrn-pharmacology2e-medication-safety`, `SRC-11-who-medication-without-harm-5-moments`.

**Review focus:** verify units, route, product concentration and the stated time basis; distinguish a correct arithmetic result from an authorized, clinically valid prescription. Q1536 and Q1537 have been removed from exact section practice pending review, but remain Nursing-eligible canonical items with source-only references. Decide whether their stems can be clarified before any exact mapping is restored.

**Reviewers:** nurse educator and pharmacist; add a Philippine medication-practice reviewer for jurisdictional claims.

### B. Maternal and newborn safety — 17 P0 records

`Q1523`, `Q1540`, `Q3855`, `Q3924`, `Q3945`, `Q3946`, `Q4933`, `Q4957`, `Q896`, `Q3856`, `Q3857`, `Q3934`, `Q3944`, `Q3951`, `Q3957`, `Q5177`, `SRC-12-who-preeclampsia-factsheet-2026`.

**Review focus:** distinguish recognition and escalation cues from diagnosis, thresholds, and institution-specific treatment. Q896 needs current, full-text neonatal evidence before any exact mapping; do not infer resuscitation steps from a general newborn-care source. Q3951 requires maternal and psychiatric co-review.

**Reviewers:** maternal/newborn nurse educator; neonatal specialist for transition/resuscitation claims; psychiatric nurse educator for Q3951.

### C. Psychiatric and self-harm safety — 10 P0 records

`Q1018`, `Q1004`, `Q1008`, `Q1035`, `Q1547`, `Q4104`, `Q4114`, `Q5113`, `Q4106`, `SRC-08-nimh-suicide-faq-2023`.

**Review focus:** distinguish direct inquiry and broad warning-sign education from a full risk assessment, an observation level, a legal hold, or a local referral sequence. Q1018’s exact section teaches the keyed direct-response principle, but its NP V teamwork concept remains flagged and the link is not academically approved. Q1008’s wording does not state whether suicidal intent or an immediate safety plan has been established.

**Reviewers:** psychiatric/mental-health nurse educator; PRC taxonomy owner for Q1018, Q1004, and Q1035.

### D. Neurologic change, deterioration, triage, and emergency recognition — 16 P0 records

`Q1011`, `Q1012`, `Q3914`, `Q4084`, `Q4091`, `Q4124`, `Q4134`, `Q1022`, `Q1023`, `Q4125`, `Q4130`, `Q4131`, `Q4139`, `Q4057`, `SRC-03-who-stroke-fact-sheet-2025`, `SRC-04-resuscitation-council-uk-abcde-2024`.

**Review focus:** verify the assessed cue and context; do not turn recognition language or the ABCDE framework into a local treatment threshold, resuscitation protocol, or universal client ranking. Q4125 is a resuscitation/BLS claim and is not supported by ABCDE alone.

**Reviewers:** nurse educator; add acute neurology/triage or resuscitation expertise for the relevant item.

### E. Infection, exposure, and immunosuppression — 12 P0 records

`Q3853`, `Q937`, `Q938`, `Q949`, `Q955`, `Q1507`, `Q4004`, `Q4005`, `Q4006`, `Q4014`, `Q5025`, `SRC-07-nci-infection-neutropenia-cancer-treatment`.

**Review focus:** use organism/exposure-specific, current precaution evidence. Do not generalize cancer-treatment guidance to every immune-modifying therapy. Q4014 and Q5025 remain source-only because the cited NCI material is cancer-specific and has a 2020 page-review date; the requested febrile-risk pathway is not established here. The prior Q4004–Q4006 mapping rejections remain in force.

**Reviewers:** infection-prevention nurse educator; pharmacist for multidose-vial/product claims; oncology/infection specialist where therapy-specific.

### F. Delegation, coordination, and professional scope — 4 P0 records

`Q1027`, `Q1543`, `Q4148`, `Q4155`.

**Review focus:** separate accountability from task assignment; verify patient stability, competence, supervision, and the applicable Philippine authority. Generic nursing principles and PRC examination scope do not grant local task permissions.

**Reviewers:** nurse educator and Philippine nursing regulator/legal reviewer.

### G. Philippine legal/currentness co-review — one P0 integrity item plus cross-specialty referrals

`Q4239` is a CPA item resolved as not Nursing-eligible. Keep it in the portfolio eligibility audit; it is not a Nursing content-review request. For Philippine scope/currentness review, co-review the existing F-group items `Q1027`, `Q1543`, `Q4148`, `Q4155`; also consult the P1 records `Q3974` and `SRC-01-prc-nursing-annex-a-resolution-10-2025` in the original queue. These repeated references are not counted as new or additional question defects.

**Review focus:** use current Philippine primary authority for legal/regulatory claims. The PRC Annex A item documents examination-scope placement only; it is not detailed clinical-rule evidence. Local facility protocols remain unverified.

**Reviewers:** Philippine nursing regulator/legal reviewer and the applicable nurse educator.

## Critical mapping decision sheet

| Item | Current technical disposition | Reviewer task | Initial decision |
|---|---|---|---|
| Q1018 | Exact section retained; taxonomy/safety review still open | Confirm NP V concept and whether the bounded section is suitable for this question | `NOT_REVIEWED` |
| Q1536 | Exact section practice quarantined; two source IDs remain | Decide intended per-dose/per-day basis and whether wording can be made unambiguous | `NOT_REVIEWED` |
| Q1537 | Exact section practice quarantined; two source IDs remain | Decide intended pediatric dose basis; arithmetic is not dose authorization | `NOT_REVIEWED` |
| Q967 | No exact link; canonical concept unchanged | Choose intended GI/fluid concept and NP level from authoritative taxonomy | `NOT_REVIEWED` |
| Q1004 / Q1035 | No exact link; canonical concepts unchanged | Resolve the NP V concept assignment | `NOT_REVIEWED` |
| Q1008 | No exact link; stem held | Specify the clinical context needed for one defensible best answer | `NOT_REVIEWED` |
| Q896 | No exact link | Supply current full-text neonatal evidence and specialist decision | `NOT_REVIEWED` |
| Q4014 / Q5025 | Source-only; no exact section | Provide therapy-specific current evidence and decide whether the stem needs narrowing | `NOT_REVIEWED` |
| Q4090 | Source-only; no exact section | Resolve age/drug context and avoid unsupported medication causality | `NOT_REVIEWED` |

## Blank decision record

Copy one record per existing queue `ITEM_ID`; keep the item ID stable.

```text
ITEM_ID=
REVIEWER_NAME=
REVIEWER_ROLE_OR_CREDENTIAL=
REVIEW_DATE=
REVIEW_DECISION=NOT_REVIEWED
REVIEWER_FEEDBACK=
CORRECTION_STATUS=
APPROVAL_EVIDENCE=
FOLLOWUP_ACTION=
```

Allowed reviewer decisions are `ACCEPT`, `ACCEPT_WITH_CORRECTION`, `CHANGES_REQUIRED`, `REJECT`, and `INSUFFICIENT_EVIDENCE`. The initial decision remains `NOT_REVIEWED`. No independent nurse-educator, pharmacist, taxonomy-owner, browser, or public-launch acceptance is recorded.
