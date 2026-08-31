# Batch 469 UI architecture map

BoardPrep remains a lightweight server-rendered PHP application. `App\Core\View` renders a page view into one of two shared layouts: `main` for learner routes and `developer` for Content Studio routes.

## Shared layers

- **Tokens/base:** `public/assets/css/style.css` defines color, typography, spacing, radius, shadow, focus, and breakpoint variables plus semantic element defaults.
- **Brand:** `app/Views/components/brand.php` is the reusable inline SVG open-book mark and wordmark.
- **Shell/navigation:** `app/Views/layouts/main.php` and `app/Views/layouts/developer.php` own landmarks, skip links, stylesheet/script loading, and flash placement. Learner navigation is in the main layout; developer navigation is in `developer/sidebar.php` and `developer/topbar.php`.
- **Patterns:** shared classes cover page headers, actions, cards/panels, stat cards, forms, alerts, badges, progress bars, tables, empty states, quiz choices, feedback, and result review.
- **Bounded behavior:** `public/assets/js/app-shell.js` only controls the mobile navigation disclosure; server routes remain authoritative.

## Reachable product areas

- **Learner:** `/`, `/dashboard`, `/quiz`, `/history`, `/study`, `/progress`, `/profile`, and subject entry views use the learner shell.
- **Developer:** `/developer`, question inventory/editor/import/export, quality, coverage, taxonomy, boards, subjects, blueprints, inspector, doctor, and metadata tools use the developer shell through `BaseDeveloperController`.
- **Data boundary:** controllers and services retain existing route contracts and storage-backed values; the overhaul changes presentation and navigation only.

## Batch 470 visual migration matrix

| Route | View | Current pattern | Target pattern | Mobile pattern | Status |
|---|---|---|---|---|---|
| `/` | `home/index.php` | Hero | Branded hero/action group | Stacked hero | Complete |
| `/dashboard` | `dashboard/index.php` | Summary blocks | Metric grid + recommendation card | Two-column cards | Complete |
| `/quiz` | `quiz/*` | Setup/form/answer flow | Focused card, progress, answer cards | Full-width controls/cards | Complete |
| `/history`, `/progress`, `/profile`, `/study` | learner views | Lists and summaries | Shared cards/metrics | Stacked records | Inherited/shared |
| `/developer` | `developer/dashboard.php` | Six-column health and action tables | Metric grid, action groups, status/record cards | One/two-column cards | Complete |
| `/question-editor*` | editor/workspace views | Inline styles and spacing breaks | Filter bar, workspace panels, editor sections | Single-column panels | Complete |
| `/question-quality`, `/coverage`, `/metadata-repair` | developer reports | Border tables | Summary metrics and record lists | Stacked records | Complete |
| `/boards`, `/subjects`, `/blueprints`, `/taxonomy` | developer management views | Tables/unstyled lists | Cards, metric lists, badges | Stacked cards | Complete |
| `/developer/doctor` | doctor view | Dense check table | Local-scroll data table | Intentional local scroll | Complete |
