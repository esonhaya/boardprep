<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$assertions = 0;
$assert = static function (bool $condition, string $message) use (&$assertions): void {
    $assertions++;
    if (!$condition) throw new RuntimeException($message);
};
$json = static function (string $relative) use ($root): array {
    $decoded = json_decode((string) file_get_contents($root . '/' . $relative), true, 512, JSON_THROW_ON_ERROR);
    if (!is_array($decoded)) throw new RuntimeException('Invalid JSON artifact: ' . $relative);
    return $decoded;
};

$decisions = $json('docs/reports/batch-706-critical-mapping-decisions.json');
$assert(($decisions['summary']['nursing_exact_mappings_before'] ?? null) === 49, 'Critical mapping report has an incorrect B705 baseline.');
$assert(($decisions['summary']['nursing_exact_mappings_after'] ?? null) === 47, 'Critical mapping report has an incorrect current total.');
$assert(($decisions['summary']['exact_mappings_quarantined'] ?? null) === 2, 'Critical mapping report does not record both quarantines.');
$assert(($decisions['summary']['academic_approvals'] ?? null) === 0, 'Critical mapping report invents academic approval.');
$historicalMappings = $json('docs/reports/batch-705-nursing-mapping-audit.json');
$assert(count((array) ($historicalMappings['mappings'] ?? [])) === 49, 'Historical Batch 705 mapping evidence was rewritten or truncated.');
$decisionIds = array_map(static fn(array $row): string => (string) ($row['question_id'] ?? ''), (array) ($decisions['decisions'] ?? []));
$assert($decisionIds === ['1018', '1536', '1537'], 'Critical mapping decision IDs or stable ordering changed.');
foreach ((array) ($decisions['decisions'] ?? []) as $row) {
    $assert(($row['review_decision'] ?? '') === 'NOT_REVIEWED', 'Critical mapping item was promoted without a reviewer.');
}

$questions = $json('storage/questions.json');
$assert(hash_file('sha256', $root . '/storage/questions.json') === ($decisions['baseline']['question_sha256'] ?? null), 'Question hash in critical mapping artifact is stale.');
$questionById = [];
foreach ($questions as $question) if (is_array($question) && isset($question['id'])) $questionById[(string) $question['id']] = $question;
$assert(($questionById['1536']['answer'] ?? '') === '17 mg', 'Canonical Q1536 answer changed.');
$assert(($questionById['1537']['answer'] ?? '') === '3.6 mL', 'Canonical Q1537 answer changed.');
$assert(($questionById['1018']['answer'] ?? '') === 'ask directly about thoughts of self-harm and assess immediate safety', 'Canonical Q1018 answer changed.');

$defects = $json('docs/reports/batch-706-defect-review.json');
$cases = (array) ($defects['cases'] ?? []);
$caseIds = array_map(static fn(array $row): string => (string) ($row['question_id'] ?? ''), $cases);
$assert(count($cases) === 11 && count(array_unique($caseIds)) === 11, 'Targeted defect report must contain eleven unique reviewed records.');
$assert(($defects['canonical_changes']['question_records_changed'] ?? null) === 0, 'Defect report records an unexpected canonical question change.');
$assert(($defects['canonical_changes']['answer_keys_changed'] ?? null) === 0, 'Defect report records an unexpected answer-key change.');
foreach ($cases as $case) $assert(($case['review_decision'] ?? '') === 'NOT_REVIEWED', 'Defect case contains a fabricated reviewer decision.');

$sourceLedger = $json('docs/reports/batch-706-source-claim-ledger.json');
$sourceRows = (array) ($sourceLedger['records'] ?? []);
$assert(count($sourceRows) === 4, 'Source-to-claim ledger must cover the four selected source claims.');
$sourceIds = array_map(static fn(array $row): string => (string) ($row['source_id'] ?? ''), $sourceRows);
$assert(count(array_unique($sourceIds)) === 4 && !in_array('', $sourceIds, true), 'Source ledger has missing or duplicate source identities.');
foreach ($sourceRows as $row) {
    foreach (['section_id','concept_id','source_id','document_title','publisher','year_or_edition','access_status','claim_location','claim_summary','claim_compatibility','jurisdiction_limit','currentness_limit'] as $field) {
        $assert(trim((string) ($row[$field] ?? '')) !== '', 'Source ledger row is missing ' . $field . ': ' . ($row['source_id'] ?? 'unknown'));
    }
    $assert(($row['review_required'] ?? false) === true, 'Source claim was promoted beyond review-required status.');
}

$queueText = (string) file_get_contents($root . '/docs/reports/batch-705-academic-review-queue.md');
$queueIds = $queuePriorities = [];
foreach (preg_split('/\R/', $queueText) ?: [] as $line) {
    if (!str_starts_with($line, '|') || str_starts_with($line, '| ITEM') || str_starts_with($line, '|---')) continue;
    $parts = array_map('trim', explode('|', trim($line, '|')));
    if (count($parts) < 10) continue;
    $queueIds[] = $parts[0];
    $queuePriorities[] = $parts[7];
    $assert($parts[9] === 'NOT_REVIEWED', 'Historical reviewer queue contains a completed/forged decision for ' . $parts[0]);
    $assert(trim($parts[10]) === '' && trim($parts[11]) === '', 'Historical queue has fabricated feedback or approval evidence for ' . $parts[0]);
}
$assert(count($queueIds) === 106 && count(array_unique($queueIds)) === 106, 'Existing Batch 705 reviewer queue lost unique stable item IDs.');
$assert(count(array_filter($queuePriorities, static fn(string $priority): bool => $priority === 'P0')) === 74, 'Existing Batch 705 P0 queue count changed unexpectedly.');

$packet = (string) file_get_contents($root . '/docs/reports/batch-706-academic-review-packet.md');
foreach (['ACCEPT', 'ACCEPT_WITH_CORRECTION', 'CHANGES_REQUIRED', 'REJECT', 'INSUFFICIENT_EVIDENCE', 'NOT_REVIEWED', 'Q1536', 'Q1537', 'Q1018'] as $requiredText) {
    $assert(str_contains($packet, $requiredText), 'Reviewer packet omits required decision/action text: ' . $requiredText);
}
$assert(str_contains($packet, '74 existing P0 queue records') && str_contains($packet, '106-record queue'), 'Reviewer packet does not point reviewers to the existing backlog.');

$readiness = $json('docs/reports/batch-706-release-readiness.json');
$matrix = (array) ($readiness['matrix'] ?? []);
$gateNames = array_map(static fn(array $row): string => (string) ($row['gate'] ?? ''), $matrix);
$assert(count($matrix) === 12 && count(array_unique($gateNames)) === 12, 'Release readiness matrix must contain twelve unique gates.');
$allowedStatuses = ['PASS','FAIL','BLOCKED','REVIEW_REQUIRED','NOT_RUN','NOT_APPLICABLE'];
foreach ($matrix as $row) {
    $assert(in_array((string) ($row['status'] ?? ''), $allowedStatuses, true), 'Readiness gate has an invalid status: ' . ($row['gate'] ?? 'unknown'));
    $assert(trim((string) ($row['evidence'] ?? '')) !== '' && trim((string) ($row['limitations'] ?? '')) !== '', 'Readiness gate lacks evidence or limitations: ' . ($row['gate'] ?? 'unknown'));
}
$gateByName = [];
foreach ($matrix as $row) $gateByName[(string) ($row['gate'] ?? '')] = $row;
$assert(($gateByName['Full Doctor']['status'] ?? '') === 'BLOCKED', 'Full Doctor storage blocker was incorrectly promoted.');
$assert(($gateByName['Browser acceptance']['status'] ?? '') === 'BLOCKED', 'Unsupported Termux browser acceptance was incorrectly promoted.');
$assert(($readiness['academic_approval'] ?? '') === 'NOT_GRANTED' && ($readiness['public_launch'] ?? '') === 'NOT_APPROVED', 'Readiness report implies academic or launch approval.');

$browserChecklist = (string) file_get_contents($root . '/docs/reports/batch-706-browser-acceptance-checklist.md');
foreach (['Q1018','Q1536','Q1537','Empty practice','Stale anchor','Cross-board rejection','360-pixel-wide viewport','Execution status:** `BLOCKED`'] as $requiredText) {
    $assert(str_contains($browserChecklist, $requiredText), 'External browser checklist omits required scenario or limitation: ' . $requiredText);
}

$pdfManifest = $json('public/study-resources/manifest.json');
$pdfRows = (array) ($pdfManifest['files'] ?? []);
$pdfPaths = [];
$assert(count($pdfRows) === 59, 'PDF manifest entry count changed.');
foreach ($pdfRows as $row) {
    $relativePath = (string) ($row['path'] ?? '');
    $pdfPaths[] = $relativePath;
    $absolutePath = $root . '/' . $relativePath;
    $assert(is_file($absolutePath) && hash_equals((string) ($row['sha256'] ?? ''), (string) hash_file('sha256', $absolutePath)), 'PDF manifest hash mismatch: ' . $relativePath);
}
$assert(count(array_unique($pdfPaths)) === 59, 'PDF manifest contains duplicate paths.');

$validation = $json('docs/reports/batch-706-validation-evidence.json');
$executionRecords = (array) ($validation['execution_records'] ?? []);
$executionById = [];
foreach ($executionRecords as $row) $executionById[(string) ($row['id'] ?? '')] = $row;
$assert(($executionById['Batch706CriticalMappingSafetyTest']['status'] ?? '') === 'NEWLY_TESTED_PASS', 'Final focused mapping test is missing from validation evidence.');
$assert(($executionById['Batch706AuditArtifactsTest']['status'] ?? '') === 'NEWLY_TESTED_PASS', 'Audit artifact test is missing from validation evidence.');
$assert(($executionById['FullDoctorReleaseGate']['status'] ?? '') === 'BLOCKED', 'Full Doctor storage gate was promoted or omitted.');
$assert(($executionById['ExternalBrowserAcceptance']['status'] ?? '') === 'BLOCKED', 'External browser limitation was promoted or omitted.');
$assert(isset($executionById['Batch703NursingExpansionTest_storage_exhaustion_attempt']), 'Storage-limited test attempt is missing from the evidence record.');
$assert(($validation['final_boundary_summary']['canonical_questions_changed'] ?? true) === false, 'Validation evidence records an unexpected canonical question change.');

$finalReport = (string) file_get_contents($root . '/docs/reports/batch-706-nursing-safety.md');
foreach (['BOARDPREP — BATCH 706','HEAD_BEFORE=2a679c8ca04d59bdae1d7828eb5368e099f95c79','Q1536_DISPOSITION=','Q1537_DISPOSITION=','FULL_DOCTOR=BLOCKED','ACADEMIC_READINESS=REVIEW_REQUIRED','PUSH_STATUS=NOT_PUSHED','CLIPBOARD_STATUS=UNVERIFIED'] as $requiredText) {
    $assert(str_contains($finalReport, $requiredText), 'Final report omits a required status or baseline: ' . $requiredText);
}
$assert(!str_contains($finalReport, 'to be finalized') && !str_contains($finalReport, 'to be verified'), 'Final report contains an unfinished delivery placeholder.');

echo "Batch706AuditArtifactsTest PASS ($assertions assertions; 106 unique existing review items; 74 P0; 11 targeted defects; 4 source claims; 12 release gates; 59 verified PDF hashes; final report and validation limitations preserved)\n";
