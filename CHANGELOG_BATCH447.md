# Batch 447 — Security and Trust Boundary Hardening

- Enforced explicit quiz method/action boundaries: GET configures or reads; POST starts, submits, advances, or finishes.
- Changed result completion to POST followed by a 303 redirect to a cached, read-only result, retaining exact-once attempt persistence.
- Rejected malformed persisted quiz questions before templates receive associative or oversized choices, non-scalar explanations, or invalid answers.
- Returned HTTP 405 for registered paths called with unsupported methods and added production-path input and HTTP regressions.
