# Batch 437 — Exam Simulation and Blueprint Realism

Exam mode now creates an isolated simulation session and applies the current
LET secondary subject allocation stored in board-subject metadata. Requested
and generated counts, blueprint shortages, unanswered scoring, retry behavior,
stale-session recovery, and exact-once attempt persistence are covered through
the shared quiz engine and realistic HTTP journeys.

Sparse pools now recover every eligible unique question without allowing
malformed content back into selection. Allocation uses deterministic normalized
largest remainders. No difficulty policy was inferred from the conflicting
legacy English blueprint files; mixed difficulty remains in effect until a
canonical active subject blueprint is published.
