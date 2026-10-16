<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/Core/Autoloader.php';

\App\Core\Autoloader::register();

use App\Services\Shared\ContentQaService;
use App\Services\Shared\AcademicSourceReviewService;

$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};
$question = static function (string $id, string $stem, array $overrides = []): array {
    return $overrides + [
        'id' => $id,
        'question' => $stem,
        'choices' => ['A', 'B'],
        'answer' => 'A',
        'explanation' => 'The provided evidence supports the selected answer.',
        'taxonomy' => ['board_id' => 'alpha', 'subject_id' => 'math', 'domain_id' => 'd', 'topic_id' => 't', 'concept_id' => 'c'],
    ];
};
$questions = [
    $question('q-bad', 'How should this malformed fixture be repaired?', ['answer' => 'Z', 'choices' => ['A', 'A'], 'explanation' => '']),
    $question('q-one', 'A delivery of 12 crates costs 45 pesos. What is the unit cost?'),
    $question('q-two', 'A delivery of 18 crates costs 45 pesos. What is the unit cost?'),
    $question('q-three', 'A delivery of 24 crates costs 45 pesos. What is the unit cost?'),
    $question('q-normalized-a', 'Which reported monthly total is correct for this ledger?'),
    $question('q-normalized-b', 'which reported monthly total is correct for this ledger!'),
    $question('q-source', 'Which source-backed conclusion follows from this record?'),
    $question('q-no-source', 'Which conclusion has no linked source evidence in this fixture?'),
];
$collections = [
    'questions' => $questions,
    'study-materials' => [
        ['id' => 'source-less', 'title' => 'No source fixture'],
        ['id' => 'review-material', 'title' => 'Reviewed lesson', 'source_ids' => ['fixture-source']],
    ],
    'study-question-links' => [
        ['id' => 'link-source', 'question_id' => 'q-no-source', 'material_id' => 'source-less'],
        ['id' => 'link-review', 'question_id' => 'q-source', 'material_id' => 'review-material'],
        ['id' => 'link-review-invalid', 'question_id' => 'q-one', 'material_id' => 'review-material'],
    ],
    'sources' => [['id' => 'fixture-source', 'title' => 'Fixture primary source', 'url' => 'https://example.invalid/source']],
    'academic-source-reviews' => [[
        'question_id' => 'q-source', 'board_id' => 'alpha', 'concept_id' => 'c',
        'material_id' => 'review-material', 'source_id' => 'fixture-source',
        'question_snapshot_sha256' => AcademicSourceReviewService::questionFingerprint($question('q-source', 'Which source-backed conclusion follows from this record?')),
        'source_snapshot_sha256' => AcademicSourceReviewService::sourceFingerprint(['id' => 'fixture-source', 'title' => 'Fixture primary source', 'url' => 'https://example.invalid/source']),
        'source_locator' => 'Section 2, page 1', 'classification' => 'PARTIAL',
        'reviewer_type' => 'AI_ASSISTED_EDITORIAL_REVIEW',
        'review_state' => 'AI_REVIEWED_PENDING_HUMAN_CONFIRMATION',
        'evidence_note' => 'The cited section explains one part of the tested distinction.',
        'limitations' => 'It does not establish every condition in the item.',
    ], [
        'question_id' => 'q-one', 'board_id' => 'alpha', 'concept_id' => 'c',
        'material_id' => 'review-material', 'source_id' => 'fixture-source',
        'source_locator' => 'Section 2, page 1', 'classification' => 'DIRECT_SUPPORT',
        'reviewer_type' => 'AI_ASSISTED_EDITORIAL_REVIEW',
        'review_state' => 'HUMAN_APPROVED',
        'evidence_note' => 'A deliberately invalid decision state.',
        'limitations' => 'A test fixture.',
    ]],
    'content-fit-audits' => [['question_id' => 'q-one', 'fit_classification' => 'WEAK_BUT_DEFENSIBLE', 'rationale' => 'Fixture review evidence']],
    'academic-review-decisions' => [['id' => 'decision-q-source', 'question_id' => 'q-source', 'review_state' => 'REVIEW_REQUIRED', 'correction_state' => 'APPLIED_PENDING_HUMAN_SIGNOFF']],
];
$integrity = ['boards' => ['alpha' => ['defects' => ['unlinked_questions' => ['q-bad']]]], 'failures' => ['invalid_answers' => ['q-bad']]];
$report = ContentQaService::analyze($collections, $integrity);
$sourceQuestionIndex = array_search('q-source', array_column($collections['questions'], 'id'), true);
$assert(is_int($sourceQuestionIndex), 'Source fingerprint fixture question was not found.');
foreach (['explanation', 'choices'] as $fingerprintedField) {
    $staleCollections = $collections;
    if ($fingerprintedField === 'explanation') {
        $staleCollections['questions'][$sourceQuestionIndex]['explanation'] .= ' Edited after review.';
    } else {
        $staleCollections['questions'][$sourceQuestionIndex]['choices'][0] = 'Changed choice';
    }
    $staleReport = ContentQaService::analyze($staleCollections, $integrity);
    $staleReasons = array_column($staleReport['semantic_source_review']['invalid_review_rows'] ?? [], 'reason');
    $assert(in_array('STALE_QUESTION_SNAPSHOT', $staleReasons, true), 'A changed question ' . $fingerprintedField . ' did not invalidate semantic source evidence.');
}
$codes = array_column($report['issues'], 'issue_code');
foreach (['INVALID_ANSWER_KEY', 'MISSING_STUDY_LINK', 'MISSING_RATIONALE', 'DUPLICATE_CHOICES', 'MISSING_SOURCE', 'MISSING_COGNITIVE_INTENT', 'NORMALIZED_DUPLICATE', 'NEAR_DUPLICATE_RISK', 'NUMBER_SWAP_CLONE_RISK', 'SOURCE_TOPIC_FIT_WARNING'] as $code) {
    $assert(in_array($code, $codes, true), 'QA did not expose ' . $code);
}
$byCode = [];
foreach ($report['issues'] as $issue) { $byCode[$issue['issue_code']][] = $issue; }
$assert(($byCode['INVALID_ANSWER_KEY'][0]['severity'] ?? '') === 'ERROR', 'invalid answer was not deterministic error');
$assert(($byCode['NUMBER_SWAP_CLONE_RISK'][0]['detection_type'] ?? '') === 'HEURISTIC', 'number swap risk was not heuristic');
$assert(($byCode['SOURCE_TOPIC_FIT_WARNING'][0]['severity'] ?? '') === 'REVIEW', 'fit audit was not a review item');
$assert(($byCode['MISSING_COGNITIVE_INTENT'][0]['severity'] ?? '') === 'REVIEW'
    && str_contains((string) ($byCode['MISSING_COGNITIVE_INTENT'][0]['suggested_action'] ?? ''), 'academic reviewer'), 'missing cognitive intent did not request academic review.');
$filtered = ContentQaService::analyze($collections, $integrity, ['question_ids' => ['q-source']]);
$filteredFromFullScan = ContentQaService::filterReport($report, ['question_ids' => ['q-source']]);
$assert($filtered['issues'] !== [], 'incremental ID filter removed matching issue');
$assert($filtered['summary'] === $filteredFromFullScan['summary'], 'Filtering a completed QA scan changed its issue summary.');
foreach ($filtered['issues'] as $issue) {
    $assert(in_array('q-source', $issue['question_ids'], true), 'incremental ID filter leaked unrelated issue');
}
$boardFiltered = ContentQaService::analyze($collections, $integrity, ['board_id' => 'alpha']);
$assert($boardFiltered['summary']['total'] === $report['summary']['total'], 'board filter did not retain matching board issues');
$assert(isset($report['actionable_fix_queue']) && $report['runtime']['n_plus_one'] === false, 'QA action queue/runtime contract missing');
$assert(($report['review_decision_summary']['review_required'] ?? 0) === 1
    && ($report['review_decision_summary']['corrections_applied_pending_human_signoff'] ?? 0) === 1,
    'Editorial correction ledger hid outstanding human sign-off.');
$sourceSummary = $report['semantic_source_review'] ?? [];
$assert(($sourceSummary['reviewed_questions'] ?? -1) === 1
    && ($sourceSummary['structurally_linked_review_rows'] ?? -1) === 1
    && ($sourceSummary['classifications']['PARTIAL'] ?? 0) === 1
    && ($sourceSummary['whole_corpus_verified_count'] ?? -1) === 0
    && ($sourceSummary['human_confirmed_rows'] ?? -1) === 0,
    'Semantic source support was conflated with structural linkage or human approval.');
$assert(count($sourceSummary['invalid_review_rows'] ?? []) === 1
    && ($sourceSummary['invalid_review_rows'][0]['reason'] ?? '') === 'INVALID_REVIEW_STATE',
    'An unsubstantiated human-approval state was accepted into the source-review totals.');
$staleCollections = $collections;
$staleCollections['questions'][6]['answer'] = 'Changed after source review';
$staleSummary = ContentQaService::analyze($staleCollections, $integrity)['semantic_source_review'] ?? [];
$assert(($staleSummary['reviewed_questions'] ?? -1) === 0
    && ($staleSummary['invalid_review_rows'][0]['reason'] ?? '') === 'STALE_QUESTION_SNAPSHOT',
    'A semantic source review remained current after the question answer changed.');
$staleSourceCollections = $collections;
$staleSourceCollections['sources'][0]['url'] = 'https://example.invalid/changed-source';
$staleSourceSummary = ContentQaService::analyze($staleSourceCollections, $integrity)['semantic_source_review'] ?? [];
$assert(($staleSourceSummary['reviewed_questions'] ?? -1) === 0
    && ($staleSourceSummary['invalid_review_rows'][0]['reason'] ?? '') === 'STALE_SOURCE_SNAPSHOT',
    'A semantic source review remained current after the registered source identity changed.');
$clusterByCode = [];
foreach ($report['issues'] as $issue) if (isset($issue['cluster_id'])) $clusterByCode[$issue['issue_code']][] = $issue['cluster_id'];
$assert(!empty($clusterByCode['NORMALIZED_DUPLICATE'][0]), 'A normalized duplicate cluster lacks a stable ID.');
$assert(!empty($clusterByCode['NEAR_DUPLICATE_RISK'][0])
    && ($clusterByCode['NEAR_DUPLICATE_RISK'][0] ?? '') === ($clusterByCode['NUMBER_SWAP_CLONE_RISK'][0] ?? ''),
    'Overlapping near-clone findings do not share a stable candidate-cluster ID.');
$reversedQuestions = array_reverse($questions);
$reversed = ContentQaService::analyze(array_merge($collections, ['questions' => $reversedQuestions]), $integrity);
$reversedClusters = [];
foreach ($reversed['issues'] as $issue) if (($issue['issue_code'] ?? '') === 'NORMALIZED_DUPLICATE') $reversedClusters[] = $issue['cluster_id'] ?? '';
$assert($reversedClusters === $clusterByCode['NORMALIZED_DUPLICATE'], 'Duplicate-cluster identity changes when record order changes.');
$conceptFiltered = ContentQaService::filterReport($report, ['concept_id' => 'c']);
$assert($conceptFiltered['issues'] !== [] && count($conceptFiltered['issues']) === count($report['issues']), 'Concept filter removed matching taxonomy findings.');
$pageOne = ContentQaService::paginateReport($report, 1, 2);
$pageTwo = ContentQaService::paginateReport($report, 2, 2);
$assert(count($pageOne['issues']) === 2 && count($pageTwo['issues']) === 2, 'QA issue pagination did not bound each page.');
$assert(($pageOne['pagination']['total'] ?? 0) === ($report['summary']['total'] ?? -1)
    && ($pageTwo['pagination']['first'] ?? 0) === 3,
    'QA pagination changed the full filtered summary or page offsets.');
$clamped = ContentQaService::paginateReport($report, 9999, 1000);
$assert(($clamped['pagination']['per_page'] ?? 0) === 100 && ($clamped['pagination']['page'] ?? 0) === ($clamped['pagination']['pages'] ?? -1), 'QA pagination failed to clamp page size or page number.');

echo "Content QA service test passed\n";
