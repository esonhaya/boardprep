# Batch 412 — Question Builder / Taxonomy Persistence Reliability

- Preserve scoped workspace taxonomy values through form submission.
- Add board selection and board-to-subject cascading for unscoped authoring.
- Use canonical `*_id` taxonomy relationships in the browser selector.
- Resolve taxonomy ancestry from the most specific selected child before persistence.
- Prevent cross-hierarchy taxonomy combinations from being saved.
- Preserve the existing correct answer during partial option-text updates.
- Harden builder inputs against non-scalar values.
