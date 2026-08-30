# Batch 411 — Question Authoring Integrity

The production question editor previously called `QuestionService::validateForSave()` directly and only blocked validation errors. Duplicate matches returned by that same validation result were displayed but were not a persistence gate, so the real editor could save duplicate questions even though `QuestionAuthoringService` already defined duplicates as unsavable.

This milestone routes create/update through one authoring submission path, centralizes the save decision, persists only accepted submissions, preserves duplicate feedback for the existing form, and hardens duplicate scanning against malformed repository records. Existing `prepare`, `canSave`, and `save` APIs remain available.

A second production-path defect was exposed while exercising create: new questions were built with a null ID, while both question validation and JSON storage require a primary key. New authoring records now receive an ID during metadata construction, before validation and duplicate detection, while edits preserve their existing ID.
