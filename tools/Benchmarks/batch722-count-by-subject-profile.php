<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/Core/Autoloader.php';
\App\Core\Autoloader::register();
require_once dirname(__DIR__, 2) . '/bootstrap/app.php';
require_once __DIR__ . '/Support/CountingReadOnlyStorage.php';

use App\Core\App;
use App\Services\Question\QuestionEligibilityService;
use App\Services\Shared\TaxonomyStorageService;

$database = App::database();
$storageProperty = new ReflectionProperty($database, 'storage');
$storage = new CountingReadOnlyStorage($storageProperty->getValue($database));
$storageProperty->setValue($database, $storage);

$started = hrtime(true);
$questions = App::storage()->all('questions');
$questionReadMs = round((hrtime(true) - $started) / 1_000_000, 3);

$subjects = [];
foreach (TaxonomyStorageService::subjectsByBoard('civil-service') as $relation) {
    if (!is_array($relation)) {
        continue;
    }
    $subjectId = trim((string) ($relation['subject_id'] ?? ''));
    if ($subjectId !== '') {
        $subjects[$subjectId] = TaxonomyStorageService::label('subjects', $subjectId);
    }
}

$started = hrtime(true);
$eligibility = QuestionEligibilityService::index($questions);
$indexBuildMs = round((hrtime(true) - $started) / 1_000_000, 3);
$request = new SelectionRequest(
    '*',
    null,
    [],
    1,
    exam: 'civil-service',
    civilServiceLevel: 'PROFESSIONAL'
);

$measure = static function () use ($questions, $subjects, $request, $eligibility): array {
    $started = hrtime(true);
    $counts = QuestionPoolFilter::countBySubject($questions, $subjects, $request, $eligibility);
    return [
        'elapsed_ms' => round((hrtime(true) - $started) / 1_000_000, 3),
        'counts' => $counts,
    ];
};

$cold = $measure();
$measure(); // one warm-up before collecting steady same-process timings
$warmTimings = [];
for ($run = 0; $run < 7; $run++) {
    $warmTimings[] = $measure()['elapsed_ms'];
}
$sorted = $warmTimings;
sort($sorted, SORT_NUMERIC);

echo json_encode([
    'report' => 'BATCH_722_COUNT_BY_SUBJECT_PROFILE',
    'result' => 'MEASURED',
    'fixture' => [
        'canonical_question_count' => count($questions),
        'board' => 'civil-service',
        'civil_service_level' => 'PROFESSIONAL',
        'configured_subject_count' => count($subjects),
        'subject_ids' => array_keys($subjects),
    ],
    'timings' => [
        'question_snapshot_ms' => $questionReadMs,
        'eligibility_index_build_ms' => $indexBuildMs,
        'pool_count_cold_ms' => $cold['elapsed_ms'],
        'pool_count_warmup_count' => 1,
        'pool_count_warm_timings_ms' => $warmTimings,
        'pool_count_warm_median_ms' => $sorted[intdiv(count($sorted), 2)],
    ],
    'result_counts' => $cold['counts'],
    'storage' => [
        'question_bank_reads' => (int) ($storage->allReads['questions'] ?? 0),
        'all_reads' => $storage->allReads,
        'mutation_methods' => 'throw on mutation',
    ],
    'process' => [
        'php_peak_memory_bytes' => memory_get_peak_usage(true),
    ],
    'limits' => [
        'single-device sequential PHP CLI profile; OS file cache was not cleared',
        'cold is first count pass after loading one snapshot and building one exact eligibility index',
        'warm timings reuse immutable in-process snapshot/index; they are not separate HTTP request timings',
    ],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL;
