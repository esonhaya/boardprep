# Batch 449 — Deployment and Production Readiness

BoardPrep retains its existing platform-neutral PHP front controller and JSON
or MySQL storage choices. The production contract now validates the environment,
forbids debug rendering in production, logs uncaught failures through PHP's
configured error log, and keeps developer and Doctor HTTP surfaces out of the
production route table.

The public document root, rewrite behavior, writable-storage contract, runtime
requirements, failure behavior, and a production-style built-in HTTP smoke path
are documented in the repository README. `public/router.php` supplies clean URL
and static-file behavior for that smoke path without selecting a hosting stack.

## Batch 460 bounded operational audit

The release-candidate audit inspected only startup/bootstrap, environment and
database configuration, learner routes, PHP session startup, JSON storage,
exception handling, production route exposure, deployment instructions, and
readiness entry points. One concrete simulator/runtime defect was found and
fixed: session cookie attributes were being applied while the isolated HTTP
simulator had cookies disabled, producing HTTP 500 responses. Production
cookie-backed sessions retain the secure settings.

| Area | Outcome | Evidence |
|---|---|---|
| Bootstrap/configuration | PASS | `public/index.php`, `bootstrap/app.php`, `app/Core/App.php`, `config/app.php` |
| Learner entry/session behavior | FIXED | `routes/web.php`, `SessionConfiguration`, session regression and canonical simulation |
| Runtime storage | PASS | `Database` validates readable/writable JSON storage; `JsonStorage` uses locked atomic writes |
| Production error/debug behavior | PASS | `ExceptionHandler` logs details and renders generic production errors; production rejects debug |
| Developer/Doctor exposure | PASS | Developer routes are not registered and Doctor API returns 404 in production |
| Deployment/readiness instructions | PASS | `README.md` documents public root, rewrite, storage, smoke path, and independent release gates |

This audit does not replace human acceptance. All 24 cases remain `NOT_RUN`
until a real tester supplies browser/mobile evidence. Two legacy focused HTTP
tests remain unavailable against the current 10-question fixture because their
requested start shapes do not produce a session; canonical lifecycle and
learner-journey checks pass.
