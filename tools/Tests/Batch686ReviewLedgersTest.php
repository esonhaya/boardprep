<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/Core/Autoloader.php';
\App\Core\Autoloader::register();

use App\Services\Question\NumericalAnswerConsistencyAudit;
use App\Services\Shared\AcademicSourceReviewService;
use App\Services\Shared\CognitiveIntentReviewService;

$root = dirname(__DIR__, 2);
$read = static fn(string $path): array => json_decode((string) file_get_contents($root . '/' . $path), true, 512, JSON_THROW_ON_ERROR);
$assert = static function (bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
};
$questions = $read('storage/questions.json');
$sources = $read('storage/sources.json');
$materials = $read('storage/study-materials.json');
$links = $read('storage/study-question-links.json');
$sourceRows = $read('storage/academic-source-reviews.json');
$decisions = $read('storage/academic-review-decisions.json');
$questionIds = array_map(static fn(array $question): string => (string) ($question['id'] ?? ''), $questions);
$assert(count($questionIds) >= 8962 && count(array_unique($questionIds)) === count($questionIds), 'Canonical question identities changed during Batch 686.');
$assert(array_diff(array_map('strval', range(1, 8962)), $questionIds) === [], 'A pre-expansion canonical question identity is missing.');

$sourceSummary = AcademicSourceReviewService::summarize($questions, $sources, $sourceRows, $links, $materials);
$assert(($sourceSummary['reviewed_questions'] ?? 0) === 100, 'The source sample is not 100 distinct questions.');
$assert(($sourceSummary['classifications'] ?? []) === ['DIRECT_SUPPORT' => 39, 'PARTIAL' => 33, 'CONTRADICTED' => 0, 'UNKNOWN' => 28], 'Source evidence classes or honest unknowns changed after the Batch 687 recheck.');
$assert(($sourceSummary['human_confirmed_rows'] ?? -1) === 0 && ($sourceSummary['whole_corpus_verified_count'] ?? -1) === 0, 'A bounded AI sample was promoted to human or whole-corpus verification.');
$assert(($sourceSummary['invalid_review_rows'] ?? []) === [], 'A source review row has stale identity, invalid provenance, or broken structural linkage.');
$assert(count($sourceSummary['by_board'] ?? []) === 10, 'Source sampling omitted one or more registered boards.');
foreach ($sourceSummary['by_board'] as $board => $counts) {
    $assert(($counts['reviewed_questions'] ?? 0) === 10, 'Source sample is not board-balanced at ' . $board . '.');
}
foreach ($sourceRows as $row) {
    $assert(($row['review_state'] ?? '') === 'AI_REVIEWED_PENDING_HUMAN_CONFIRMATION', 'Source row lost pending human state.');
}

$cognitiveRows = $read('storage/cognitive-intent-review-samples.json');
$cognitive = CognitiveIntentReviewService::summarize($questions, $cognitiveRows, $decisions);
$assert(($cognitive['inspected_questions'] ?? 0) === 100, 'The cognitive-intent inspection sample is not 100 questions.');
$assert(($cognitive['label_assigned_pending_human_review'] ?? 0) === 25 && ($cognitive['review_required_no_automated_label'] ?? 0) === 75, 'Cognitive assignments and unlabelled review cases were not kept distinct.');
$assert(($cognitive['human_approved'] ?? -1) === 0 && ($cognitive['invalid_review_rows'] ?? []) === [], 'A cognitive label was approved or the sample ledger is inconsistent.');
foreach ($cognitive['by_board'] as $board => $counts) {
    $assert(($counts['inspected'] ?? 0) === 25, 'Cognitive sample is not balanced at ' . $board . '.');
}

$byId = [];
foreach ($questions as $question) $byId[(string) $question['id']] = $question;
$rationaleIds = ['622','6334','6353','5667','1387','1397','3533','4622','1589','1607'];
$numeric = NumericalAnswerConsistencyAudit::analyze(array_map(static fn(string $id): array => $byId[$id], $rationaleIds));
$findingIds = array_column($numeric['findings'], 'entity_id');
$assert(($numeric['metrics']['recomputed'] ?? 0) === count($rationaleIds), 'A rationale repair lacks a supported independent numerical recomputation.');
$assert($findingIds === [], 'A corrected question still has a numerical answer/formula disagreement.');

$newDecisionIds = [];
foreach ($decisions as $decision) {
    if (str_starts_with((string) ($decision['id'] ?? ''), 'batch686-')) {
        $newDecisionIds[] = (string) $decision['question_id'];
        $assert(($decision['review_state'] ?? '') === 'REVIEW_REQUIRED', 'Batch 686 decision was incorrectly approved.');
        $assert(($decision['correction_state'] ?? '') === 'APPLIED_PENDING_HUMAN_SIGNOFF', 'Batch 686 decision lost pending sign-off state.');
        $assert(($decision['human_signoff'] ?? '') === 'NOT_RECORDED', 'Batch 686 decision claims human sign-off.');
    }
}
$assert(count($newDecisionIds) === 35, 'Exact trace records do not cover all 25 intent and 10 rationale changes.');

$duplicate = $read('docs/batches/batch-686-duplicate-triage.json');
$summary = $duplicate['summary'] ?? [];
$assert(($summary['live_exact_findings'] ?? 0) === 85 && ($summary['live_normalized_findings'] ?? 0) === 85, 'Duplicate QA findings changed unexpectedly.');
$assert(($summary['exact_groups_matched_to_batch681'] ?? 0) === 85 && ($summary['exact_and_normalized_same_question_sets'] ?? 0) === 85, 'Exact and normalized duplicate overlap was not reconciled.');
$assert(($summary['question_ids_deleted_or_merged'] ?? -1) === 0 && ($summary['human_approved_clusters'] ?? -1) === 0, 'Duplicate identity or human-review safety contract changed.');
$assert(count($duplicate['unresolved_clone_candidates'] ?? []) === 179, 'Heuristic clone candidates were silently dropped from the review packet.');

echo '[PASS] Source/cognitive ledgers, pending decisions, duplicate clusters, and numerical corrections are structurally consistent.' . PHP_EOL;
