# Batch 416 — Quiz Result Attempt Integrity

The result-attempt factory previously copied session and scoring fields directly into persistence. Malformed or stale session metadata could produce invalid ids, contradictory question counts, malformed question-id lists, or impossible score percentages. Batch 416 makes the persistence boundary deterministic and safe.
