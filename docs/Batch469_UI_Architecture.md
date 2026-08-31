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
