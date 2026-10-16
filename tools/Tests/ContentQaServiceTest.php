<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/Core/Autoloader.php';

\App\Core\Autoloader::register();

use App\Services\Shared\ContentQaService;

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
];
$collections = [
    'questions' => $questions,
    'study-materials' => [['id' => 'source-less', 'title' => 'No source fixture']],
    'study-question-links' => [['id' => 'link-source', 'question_id' => 'q-source', 'material_id' => 'source-less']],
    'sources' => [],
    'content-fit-audits' => [['question_id' => 'q-one', 'fit_classification' => 'WEAK_BUT_DEFENSIBLE', 'rationale' => 'Fixture review evidence']],
];
$integrity = ['boards' => ['alpha' => ['defects' => ['unlinked_questions' => ['q-bad']]]], 'failures' => ['invalid_answers' => ['q-bad']]];
$report = ContentQaService::analyze($collections, $integrity);
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

echo "Content QA service test passed\n";
