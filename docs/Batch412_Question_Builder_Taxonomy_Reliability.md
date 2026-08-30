# Batch 412 — Question Builder / Taxonomy Persistence Reliability

The question workspace previously hid selectors supplied by URL context without submitting those values, while the normal browser cascade filtered legacy relationship keys that do not exist in current taxonomy storage. The builder also could clear the current correct answer when only option text was partially updated.

Batch 412 makes the authoring contract canonical end-to-end. The form submits scoped context, unscoped authoring exposes board selection, the browser cascade uses `board_id`, `subject_id`, `domain_id`, and `topic_id`, and the backend resolves the full hierarchy from the most specific child. This prevents missing or cross-hierarchy taxonomy persistence. Option rebuilding now preserves the stored correct answer unless a new correct option is explicitly submitted.
