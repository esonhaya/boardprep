# TB-017 — Storage Persistence Integrity

## Purpose

BoardPrep's JSON backend is the canonical local-development persistence layer for attempts, learner weakness state, questions, and repository collections. Taxonomy remains JSON-backed by design and now uses the same JSON storage write semantics instead of direct file replacement. Persistence operations must never expose a partially rewritten collection or silently retain stale records when a caller intends to replace a complete state set.

## Storage contract

`StorageInterface` supports record CRUD plus whole-collection replacement. Whole-collection replacement is appropriate when the caller owns the complete canonical state, such as learner weakness statistics or authored taxonomy collections.

JSON mutations are serialized by a storage-level mutation lock. Each write is encoded to a temporary file and then renamed over the canonical collection. A failed encode or replacement therefore leaves the previous collection intact.

Primary keys are immutable during update. Creation and replacement require non-empty string or integer primary keys, and collection replacement rejects duplicate IDs before modifying canonical data.

## Caller responsibilities

Repositories should use ordinary CRUD when changing individual records. Services that own a full state projection should use `replace()` rather than delete-and-recreate loops. Readers must still tolerate malformed legacy individual rows where their production contract explicitly allows recovery, but malformed collection JSON is a storage failure and must not be treated as an empty collection.

## Backend parity

The MySQL adapter implements the same generic replacement contract transactionally for database-backed repositories. SQLite now provides the same repository seam for mutable `attempts` and `weakness` state; a storage router keeps curated questions, study material, and taxonomy on their established JSON-backed path. SQLite migrations are numbered and recorded in `schema_migrations`, and `php tools/db-migrate.php` imports legacy mutable records idempotently without deleting their JSON source. Test storage mirrors the generic contract so service and repository tests exercise equivalent replacement semantics.

## Runtime artifacts

The JSON backend may create `storage/.storage.lock` while coordinating mutations. It is runtime state and is ignored by Git.
