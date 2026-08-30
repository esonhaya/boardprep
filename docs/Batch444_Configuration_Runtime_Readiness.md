# Batch 444 — Configuration and Runtime Readiness

Application startup now loads one environment source with process-level
precedence, validates timezone and storage driver configuration, prepares the
JSON runtime directory, and constructs the application singleton atomically.

Local secrets were removed from tracked configuration in favor of
`.env.example`. MySQL remains optional and reports a clear configuration error
when its PHP extension is unavailable.
