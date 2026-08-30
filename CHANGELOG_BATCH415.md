# Batch 415 — Question Statistics & Answer Tracking Reliability

- Wire question statistics into the completed-result persistence path.
- Record statistics once per persisted quiz attempt instead of per submit click.
- Normalize malformed legacy counters before arithmetic.
- Count unanswered questions as used without misclassifying them as incorrect.
- Keep missing/deleted questions non-fatal at the statistics boundary.
