# BoardPrep product completeness and launch gate

This is the evidence-based product audit for the v1.0.1 baseline, revalidated
on the Batch 663 branch. It is
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
convert an untested item to PASS because a file or route exists. It checks
board-matrix completeness, privacy routes and test registration, exam-mode
disclosure, and the twelve-case human matrix; those checks do not replace
running the HTTP tests.

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

`routes/web.php` has 19 production web route registrations:

| Method | Route | Handler |
|---|---|---|
| GET | `/` | `HomeController::index` |
| GET | `/exams` | `HomeController::exams` |
| GET | `/privacy` | `DataHandlingController::index` |
| GET | `/privacy/export` | `DataHandlingController::export` |
| POST | `/privacy/data/clear` | `DataHandlingController::clear` |
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
| Home, entry links, board listing and board detail | `IMPLEMENTED_AND_VERIFIED` | `TenBoardLearnerJourneyHttpTest.php` and `PublicBoardDiscoveryTest.php` exercise production HTTP board entry for all ten boards. Browser and human presentation remain unverified. |
| Practice, question selection, answer feedback, submit, score, explanation, retry and recovery | `IMPLEMENTED_AND_VERIFIED` | `TenBoardLearnerJourneyHttpTest.php` exercises each board with a source-linked eligible question; `LearnerJourneyHttpTest.php`, `QuizStartFailureHttpTest.php`, and `QuizSessionResilienceTest.php` add generic lifecycle/recovery coverage. |
| History, historical result, progress, dashboard and recommendations | `IMPLEMENTED_AND_VERIFIED` | `TenBoardLearnerJourneyHttpTest.php`, `HistoryResultHttpTest.php`, `MultiAttemptLearningHttpTest.php`, and learner journey tests; attempts remain scoped to the random learner identity. |
| Adaptive practice and weakness analysis | `IMPLEMENTED_AND_VERIFIED` | Focused learning/recommendation tests plus the multi-page HTTP journey; behavior remains bounded by actual completed attempts. |
| Exam mode | `PARTIAL` | `ExamSimulationHttpTest.php` verifies answer withholding and partial result persistence for LET. Setup now states there is no timer and no claim of a complete official-format replica for every board. |
| Flashcards | `IMPLEMENTED_AND_VERIFIED` | `FlashcardHttpTest.php` and integration/scheduling tests cover reveal, rating, CSRF, persisted state, and learner isolation. |
| Source-first study support | `IMPLEMENTED_AND_VERIFIED` | Board-specific study libraries, question feedback links, and published material/source navigation are exercised by `TenBoardLearnerJourneyHttpTest.php`, `StudyLibraryNavigationTest.php`, and source/reference tests. This verifies linkage/rendering, not every resource's educational quality. |
| Resume/reload, stale requests and retry | `IMPLEMENTED_AND_VERIFIED` in HTTP/simulation tests | Session is browser-cookie based; no account recovery or cross-device continuity is provided. |
| Empty/error states | `IMPLEMENTED_AND_VERIFIED` structurally and in focused paths | Existing empty-state, invalid-action, shortage, and start-failure tests cover key paths; no claim that every copy has human acceptance. |
| Mobile, visual layout, keyboard and assistive technology | `IMPLEMENTED_UNVERIFIED` | Static view/accessibility contracts exist. Playwright and a real browser/device were unavailable in this environment; visual and practical accessibility are NOT_VERIFIED. |
| Anonymous data visibility, export, deletion and returning learner state | `PARTIAL` | `AnonymousLearnerPrivacyHttpTest.php` verifies session-scoped export/deletion, CSRF, confirmation, session rotation, idempotence, and JSON/SQLite isolation. Owner-approved terms and external log/backup retention remain outstanding. |

The 14 feature rows reconcile to 10 `IMPLEMENTED_AND_VERIFIED`, 2 `PARTIAL`,
1 `IMPLEMENTED_UNVERIFIED`, and 1 `INTENTIONALLY_DEFERRED`. This is distinct
from the ten-board matrix and from the technical release gate.

`tools/Tests/` has meaningful feature and HTTP tests, but the count of tests is
not proof of coverage. Some legacy tests are source/contract assertions. The
manual matrix in `docs/HUMAN_TESTER_READINESS.md` is authoritative for the
12 learner acceptance cases; automation does not change any human status, and
no human acceptance was conducted in Batch 663. The Playwright workflow is
documented in `README.md`; no supported browser engine was available here, so
rendered mobile, visual accessibility, and browser-interaction evidence remain
`NOT_VERIFIED`.

### Ten-board evidence

`TenBoardLearnerJourneyHttpTest.php` uses reduced fixtures selected from
canonical question, eligibility, and source data and exercises production HTTP
routes for all ten boards. Each passes discovery/detail, board-filtered study,
effective-subject routing, eligibility, practice answer, scoring, explanation,
published study reference, result review, history, progress, another attempt,
and stale-action recovery. This is automated HTTP evidence, not browser
rendering or human acceptance.

| Board | Inventory status | Evidence limit |
|---|---|---|
| LET | `AUTOMATED_PASS` | Representative production HTTP path passes. Browser and human acceptance remain `NOT_VERIFIED`. |
| CSE | `AUTOMATED_PASS` | Subprofessional path, eligibility, and complete representative HTTP path pass. Browser and human acceptance remain `NOT_VERIFIED`. |
| NURSING | `AUTOMATED_PASS` | Representative production HTTP path passes. Browser and human acceptance remain `NOT_VERIFIED`. |
| CRIMINOLOGIST | `AUTOMATED_PASS` | Representative production HTTP path passes, including code normalization. Browser and human acceptance remain `NOT_VERIFIED`. |
| CIVIL_ENGINEERING | `AUTOMATED_PASS` | Representative production HTTP path passes, including code normalization. Browser and human acceptance remain `NOT_VERIFIED`. |
| MECHANICAL_ENGINEERING | `AUTOMATED_PASS` | Representative production HTTP path passes, including code normalization. Browser and human acceptance remain `NOT_VERIFIED`. |
| REE | `AUTOMATED_PASS` | Representative production HTTP path passes, including code normalization. Browser and human acceptance remain `NOT_VERIFIED`. |
| ARCHITECTURE | `AUTOMATED_PASS` | Representative production HTTP path passes, including code normalization. Browser and human acceptance remain `NOT_VERIFIED`. |
| PSYCHOMETRICIAN | `AUTOMATED_PASS` | Representative production HTTP path passes, including code normalization. Browser and human acceptance remain `NOT_VERIFIED`. |
| CPA | `AUTOMATED_PASS` | Representative production HTTP path passes. Browser and human acceptance remain `NOT_VERIFIED`. |

The machine-readable `board_assessment` records this automated evidence board
by board. `IMPLEMENTED_AND_VERIFIED` means the reduced-fixture HTTP journey
passed; it does not imply full content coverage, visual acceptance, or official
exam-format fidelity.

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
private JSON by default or mutable-state SQLite when configured. The privacy
page shows counts and offers JSON export plus confirmed, CSRF-protected deletion
for this session's attempts, weakness summaries, and flashcard review state; it
also clears and rotates that browser session. It cannot erase PHP/host access or
error logs, off-host backups, or copies outside these collections. Clearing
cookies can still make retained records inaccessible; there is no login
recovery or cross-device continuity.

`/privacy` explains the anonymous state model and conditionally displays
owner-supplied terms. Until `PRIVACY_OPERATOR_NAME`,
`PRIVACY_OPERATOR_CONTACT`, `PRIVACY_RETENTION_NOTICE`,
`PRIVACY_HOSTING_NOTICE`, and `PRIVACY_TERMS_APPROVED=true` are supplied and
reviewed, it displays a launch-blocking warning. The application does not invent
legal or host-specific facts.

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
| `BP663-DEF-001` | P1 | Seven short board codes did not normalize to their canonical eligibility IDs. | Fixed and covered by all-board production HTTP journeys. | No |
| `BP663-DEF-002` | P1 | Board subject actions and quiz settings were disconnected from effective eligible subjects. | Fixed: both detail links and selector are generated from runtime effective subject IDs. | No |

`BP662-CAP-001` remains the blocking owner-approved privacy-terms capability.
`BP663-CAP-001` records the now-implemented learner-scoped export/deletion
controls as closed; those controls do not erase external logs or backups.
Cross-device accounts (`CAP-002`) and full timed official-format simulators for
every board (`CAP-003`) remain intentionally deferred. Production authoring
(`CAP-004`) remains deferred because curated content is release-managed.

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
- Owner-approve actual privacy terms and reconcile the application's deletion
  scope with host-controlled access/error logs and off-host backups.

### Should fix before launch

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
