# Batch 686 Chromium review inventory

## Exact-commit result

- Workflow: [Browser acceptance run 38922718846](https://github.com/esonhaya/boardprep/actions/runs/38922718846)
- Exact test commit: `044f996603c6306c4982f0d6cb3e987cfbd6d1d8`
- Registered result: `VERIFIED_PASS`; desktop and mobile projects completed.
- Viewports: desktop `1366×768`, mobile `390×844`; the existing mobile viewport sweep also covers `320×640`, `360×800`, `430×932`, and `768×1024`.
- Expected matrix: 30 project executions, 26 passing and 4 intentionally skipped by project scope. The count is derived from the committed spec/project matrix; the hosted `browser-results.json` counts were not retrieved independently.
- New focused assertions: PDF link/response MIME and signature, manifest SHA-256 and lesson identity, searchable PDF title text, keyboard-triggered download byte equality, mobile board-to-library-to-lesson navigation, and GET-only developer question-editor inspection with the visible update form.
- Browser review versus manual acceptance: all test assertions passed. Automated screenshot capture is evidence only; no human visual, screen-reader, WCAG, or physical-device acceptance is recorded.

## Remote evidence artifact

Artifact `12054784399`, `boardprep-chromium-visual-qa`, is attached to run 38922718846. GitHub reports `127,016,768` bytes and expiry `2026-10-30T15:20:47Z`. It contains the workflow's screenshots, `visual-audit.md`, `defect-register.md`, Playwright results, console diagnostics, and test attachments. It was not downloaded to Termux because free storage was below the 1.5 GiB safeguard. Review the artifact through GitHub Actions when a reviewer has sufficient storage.

## Failure-to-repair trail

| Run | Exact SHA | Observed failure | Classification and response |
|---|---|---|---|
| [38918714862](https://github.com/esonhaya/boardprep/actions/runs/38918714862) | `db5dad1df645bef29bc80231ee5e611a481bbe0f` | Headless Chromium began a download during direct PDF navigation in desktop/mobile; editor test selected a hidden conditional placement form. | Test expectation defects. Validate the real PDF response and bytes, exercise the actual link's keyboard download, and select the visible `/question-editor/update` form while asserting zero write requests. |
| [38920723927](https://github.com/esonhaya/boardprep/actions/runs/38920723927) | `218309fe97d26e9f00b757e90f29c2b678993b82` | Diagnostic annotation confirmed the same PDF navigation and hidden-form selector assumptions. | The bounded diagnostic step was added so later failures expose test names and assertion text publicly; no application defect was identified. |
| [38921894492](https://github.com/esonhaya/boardprep/actions/runs/38921894492) | `a2e40a651c605b2125508defaa60c02220044e77` | The PDF download link was more than 30 Tab presses from the page start, so the reusable helper stopped before checking focus. | Test-bound defect. The PDF assertion now allows up to 100 genuine Tab events, retaining its focus-visible assertion. The next exact-commit run passed. |

No assertion was suppressed, removed, or weakened. The app's static PDF response was inspected locally: HTTP 200, `Content-Type: application/pdf`, valid `%PDF-` signature, and no `Content-Disposition` header. Direct PDF navigation is handled as a download by this headless Chromium setup.
