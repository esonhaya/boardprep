<?php

declare(strict_types=1);

require_once __DIR__ . '/../../app/Core/Autoloader.php';
\App\Core\Autoloader::register();

use App\Services\Question\QuestionEligibilityIndex;
use App\Services\RepositoryHealth\Engine\RepositoryHealthCheckCatalog;
use App\Services\RepositoryHealth\Engine\RepositoryHealthEngine;

function b645_assert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function b645_normalize(mixed $value): mixed
{
    if (!is_array($value)) return $value;
    foreach ($value as &$child) $child = b645_normalize($child);
    unset($child);
    if (!array_is_list($value)) ksort($value, SORT_STRING);
    return $value;
}

function b645_question(string $id, string $board = '', bool $includeTaxonomy = true): array
{
    $question = [
        'id' => $id,
        'question' => "Question {$id}",
        'explanation' => 'A concise explanation for the test fixture.',
        'difficulty' => 'medium',
        'choices' => ['First option', 'Second option'],
        'answer' => 'First option',
        'status' => 'active',
    ];
    if ($board !== '') $question['board'] = $board;
    if ($includeTaxonomy) $question['taxonomy'] = $board === '' ? [] : ['board_id' => $board];
    return $question;
}

$questions = [
    b645_question('q-native-let', 'let'),
    b645_question('q-direct-cse', 'mechanical-engineering'),
    b645_question('q-mapped-nursing', 'architecture'),
    b645_question('q-derived-architecture', 'civil-engineering'),
    b645_question('q-native-civil', 'civil-engineering'),
    b645_question('q-excluded-let', 'let'),
    b645_question('q-unscoped', '', false),
    b645_question('q-empty-taxonomy', '', true),
];
$entries = [
    ['id' => 'direct-cse', 'question_ids' => ['q-direct-cse'], 'exam_ids' => ['civil-service']],
    ['id' => 'mapping-base', 'question_ids' => ['q-mapped-nursing'], 'exam_ids' => ['let'], 'question_mappings' => ['q-mapped-nursing' => ['exam_ids' => ['nursing']]]],
];
$derived = [[
    'id' => 'derived-architecture',
    'record_type' => 'RELATIONSHIP',
    'question_id' => 'q-derived-architecture',
    'target_board' => 'architecture',
    'target_placement' => ['subject_id' => 'architecture-foundations'],
]];
$overrides = [
    ['id' => 'manual-include', 'question_id' => 'q-direct-cse', 'board_id' => 'cpa', 'override_type' => 'INCLUDE', 'placement' => ['subject_id' => 'taxation']],
    ['id' => 'explicit-exclude', 'question_id' => 'q-excluded-let', 'board_id' => 'let', 'override_type' => 'EXCLUDE'],
];
$index = new QuestionEligibilityIndex($entries, [], $derived, $overrides);
$candidateIds = $index->potentialQuestionIdsByExam();
$exams = ['let', 'civil-service', 'nursing', 'architecture', 'civil-engineering', 'mechanical-engineering', 'cpa'];
foreach ($exams as $exam) {
    $examCandidates = $candidateIds[$exam] ?? [];
    $candidateQuestions = [];
    foreach ($questions as $question) {
        $id = (string) ($question['id'] ?? '');
        $taxonomy = is_array($question['taxonomy'] ?? null) ? $question['taxonomy'] : [];
        $nativeBoard = strtolower((string) ($taxonomy['board_id'] ?? $question['board'] ?? ''));
        $nativeBoard = ['cse' => 'civil-service', 'cle' => 'criminologist', 'nle' => 'nursing', 'ple' => 'psychometrician'][$nativeBoard] ?? $nativeBoard;
        $hasNoBoardMetadata = !array_key_exists('board', $question) && !array_key_exists('taxonomy', $question);
        if (isset($examCandidates[$id]) || $nativeBoard === $exam || $hasNoBoardMetadata) {
            $candidateQuestions[] = $question;
        }
    }
    $request = new SelectionRequest('*', null, [], 1, null, null, $exam);
    $expected = QuestionPoolFilter::filter($questions, $request, $index);
    $actual = QuestionPoolFilter::filter($candidateQuestions, $request, $index);
    b645_assert(array_column($expected, 'id') === array_column($actual, 'id'), "Candidate prefilter changed production reachability for {$exam}.");
}

$firstIndex = new QuestionEligibilityIndex([['question_ids' => ['first'], 'exam_ids' => ['let']]]);
$secondIndex = new QuestionEligibilityIndex([['question_ids' => ['second'], 'exam_ids' => ['nursing']]]);
b645_assert(isset($firstIndex->potentialQuestionIdsByExam()['let']['first']), 'First per-scan eligibility index lost its own data.');
b645_assert(!isset($secondIndex->potentialQuestionIdsByExam()['let']['first']), 'Eligibility candidates leaked between index instances.');
b645_assert(isset($secondIndex->potentialQuestionIdsByExam()['nursing']['second']), 'Second per-scan eligibility index did not observe its own repository state.');

$collections = [];
$normal = RepositoryHealthEngine::analyzeCollections($collections)->toArray();
$profiledReport = RepositoryHealthEngine::analyzeCollections($collections, true);
$profiled = $profiledReport->toArray();
unset($normal['generated_at'], $profiled['generated_at']);
b645_assert(b645_normalize($normal) === b645_normalize($profiled), 'Profiling changed normalized health findings or counts.');
b645_assert(!array_key_exists('profiling', $profiled), 'Volatile profiling metadata leaked into the stable HealthReport payload.');
$profile = $profiledReport->profiling ?? [];
b645_assert(
    (int) ($profile['execution_counts']['eligibility_index_builds'] ?? 0) === 1,
    'A health scan rebuilt the same immutable eligibility index for separate consumers.'
);
b645_assert(
    (int) ($profile['execution_counts']['coverage_inventory_filter_executions'] ?? 0) === 1,
    'Coverage profiling did not count its production eligibility filter.'
);
b645_assert(
    (int) ($profile['execution_counts']['coverage_candidate_fallbacks'] ?? 0) === 0,
    'Coverage unexpectedly fell back to full question collections when building candidate sets.'
);
$coverageOperations = array_column((array) ($profile['operations'] ?? []), 'operation');
foreach (['coverage.inventory_eligibility_filter', 'coverage.inventory_taxonomy_scan', 'coverage.subject_depth', 'coverage.blueprint_availability'] as $operation) {
    b645_assert(in_array($operation, $coverageOperations, true), "Coverage profile omitted {$operation}.");
}
$expectedChecks = RepositoryHealthCheckCatalog::definitions();
$timings = [];
foreach ((array) ($profile['check_timings'] ?? []) as $check) {
    $timings[(string) ($check['check_id'] ?? '')] = $check;
    b645_assert((float) ($check['duration_ms'] ?? -1) >= 0, 'A check has a negative profile duration.');
    b645_assert((float) ($check['end_ms'] ?? -1) >= (float) ($check['start_ms'] ?? 0), 'A check has an invalid profile interval.');
    b645_assert((int) ($check['execution_count'] ?? 0) === 1, 'A health check did not execute exactly once.');
}
b645_assert(array_keys($timings) !== [], 'Profiling produced no check timing data.');
b645_assert(count($timings) === count($expectedChecks), 'Profiling omitted a catalog check.');
foreach (array_keys($expectedChecks) as $code) b645_assert(isset($timings[$code]), "Missing profile timing for {$code}.");
b645_assert((float) ($profile['total_runtime_ms'] ?? 0) > 0, 'Profile total runtime was not recorded.');

$changedCollections = ['boards' => [['id' => 'fixture-board', 'name' => 'Fixture Board']], 'questions' => []];
$secondScan = RepositoryHealthEngine::analyzeCollections($changedCollections, true);
b645_assert(count($secondScan->boardSummaries) === 1, 'A sequential scan did not observe its new collection state.');
b645_assert(count($secondScan->profiling['check_timings'] ?? []) === count($expectedChecks), 'A sequential scan reused stale profiling state.');

echo "repository_health_performance_contract=PASS\n";
