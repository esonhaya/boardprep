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
