<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/Core/Autoloader.php';
\App\Core\Autoloader::register();
require_once dirname(__DIR__, 2) . '/bootstrap/app.php';
require_once __DIR__ . '/../Benchmarks/Support/CountingReadOnlyStorage.php';

use App\Core\App;
use App\Repositories\QuestionRepository;
use App\Services\Question\QuestionEligibilityService;
use App\Services\Shared\TaxonomyStorageService;

$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};

$database = App::database();
$storageProperty = new ReflectionProperty($database, 'storage');
$storage = new CountingReadOnlyStorage($storageProperty->getValue($database));
$storageProperty->setValue($database, $storage);
$questions = App::container()->get(QuestionRepository::class)->all();
$eligibility = QuestionEligibilityService::index($questions);
$boards = array_values(array_filter(TaxonomyStorageService::boards(), static fn(mixed $row): bool =>
    is_array($row) && strtolower(trim((string) ($row['status'] ?? 'active'))) !== 'archived'
));
$results = [];

foreach ($boards as $board) {
    $boardId = strtolower(trim((string) ($board['id'] ?? '')));
    if ($boardId === '') {
        continue;
    }
    $civilServiceLevel = $boardId === 'civil-service' ? 'PROFESSIONAL' : null;
    $request = new SelectionRequest(
        subject: '*',
        domain: null,
        difficultyDistribution: [],
        questionCount: 5,
        exam: $boardId,
        civilServiceLevel: $civilServiceLevel
    );
    $pool = QuestionPoolFilter::filter($questions, $request, $eligibility);
    $assert($pool !== [], "No canonical eligible pool was available for configured board {$boardId}.");
    $count = min(5, count($pool));
    $selected = array_slice($pool, 0, $count);
    $exposure = [];
    foreach (array_slice($selected, 0, 2) as $question) {
        $id = (string) ($question['id'] ?? '');
        if ($id !== '') {
            $exposure[$id] = ['count' => 1, 'last_attempt_index' => 0];
        }
    }
    $ranked = AdaptiveQuestionSelectionService::apply($selected, $pool, $request, [
        'weak_topics' => [],
        'question_exposure' => $exposure,
    ]);
    $poolIds = array_fill_keys(array_map(static fn(array $row): string => (string) ($row['id'] ?? ''), $pool), true);
    $selectedIds = array_map(static fn(array $row): string => (string) ($row['id'] ?? ''), $ranked);
    $assert(count($ranked) === $count, "Adaptive ranking changed the requested eligible count for {$boardId}.");
    $assert(count(array_unique($selectedIds)) === count($selectedIds), "Adaptive ranking duplicated a question for {$boardId}.");
    foreach ($selectedIds as $id) {
        $assert($id !== '' && isset($poolIds[$id]), "Adaptive ranking selected an ineligible/noncanonical question for {$boardId}: {$id}.");
    }
    $results[] = [
        'board' => $boardId,
        'civil_service_level' => $civilServiceLevel,
        'eligible_pool_count' => count($pool),
        'selected_count' => count($ranked),
        'unique_selected_count' => count(array_unique($selectedIds)),
        'all_selected_from_filtered_pool' => true,
    ];
    unset($pool, $ranked, $selected, $poolIds, $selectedIds);
    gc_collect_cycles();
}

$assert(count($results) === 10, 'Adaptive eligibility evaluation did not cover all ten active boards.');
$questionReads = (int) ($storage->allReads['questions'] ?? 0);
$assert($questionReads === 1, "Expected one canonical question-bank read; observed {$questionReads}.");

echo json_encode([
    'report' => 'BATCH_721_TEN_BOARD_ADAPTIVE_ELIGIBILITY',
    'result' => 'PASS',
    'canonical_question_count' => count($questions),
    'board_count' => count($results),
    'boards' => $results,
    'question_bank_reads' => $questionReads,
    'storage_mutations' => 0,
    'peak_memory_bytes' => memory_get_peak_usage(true),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL;
