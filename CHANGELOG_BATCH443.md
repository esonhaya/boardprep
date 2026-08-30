# Batch 443 — Persistence + Storage Integrity

- Added whole-collection replacement to the shared storage contract.
- Serialized JSON mutations and retained atomic temp-file replacement.
- Rejected invalid/duplicate primary keys and primary-key mutation during update.
- Moved weakness persistence from delete/recreate loops to one collection replacement.
- Routed taxonomy persistence through the configured storage backend instead of hard-coded file access.
- Added backend, repository, learner-weakness, and taxonomy persistence regression coverage.
