# Batch 406 — Quiz Start Orchestration

Decomposes the quiz-start HTTP flow into focused production collaborators while preserving `QuizStartService::start()` as the controller-facing API.

The milestone separates request normalization, specification construction, generation preparation, question-ID extraction, session payload creation, session persistence, and view-model creation. The start service remains responsible only for repository access, empty-result redirect, navigation reset, and rendering.

Validation covers each collaborator, the real generation path, the production orchestration contract, the full quiz regression, and Doctor.
