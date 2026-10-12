# Batch 669 Nursing ID reconciliation

## Decision

The Batch 625 Nursing records are retained in the Batch 668 canonical bank;
they are not re-imported from `main`. Every one of the 320 main Nursing rows
with IDs 4163–4482 matches an existing Nursing row with IDs 4873–5192 by
normalized stem, answer, explanation, source, eligibility, study links,
materials, and reuse metadata. Their compared mutable counters and timestamps
also match. The additional main-only file `storage/batch625-nursing-modernization.json`
is retained as provenance.

The same numeric IDs in Batch 668 belong to distinct questions: 151 CPA and 169
Criminologist. All 320 such records remain unchanged. The complete per-record
fingerprints, answer/source/eligibility data, references, and commit provenance
are in `BATCH669_NURSING_ID_RECONCILIATION.json`.

## Compatibility mapping

Older Nursing quiz sessions may still contain the pre-reconciliation numeric
IDs. `QuestionIdMigrationService` applies the versioned, Nursing-only mapping
4163–4482 → 4873–5192 (+710) when validating session questions, displaying
current or historical reviews, recording answer statistics, and creating
missed-question flashcards. Saved attempt snapshots are not rewritten. CPA and
Criminologist IDs are never remapped.

The legacy `question-statistics.json` collection is keyed by question ID and
has no board provenance. Existing non-empty rows in the colliding range cannot
be safely attributed or split; do not bulk-migrate them. The inspected fixture
was empty. New Nursing answer statistics use canonical IDs.

## Merge decisions

The six conflict paths were resolved from Batch 668 because it already
contained the matched Nursing records and their references, alongside the
distinct CPA/Criminologist questions. This preserves Batch 668's LET variant,
CSE level, and wildcard-subject selection safeguards; the main-side filter
version would have removed those constraints. The non-conflicting
`QuestionSelectionService.php` result likewise retains Batch 668's variant and
level propagation.

## Reproduction

```sh
temporary_report="$(mktemp)"
php tools/batch669-nursing-reconciliation.php > "$temporary_report"
cmp docs/BATCH669_NURSING_ID_RECONCILIATION.json "$temporary_report"
rm -f -- "$temporary_report"
php tools/Tests/Batch669NursingIntegrationTest.php
```

The test includes a real cross-board ID collision fixture and asserts that
Doctor's existing canonical-ID integrity check rejects the duplicate. The
historical Batch 625 authoring/audit script refuses to run when this
reconciliation artifact is present, preventing a second import into the
integrated bank.
