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

echo "Batch706AuditArtifactsTest PASS ($assertions assertions; 106 unique existing review items; 74 P0; 11 targeted defects; 4 source claims; no approval promotion)\n";
