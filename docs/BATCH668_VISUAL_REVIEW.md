# Batch 668 visual review record

## Evidence

- GitHub Actions run: [38200931826](https://github.com/esonhaya/boardprep/actions/runs/38200931826)
- Artifact ID: `11725317583`, name `boardprep-batch667-visual-qa`.
- Downloaded archive SHA-256:
  `c380ae292dee81f1b0b44f54ef1bcb1010f30944c45c399da97ad23971566159`.
- The archive passed `unzip -t`; 86 curated PNG screenshots were present:
  36 desktop, 47 mobile, and 3 tablet. PNG width metadata matches each target
  viewport; heights vary because the artifact includes both viewport captures
  and full-page captures (desktop 768–23282px, mobile 640–36376px, tablet
  1024–2534px). Browser geometry reports record no unexpected horizontal page
  overflow (2px tolerance; tables may scroll locally). Chromium was
  153.0.8010.12; the run recorded 20 pass, 0 fail, and 2 skip.
- All 86 curated screenshots below were opened and inspected at pixel level in
  this review. The original report says nine had been inspected before this
  pass, but the artifact does not identify that exact nine-file roster. The
  earlier defect register names eight screenshots in its final-review
  paragraph and refers to prior runs for other states. No screenshot remains
  uninspected after this pass.
- Browser captures do not establish physical Android touch behavior, owner
  acceptance, screen-reader support, or WCAG conformance.
- The filename stem identifies the page/state (`quiz-table`, `result-review`,
  `privacy-delete-confirmation`, etc.); the viewport suffix identifies the
  target width/height. `board-*` files are board detail pages; LET/CSE
  configuration files retain the named candidate/exam-level context. Empty,
  error, selected-answer, feedback, ready-to-submit, revealed-card, and
  deletion-confirmation captures represent distinct interaction states. These
  are deterministic browser-test fixtures, not a live learner's data.

## Findings

| ID | Screen / viewport | Screenshot | Severity | Observation and impact | Repair / verification |
|---|---|---|---|---|---|
| B668-VIS-001 | Study material detail, desktop 1366×768 and mobile 390×844 | `screenshots/desktop/study-material-detail-1366x768.png`; `screenshots/mobile/study-material-detail-390x844.png` | P2 | Source titles and links run together with no visual/list separation, making references difficult to scan or activate accurately. The PHP template emits adjacent anchors in one paragraph. | Replaced with a labeled source section and one list item per reference; added focused production HTTP assertions. New rendered browser evidence is pending the integration-branch workflow. |
| B668-VIS-002 | Dashboard/progress, mobile 320/360/390/430, tablet 768, desktop 1366/1440 | `screenshots/mobile/dashboard-width-320-320x640.png`; `screenshots/mobile/dashboard-width-360-360x800.png`; `screenshots/mobile/dashboard-width-390-390x844.png`; `screenshots/mobile/dashboard-width-430-430x932.png`; `screenshots/tablet/dashboard-width-768-768x1024.png`; `screenshots/desktop/dashboard-wide-1440x900.png`; `screenshots/desktop/progress-1366x768.png` | P2 | Top-N ranking labels a sole 100% topic as “weakest” and can include a 20% subject as “strongest” when fewer than three groups exist. Cross-board/variant aggregates also invite invalid comparisons. | Comparisons now use a weighted average within the same board and exam variant and show only groups above/below that baseline. Full history and actionable recommendations remain. Added service regression coverage; new browser screenshots are pending the integration-branch workflow. |

No P0/P1 or P3 defects were identified in the visual set. All 86 screenshots
showed usable general hierarchy and page width. In the 768px dashboard the
four summary tiles are compact but remain readable; the report is long and
requires normal vertical scrolling. Quiz/table content stays in its expected
local layout and the browser geometry report recorded no page-level overflow.
Privacy deletion is visibly separate and requires an explicit checkbox.

## Inspected screenshots

Every filename listed here was visually inspected. Paths are relative to the
Batch667 browser artifact root.

### Desktop (36)

- `screenshots/desktop/answer-feedback-1366x768.png`
- `screenshots/desktop/answer-selected-1366x768.png`
- `screenshots/desktop/board-cpa-1366x768.png`
- `screenshots/desktop/board-cse-1366x768.png`
- `screenshots/desktop/board-engineering-1366x768.png`
- `screenshots/desktop/board-let-1366x768.png`
- `screenshots/desktop/board-nursing-1366x768.png`
- `screenshots/desktop/dashboard-1366x768.png`
- `screenshots/desktop/dashboard-wide-1440x900.png`
- `screenshots/desktop/empty-state-1366x768.png`
- `screenshots/desktop/error-state-1366x768.png`
- `screenshots/desktop/exams-1366x768.png`
- `screenshots/desktop/finish-quiz-ready-1366x768.png`
- `screenshots/desktop/flashcard-landing-1366x768.png`
- `screenshots/desktop/flashcard-revealed-1366x768.png`
- `screenshots/desktop/flashcard-unrevealed-1366x768.png`
- `screenshots/desktop/history-1366x768.png`
- `screenshots/desktop/history-empty-1366x768.png`
- `screenshots/desktop/history-reopen-1366x768.png`
- `screenshots/desktop/home-1366x768.png`
- `screenshots/desktop/privacy-delete-confirmation-1366x768.png`
- `screenshots/desktop/privacy-export-1366x768.png`
- `screenshots/desktop/progress-1366x768.png`
- `screenshots/desktop/progress-empty-1366x768.png`
- `screenshots/desktop/quiz-config-1366x768.png`
- `screenshots/desktop/quiz-config-cse-professional-1366x768.png`
- `screenshots/desktop/quiz-config-let-elementary-1366x768.png`
- `screenshots/desktop/quiz-numeric-1366x768.png`
- `screenshots/desktop/quiz-svg-1366x768.png`
- `screenshots/desktop/quiz-table-1366x768.png`
- `screenshots/desktop/quiz-text-1366x768.png`
- `screenshots/desktop/result-review-1366x768.png`
- `screenshots/desktop/result-summary-1366x768.png`
- `screenshots/desktop/study-landing-1366x768.png`
- `screenshots/desktop/study-material-detail-1366x768.png`
- `screenshots/desktop/study-resource-1366x768.png`

### Mobile (47)

- `screenshots/mobile/answer-feedback-390x844.png`
- `screenshots/mobile/answer-selected-390x844.png`
- `screenshots/mobile/board-cpa-390x844.png`
- `screenshots/mobile/board-cse-390x844.png`
- `screenshots/mobile/board-engineering-390x844.png`
- `screenshots/mobile/board-let-390x844.png`
- `screenshots/mobile/board-nursing-390x844.png`
- `screenshots/mobile/dashboard-390x844.png`
- `screenshots/mobile/dashboard-width-320-320x640.png`
- `screenshots/mobile/dashboard-width-360-360x800.png`
- `screenshots/mobile/dashboard-width-390-390x844.png`
- `screenshots/mobile/dashboard-width-430-430x932.png`
- `screenshots/mobile/empty-state-390x844.png`
- `screenshots/mobile/error-state-390x844.png`
- `screenshots/mobile/exams-390x844.png`
- `screenshots/mobile/finish-quiz-ready-390x844.png`
- `screenshots/mobile/flashcard-landing-390x844.png`
- `screenshots/mobile/flashcard-revealed-390x844.png`
- `screenshots/mobile/flashcard-unrevealed-390x844.png`
- `screenshots/mobile/history-390x844.png`
- `screenshots/mobile/history-empty-390x844.png`
- `screenshots/mobile/history-reopen-390x844.png`
- `screenshots/mobile/home-390x844.png`
- `screenshots/mobile/mobile-nav-390x844.png`
- `screenshots/mobile/privacy-delete-confirmation-390x844.png`
- `screenshots/mobile/privacy-export-390x844.png`
- `screenshots/mobile/progress-390x844.png`
- `screenshots/mobile/progress-empty-390x844.png`
- `screenshots/mobile/quiz-config-390x844.png`
- `screenshots/mobile/quiz-config-cse-professional-390x844.png`
- `screenshots/mobile/quiz-config-let-elementary-390x844.png`
- `screenshots/mobile/quiz-numeric-320x640.png`
- `screenshots/mobile/quiz-numeric-360x800.png`
- `screenshots/mobile/quiz-numeric-390x844.png`
- `screenshots/mobile/quiz-numeric-430x932.png`
- `screenshots/mobile/quiz-svg-390x844.png`
- `screenshots/mobile/quiz-table-320x640.png`
- `screenshots/mobile/quiz-table-360x800.png`
- `screenshots/mobile/quiz-table-390x844.png`
- `screenshots/mobile/quiz-table-430x932.png`
- `screenshots/mobile/quiz-text-390x844.png`
- `screenshots/mobile/result-review-390x844.png`
- `screenshots/mobile/result-summary-390x844.png`
- `screenshots/mobile/study-landing-390x844.png`
- `screenshots/mobile/study-material-detail-390x844.png`
- `screenshots/mobile/study-resource-390x844.png`
- `screenshots/mobile/table-question-result-390x844.png`

### Tablet (3)

- `screenshots/tablet/dashboard-width-768-768x1024.png`
- `screenshots/tablet/quiz-numeric-768x1024.png`
- `screenshots/tablet/quiz-table-768x1024.png`
