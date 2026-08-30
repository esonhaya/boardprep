# Batch 414 — Attempt Persistence & Learning Context Reliability

Completed attempts now preserve the quiz context needed by learning-history and recommendation consumers. When a session lacks explicit taxonomy, persistence derives missing context from canonical question `taxonomy.*_id` fields while retaining compatibility with legacy top-level question fields. Existing attempt/session values remain authoritative, all discovered topics are retained, and the legacy primary `topic` remains deterministic.
