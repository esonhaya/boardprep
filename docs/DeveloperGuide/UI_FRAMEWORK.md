# Portable server-rendered UI framework

BoardPrep’s UI framework is class/data-attribute based so it can be reused by another lightweight PHP project without importing BoardPrep domain code.

## Layers

- **Tokens/theme:** `public/assets/css/style.css` exposes generic `--ui-*` variables inside `.ui-theme`, with BoardPrep brand values mapped into the theme.
- **Base/layout:** semantic defaults, focus states, typography, controls, app shells, page headers, and workspaces.
- **Components:** `.card`, `.badge`, `.alert`, `.button`, `.progress-bar`, and `.empty-state`.
- **Patterns:** `.ui-metric-grid`, `.ui-record-list`, `.ui-record`, `.ui-metadata-grid`, `.ui-action-group`, `.ui-filter-bar`, and `.ui-dense-table-wrap`.
- **Density:** `.ui-density-standard`, `.ui-density-compact`, and `.ui-density-dense`.

## Responsive contracts

Use metric grids for summary counts, record lists for moderate metadata, and `.ui-dense-table-wrap`/`data-ui-dense-table` only for genuinely dense datasets. Dashboard and ordinary card content should not use a scroll-table workaround. Action groups wrap and forms stack at mobile breakpoints.

## Preview collections

Summary collections declare intent with `data-ui-collection="preview"` and `data-ui-limit="5"`, plus a `data-ui-view-all` link when a full destination exists. `App\\Support\\Presentation\\PreviewCollection` provides the BoardPrep presentation boundary. Full index/history destinations remain untruncated.

## Presentation formatting

`App\\Support\\Presentation\\UiFormatter` handles human-facing dates, percentages, scores, status labels, and bounded text previews. Raw storage values are unchanged.

## Doctor integration

Generic Haya Doctor `UiContract::analyze()` delegates to `UiStaticAnalyzer`, returning a score, structured `DiagnosticFinding` objects, per-rule counts, source file, line evidence where available, and recommendations. BoardPrep’s `UiContractCheck` supplies only project paths/configuration; the core analyzer knows nothing about quizzes, LET, or BoardPrep routes.

The fixture suite covers legacy tables, dense-table opt-in, global wrapping, preview limits, valid previews, emoji actions, and unlabeled controls.

## Extraction readiness

Copy the `ui-*` CSS block, map a project theme to the generic variables, copy the Haya Doctor UI contract/analyzer classes, and provide a project adapter with templates/stylesheets configuration. No BoardPrep service or domain class is required by the generic analyzer.
