# Batch 409 — Blueprint Creation Reliability

BoardPrep previously had two independently implemented `BlueprintService` classes. The developer blueprint UI used `App\Services\Blueprint\BlueprintService`, while legacy/runtime tests used `App\Services\BlueprintService`, allowing behavior to drift while tests remained green.

Batch 409 establishes the namespaced Blueprint service as the canonical production path. The legacy service is retained only as a compatibility facade. Creation now normalizes input, validates a canonical record before repository version lookup, resolves the next version through `BlueprintRepository::versions()`, builds one record shape, and persists it.

The milestone preserves the public `all()` and `create()` APIs while aligning UI and legacy callers around the same validator, ID format, status/scope fields, and version semantics.
