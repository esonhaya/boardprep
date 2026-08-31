# BoardPrep MVP v0.1.0 — Scope Freeze

This document is the canonical MVP scope for the release candidate. It records
implemented behavior only; future ideas are not part of the MVP finish line.

## Learner MVP

- Home and learner entry surfaces.
- Practice quiz and currently supported exam-mode flow.
- Question rendering, answer submission, Back/Next navigation, and completion.
- Results, result revisit/history, and supported retake/recovery paths.
- Session persistence, reload/stale-session recovery, exact-once completion, and
  isolation between sessions.
- Content eligibility rules: active/approved eligible content; draft and
  archived content excluded.

## Developer MVP

- Developer dashboard and operational entry points.
- Question inventory, text search, subject/domain/topic/difficulty/status filters,
  empty results, and edit-context preservation.
- Question create/edit authoring with taxonomy, options, explanations,
  difficulty, status, validation, duplicate feedback, and failed-input
  preservation.
- Question lifecycle actions for active/draft/approved/archived records.
- Quality and duplicate inspection tooling.
- Existing board, subject, and taxonomy management surfaces.
- JSON question import with validation/rejection feedback and JSON export with
  identity preservation.
- Coverage matrix for subject/topic content gaps.

## Deployment MVP

- PHP 8.1+ with `json` and `session`; `pdo_mysql` only when MySQL is selected.
- `public/` is the web root and requests are routed through `public/index.php`;
  repository internals remain private.
- `.env` and process environment variables configure environment, timezone,
  storage driver/path, and optional MySQL credentials. Secrets are not tracked.
- JSON storage is the default and must be durable, readable, writable, and able
  to create collection files. MySQL is an optional configured backend.
- Production requires `APP_ENV=production` and rejects debug mode. PHP/server
  logging handles detailed failures while learner responses remain generic.
- Sessions use strict IDs, `HttpOnly`, `SameSite=Lax`, and HTTPS-aware secure
  cookies.

## Explicit non-goals

- AI authoring or semantic duplicate detection.
- Advanced analytics, monetization, or role-based admin architecture.
- Advanced bulk authoring and new import formats.
- Large content expansion.
- Complete UI/cosmetic redesign.
- Perfect Doctor maintainability health or general architecture cleanup.

## Release decision record

`HUMAN_ACCEPTANCE_EXECUTED=NO`.

`HUMAN_ACCEPTANCE_ASSUMED_PASS_BY_RELEASE_DECISION=YES` for release planning,
as explicitly authorized by the release owner. This is not test evidence and
does not claim that browser/device acceptance was observed.

`MVP_SCOPE_FROZEN=YES` once the release-candidate gates recorded in the
checklist pass. Further product or engineering expansion is post-MVP work.
