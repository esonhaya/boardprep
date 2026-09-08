# BoardPrep Batch 570 visual UX audit

The Batch 570 Playwright visual-baseline test captures deterministic learner
states at 390×844 and 1366×768 from the production PHP server. Each capture is
accompanied by geometry checks for whole-page overflow, important controls
outside the viewport, table containment, figure containment, image health, and
mobile touch-target size. The generated `visual-audit.md` report and PNG files
are retained in the Playwright artifact for the CI run.

The suite intentionally does not claim subjective UX findings from geometry
automation. After the first successful Chromium run, review every retained
screenshot and record evidence-backed findings using this schema:

| ID | Page | Viewport | Screenshot | Severity | Category | Observation | User impact | Recommended repair | Batch 570 action | Batch 571 disposition |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| — | — | — | — | — | — | No screenshot-backed finding is asserted before the external Chromium artifact review. | — | — | Pending CI evidence | Review after Batch 570 capture |

Severity remains bounded to P0 (blocking), P1 (major learner defect), P2
(meaningful non-blocking UX defect), and P3 (polish). No product redesign is
authorized by this audit record.
