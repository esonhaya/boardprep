# Batch 706 External Browser Acceptance Checklist

**Execution status:** `BLOCKED` in Android/Termux. A single capability probe found no Chromium, Chrome, Firefox, Playwright, or Selenium executable. This is a host-side manual acceptance procedure, not browser automation or an acceptance result.

## Record before execution

```text
TESTED_COMMIT_SHA=
HOST_OS_AND_VERSION=
BROWSER_AND_VERSION=
VIEWPORT=
FIXTURE_STORAGE_ID=
EXECUTION_DATE=
EXECUTOR=
RESULT=
EVIDENCE_PATHS=
```

Use a disposable copy of the tested commit and isolated temporary learner storage. Do not use production learner accounts, attempts, or state. Preserve the normal Nursing exam pool; the two dose-basis questions below remain eligible for ordinary exam practice even though their exact study-section relationship is quarantined.

## Scenarios

1. **Study entry:** Open the Nursing board, a Nursing subject, and a published study material. Verify the displayed material belongs to Nursing and its source references are separate from exact lesson support.
2. **Q1018 retained exact support:** Open the therapeutic communication/crisis lesson and its self-harm statement section. Follow exact practice. Confirm Q1018 is present, its full stem and options render, and the section anchor is stable. Submit the keyed response and a non-keyed response in separate attempts. Confirm each answer review returns to `b703-self-harm-statement-and-safety`, while the content does not imply a complete risk protocol or local referral pathway.
3. **Dose-basis quarantine:** Open the medication calculation lesson and `b705-medication-weight-based-dose`. Confirm Q1536 and Q1537 are absent from this exact section pool. Open their source-only references through the appropriate question/reference view; confirm no exact lesson-section anchor is asserted. Confirm both questions remain available in the normal Nursing exam eligibility path.
4. **Review actions:** Submit correct and incorrect answers for an unrelated exact-supported item. Verify the stored key and rationale are displayed as authored, and the exact return link leads to the supporting section.
5. **Repeat practice:** Repeat an exact practice session and verify stable question identity, navigation, and learner state isolation.
6. **Empty practice:** Open a valid section with no exact questions. Verify the page reports an empty exact pool truthfully and offers a way back to study without selecting a broad substitute question.
7. **Stale anchor:** Request a nonexistent section anchor. Verify safe recovery to the material or lesson and no fabricated practice item.
8. **Cross-board rejection:** Attempt to open the Nursing-only exact practice reference under a different board context. Verify rejection or an empty scoped result; no other board's question is rendered.
9. **Anonymous isolation:** In a second anonymous browser session, confirm the first session's attempts and identity are not exposed. Do not inspect or modify production learner data.
10. **Mobile layout:** Repeat the study entry, Q1018 review, source-only dose reference, empty-pool, and stale-anchor checks at a 360-pixel-wide viewport. Verify controls and answer-review links remain usable without horizontal clipping.

## Acceptance evidence

Attach a result for each scenario, screenshots or a recording with identifiers redacted as appropriate, browser console and network failures, and the tested commit SHA. A browser pass does not grant clinical approval, mapping approval, or deployment authorization. Leave `HUMAN_ACCEPTANCE=NOT_REVIEWED` until an authorized person records their decision.
