# Batch 411

- Route question editor create/update through the production authoring service.
- Block duplicate questions before create/update persistence.
- Centralize authoring save decisions and persistence dispatch.
- Preserve validation and duplicate feedback in the existing editor form.
- Ignore malformed repository entries during duplicate scanning.
- Add production-path regression coverage for create, update, duplicate blocking, and self-update behavior.
- Generate a valid question primary key before validating a new authoring record.
- Preserve requested/existing IDs on edit while keeping creation timestamps stable.
