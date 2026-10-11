# BoardPrep product completeness and launch gate

This is the evidence-based product audit for the v1.0.1 product baseline. It is
separate from technical `RELEASE_GATE`: release/content checks do not prove that
learners can use every workflow, that a deployment is correctly configured, or
that a human has accepted the presentation.

## Run the audit

```sh
php tools/product-readiness.php
php tools/product-readiness.php --json
```

The JSON report enumerates the actual registered routes and project files for
controllers, services, repositories, storage JSON, views, frontend assets, and
tests. It also returns the feature assessment, board evidence, defect register,
missing capabilities, and product gate. The command is local/CLI-only; no
installer, administration, Doctor, or product-audit endpoint is public. A
non-zero exit while mandatory product evidence is unresolved is intentional.
Update `config/product-readiness.json` only when new evidence is reviewed; do not
convert an untested item to PASS because a file or route exists.

The gate rules are:

- `TECHNICAL_RELEASE_READY` is decided independently by
  `php tools/doctor.php --release --json`.
- `PRODUCT_FEATURE_COMPLETE` requires every in-scope feature to be
  `IMPLEMENTED_AND_VERIFIED`, every supported board journey to be
  `IMPLEMENTED_AND_VERIFIED`, and no open P0/P1 finding.
- `PRODUCT_LAUNCH_READY` additionally requires no blocking missing capability
  and PASS evidence for every mandatory human, deployment, privacy, browser, and
  board-journey check. `NOT_VERIFIED` is not a pass.

## Runtime inventory

`routes/web.php` has 17 production web route registrations:

| Method | Route | Handler |
|---|---|---|
| GET | `/` | `HomeController::index` |
| GET | `/exams` | `HomeController::exams` |
| GET | `/privacy` | `DataHandlingController::index` |
| GET | `/grammar` | Inline static view |
| GET | `/dashboard` | `DashboardController::index` |
| GET | `/history` | `HistoryController::index` |
| GET | `/history/result` | `HistoryController::result` |
| GET, POST | `/quiz` | `QuizFlowController::handle` |
| GET | `/study` | `StudyDashboardController::index` |
| GET | `/study-library` | `StudyLibraryController::index` |
| GET | `/profile` | `LearningProfileController::index` |
| GET | `/progress` | `ProgressController::index` |
| GET | `/flashcards` | `FlashcardController::index` |
| GET | `/flashcards/missed` | `FlashcardController::missed` |
| GET, POST | `/flashcards/review` | `FlashcardController::review` / `submitReview` |

The following 43 web registrations are inside the non-production environment
block. They are not authenticated production administration; they must remain
unavailable when `APP_ENV=production`:

- GET: `/developer`, `/developer/content-intelligence`,
  `/developer/study-library`, `/developer/doctor`, `/boards`, `/board/create`,
  `/board/view`, `/subjects`, `/subject/create`, `/subject/edit`,
  `/subject/view`, `/question-editor`, `/question-editor/create`,
  `/question-editor/edit`, `/question-export`, `/question-import`,
  `/question-quality`, `/question-inspector`, `/coverage`, `/taxonomy`,
  `/metadata-repair`, `/blueprints`, `/blueprints/create`, `/blueprint-health`.
- POST: `/developer/doctor/run`, `/board/save`, `/board/archive`,
  `/board/activate`, `/subject/save`, `/subject/update`, `/subject/archive`,
  `/subject/activate`, `/question-editor/save`, `/question-editor/update`,
  `/question-editor/archive`, `/question-editor/restore`,
  `/question-editor/share`, `/question-editor/placement`,
  `/question-editor/unshare`, `/question-editor/duplicate`,
  `/question-import/import`, `/taxonomy/rebuild`, `/blueprints/save`.

Separate public PHP entry points are `public/health.php` (limited status and
release identity) and `public/api/doctor.php` (returns 404 in production).
`public/router.php` is a built-in-server smoke helper, not a production server
configuration. Runtime boundaries are `app/Core/Router.php`,
`SessionConfiguration.php`, `SecurityHeaders.php`, and `ExceptionHandler.php`.
There is no production authentication/authorization middleware because no
production admin or learner login exists.

The executable JSON inventory enumerates all current PHP controllers, services,
repositories, JSON storage files, templates, assets, and tests. Main areas are:

- Controllers: `app/Controllers/`; learner templates: `app/Views/home/`,
  `dashboard/`, `history/`, `progress/`, `profile/`, `quiz/`, `study/`, and
  `flashcards/`.
- Domain logic: `app/Services/` and `app/Domains/`; persistence: `app/Storage/`,
  `app/Repositories/`, `app/Core/Database.php`, and `database/migrations/`.
- Private curated/runtime records: `storage/*.json` and nested taxonomies,
  questions, board, subject, and blueprint collections. The product-ready
  package and release profile define which collections are canonical versus
  mutable; do not publish this tree.
- Frontend: `public/assets/css/`, `js/`, and board-specific images; tests:
  `tools/Tests/`, `tools/Doctor/`, and `tests/browser/`.
- Runtime controls: `APP_ENV`, `APP_DEBUG`, `APP_TIMEZONE`, `APP_MEMORY_LIMIT`,
  `APP_STORAGE_PATH`, `DB_DRIVER`, `DB_SQLITE_PATH`, and CLI-preflight-only
  `APP_PUBLIC_PATH`. `APP_ENV` is the Developer/Doctor route boundary, not an
  authentication substitute.

## Evidence and user journeys

| Journey or surface | Current classification | Evidence and remaining limit |
|---|---|---|
| Home, entry links, board listing | `IMPLEMENTED_AND_VERIFIED` / `PARTIAL` | `LearnerJourneyHttpTest.php` and `PublicBoardDiscoveryTest.php`; public listing shows ten populated boards, but not every detail-to-result journey is individually executed. |
| Practice, question selection, answer feedback, submit, score and review | `IMPLEMENTED_AND_VERIFIED` | `LearnerJourneyHttpTest.php`, `QuizCompletionHttpTest.php`, `QuizStartFailureHttpTest.php`, and `QuizSessionResilienceTest.php` cover production routes and recovery. |
| History, historical result, progress, dashboard and recommendations | `IMPLEMENTED_AND_VERIFIED` | `HistoryResultHttpTest.php`, `MultiAttemptLearningHttpTest.php`, and learner journey tests; attempt reads are scoped to the random learner identity. |
| Adaptive practice and weakness analysis | `IMPLEMENTED_AND_VERIFIED` | Focused learning/recommendation tests plus the multi-page HTTP journey; behavior remains bounded by actual completed attempts. |
| Exam mode | `PARTIAL` | `ExamSimulationHttpTest.php` verifies answer withholding and partial result persistence for LET. Setup now states there is no timer and no claim of a complete official-format replica for every board. |
| Flashcards | `IMPLEMENTED_AND_VERIFIED` | `FlashcardHttpTest.php` and integration/scheduling tests cover reveal, rating, CSRF, persisted state, and learner isolation. |
| Source-first study support | `IMPLEMENTED_AND_VERIFIED` after Batch 662 | All 8,962 canonical questions have study-material links. Batch 662 connects those links from practice feedback and result review, plus direct board-filtered and flashcard references. |
| Resume/reload, stale requests and retry | `IMPLEMENTED_AND_VERIFIED` in HTTP/simulation tests | Session is browser-cookie based; no account recovery or cross-device continuity is provided. |
| Empty/error states | `IMPLEMENTED_AND_VERIFIED` structurally and in focused paths | Existing empty-state, invalid-action, shortage, and start-failure tests cover key paths; no claim that every copy has human acceptance. |
| Mobile, visual layout, keyboard and assistive technology | `IMPLEMENTED_UNVERIFIED` | Static view/accessibility contracts exist. Playwright and a real browser/device were unavailable in this environment; visual and practical accessibility are NOT_VERIFIED. |
| Returning learner state | `IMPLEMENTED_AND_VERIFIED` within the same PHP session | Random session identity scopes history, weakness and flashcards. A new browser/session is a different anonymous learner. |

`tools/Tests/` has meaningful feature and HTTP tests, but the count of tests is
not proof of coverage. Some legacy tests are source/contract assertions. The
manual matrix in `docs/HUMAN_TESTER_READINESS.md` is authoritative for the
12 learner acceptance cases; all remain `NOT_RUN` until a real tester records
evidence. The Playwright workflow is documented in `README.md`; a missing
Playwright install is NOT_VERIFIED, not PASS.

### Ten-board evidence

Every board is in the public listing and has board-filtered published study
materials. `StudyLibraryNavigationTest.php` exercises all ten board filters via
the production HTTP route. The full learner workflow (board detail, practice,
exam mode where applicable, explanation, result, history, progress, and
resources) is not individually proven for all ten; most use common generic
controllers and templates, but that is not equivalent to an end-to-end pass.

| Board | Inventory status | Evidence limit |
|---|---|---|
| LET | `PARTIAL` | Detail, learner practice/result journey, exam-mode HTTP, and board-scoped study materials have targeted coverage; not human accepted. |
| CSE | `IMPLEMENTED_UNVERIFIED` | Listed and board-filtered materials are covered; the board-specific full learner HTTP journey is not. Requires Professional/Subprofessional selection. |
| NURSING | `IMPLEMENTED_UNVERIFIED` | Listed and board-filtered materials are covered; the board-specific full learner HTTP journey is not. |
| CRIMINOLOGIST | `IMPLEMENTED_UNVERIFIED` | Listed and board-filtered materials are covered; the board-specific full learner HTTP journey is not. |
| CIVIL_ENGINEERING | `IMPLEMENTED_UNVERIFIED` | Listed and board-filtered materials are covered; the board-specific full learner HTTP journey is not. |
| MECHANICAL_ENGINEERING | `IMPLEMENTED_UNVERIFIED` | Listed and board-filtered materials are covered; the board-specific full learner HTTP journey is not. |
| REE | `IMPLEMENTED_UNVERIFIED` | Listed and board-filtered materials are covered; the board-specific full learner HTTP journey is not. |
| ARCHITECTURE | `PARTIAL` | Detail/material filter covered; no board-specific full learner HTTP journey. |
| PSYCHOMETRICIAN | `IMPLEMENTED_UNVERIFIED` | Listed and board-filtered materials are covered; the board-specific full learner HTTP journey is not. |
| CPA | `IMPLEMENTED_UNVERIFIED` | Listed and board-filtered materials are covered; the board-specific full learner HTTP journey is not. |

The machine-readable `board_assessment` records these limits board by board.
No board is labeled fully verified merely because its content count is nonzero.

## Administration and identity

There is no production admin login, first-admin bootstrap route, or
production-authorized authoring UI. Board, subject, question, taxonomy, import,
export, blueprint, and Doctor tooling exists only in non-production routes.
`APP_ENV=production` removes those routes; `ProductionQuizCsrfTest.php` checks
the boundary. Those non-production routes do not have a production-grade role
authorization system and must never be made public by changing production to
development/testing. Do not add default credentials or a public setup endpoint.

The frozen MVP scope excludes role-based production administration. Curated
content is changed through reviewed repository updates and release packages;
v1.0.1 does not need live authoring to serve its read-only learning product.
This is intentionally deferred, not an admin feature that is claimed complete.

Learners are anonymous, not registered accounts. `LearnerIdentityService`
generates a random ID stored in the PHP session. `AttemptRepository`, weakness
storage, and flashcard state are scoped by that ID; user-supplied owner IDs do
not override it. Attempts/answer review and derived state are persisted in
private JSON by default or mutable-state SQLite when configured. Clearing a
cookie can make old records inaccessible to the learner without deleting them.
There is no login recovery, cross-device state, self-service export, or
self-service deletion. The last capabilities are not represented as features.

`/privacy` now explains the implemented anonymous state model and its limits.
It is not a deployment-specific legal policy. The site operator still needs to
provide its identity/contact, retention and deletion policy, and actual hosting,
log, and backup practices before launch. The user-facing notice intentionally
does not invent those facts.

## Security, persistence, and isolation

- Production `APP_DEBUG` must be false. Browser exceptions are generic; the
  configured PHP error log retains diagnostic detail and must be kept private.
- Session cookies are HttpOnly, SameSite=Lax, strict-ID mode is enabled, and
  Secure depends on the web server correctly forwarding HTTPS to PHP. CSRF
  validation covers quiz and flashcard mutations. The app has no login or
  password flow to audit.
- Learner question, explanation, study material, taxonomy, and structured
  content are escaped/rendered through controlled templates. Figure assets are
  path-validated. No general learner upload/download path exists.
- JSON mutation uses a storage lock and atomic replacement. SQLite uses PDO,
  migrations, and a busy timeout. SQLite is optional (`pdo_sqlite`); canonical
  content remains JSON. Shared-host filesystem lock/rename semantics, quotas,
  and concurrent-write capacity are provider-specific and NOT_VERIFIED.
- The root `storage/` and all state, `.env`, SQLite files, error logs, backups,
  and release artifacts must remain outside the document root. Production
  health exposes only limited status and release identity; Doctor and authoring
  routes stay private.
- Separate every PHP project’s private code, `.env`, database credentials,
  writable paths, session namespace, logs, and backups. Never share writable
  state or credentials across unrelated domains. Cookies are host-only by
  default, but same-host path projects may collide on PHP's default `PHPSESSID`
  and `/` cookie path. Shared hosting under one account is not strong process
  isolation: account-level filesystem access, PHP identity, backups/logs,
  session storage, CPU, memory, process, and inode limits can cross project
  boundaries or cause contention.

See the existing [`RELEASE_RUNBOOK_V1.md`](RELEASE_RUNBOOK_V1.md) for the single
hosting-selection checklist and deployment procedure, including the private
`public/` layout and safe fixed-`public_html` shim. Its simulations verify local
fixtures only; actual Apache/Nginx rules, provider permissions, account
isolation, and remote HTTPS remain NOT_VERIFIED without a real host.

## Defect and capability register

The machine-readable register is `config/product-readiness.json`; fields match
the product defect contract: ID, feature, user impact, evidence, reproduction,
severity, root cause, status, fix commit, test coverage, release-blocking flag,
and dependencies. Missing capabilities are kept in a separate collection.

| ID | Severity | Finding | Status | Release blocking |
|---|---:|---|---|---|
| `BP662-DEF-001` | P2 | Flashcard study-reference links went to the generic library. | Fixed: material identity now opens its exact published item. | No |
| `BP662-DEF-002` | P2 | Board detail lacked a board-scoped study-material action. | Fixed: detail links use canonical board IDs and verified filters. | No |
| `BP662-DEF-003` | P2 | Shared footer said `v0.1` on v1.0.1. | Fixed: footer reads configured release identity. | No |
| `BP662-DEF-004` | P2 | No learner-facing notice described anonymous session state. | Fixed with limitation: `/privacy` discloses actual behavior; site policy remains open. | No |
| `BP662-DEF-005` | P2 | Exam mode could be mistaken for a timed official replica. | Fixed with limitation: setup states no timer and no universal official-format claim. | No |
| `BP662-DEF-006` | P2 | Question-to-material mappings were not visible from quiz feedback/results. | Fixed: practice and both current/historical review link relevant published materials. | No |
| `BP662-DEF-007` | P3 | The source validator rejected valid committed source categories. | Fixed: validator accepts the existing source taxonomy; regression test passes. | No |

Blocking missing capability `BP662-CAP-001` is site-specific privacy terms and
data controls. Cross-device accounts (`CAP-002`) and full timed official-format
simulators for every board (`CAP-003`) are intentionally deferred beyond the
frozen MVP. Production authoring (`CAP-004`) is intentionally deferred because
curated content is release-managed; revisit only if an operator requirement
shows that manual reviewed releases are inadequate.

## Roadmap by dependency and impact

### Must fix before public launch

- Have the actual site owner approve and publish complete deployment-specific
  privacy terms: operator identity/contact, retention, deletion/export route,
  hosting/provider access, logs, backups, and support handling. Do not launch
  using the generic in-product notice as a substitute.
- Execute all `MVP-01` through `MVP-12` acceptance cases on a real browser and
  phone; record build, device/browser, viewport, steps, and evidence in
  `HUMAN_TESTER_READINESS.md`.
- Run the browser suite with Chromium; inspect narrow and wide screens,
  orientation, long choices/tables/math, touch targets, keyboard focus, and
  accessibility. Static CSS/view tests do not close this item.
- Exercise the exact package on the chosen provider/domain over HTTPS. Verify
  PHP version/extensions, Secure cookie behavior, rewrite rules, private
  storage/session paths, backup/restore access, and safe HTTP exposure checks.
- Run a board-specific production learner smoke for all ten boards, including
  board-level subject/eligibility edge cases, exam mode claims, result/history,
  progress, explanations, and resource navigation.

### Should fix before launch

- Establish an operator-run learner data deletion/export procedure that is
  consistent with the chosen host’s backups/logs and approved retention terms.
- Confirm representative concurrent writes and storage recovery against the
  provider’s actual JSON lock/rename or SQLite filesystem behavior.
- If live authoring is needed, first define a separate authenticated,
  authorized, audited administration requirement. Do not expose existing
  Developer/Doctor routes as a shortcut.

### Post-launch optional

- Account-based cross-device continuity and recovery, if user research supports
  the added privacy and authentication burden.
- Timers and verified official exam-format replicas, only with current,
  board-specific authoritative duration and weighting evidence.
- Further production authoring convenience if release-managed content updates
  become operationally insufficient.

## Current gate interpretation

The technical release gate remains independent and must be run separately.
Batch 662's product gate is expected to remain NO until the above mandatory
human, provider, privacy-owner, and per-board evidence is recorded. This is a
deliberate readiness result, not a code/test failure and not evidence of a
successful deployment.
