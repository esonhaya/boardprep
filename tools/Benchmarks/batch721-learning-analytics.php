<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/Core/Autoloader.php';
\App\Core\Autoloader::register();
require_once dirname(__DIR__, 2) . '/bootstrap/app.php';

use App\Services\Learning\LearningReadinessService;
use App\Services\Learning\Recommendation\LearningQuestionCatalog;
use App\Services\Learning\SubjectPerformanceService;
use App\Services\Learning\StudyDashboardService;
use App\Services\Learning\TopicPerformanceService;

$attempts = [];
$subjects = ['verbal-ability', 'numerical-ability', 'general-information'];
$topics = ['reasoning', 'calculation', 'civic-literacy'];
for ($attemptIndex = 0; $attemptIndex < 30; $attemptIndex++) {
    $review = [];
    $correct = 0;
    for ($questionIndex = 0; $questionIndex < 20; $questionIndex++) {
        $subjectIndex = $questionIndex % 3;
        $isCorrect = (($attemptIndex + $questionIndex) % 5) !== 0;
        $correct += $isCorrect ? 1 : 0;
        $subject = $subjects[$subjectIndex];
        $review[] = [
            'answered' => true,
            'correct' => $isCorrect,
            'question' => [
                'id' => 'synthetic-' . $attemptIndex . '-' . $questionIndex,
                'board' => 'civil-service',
                'taxonomy' => [
                    'board_id' => 'civil-service',
                    'subject_id' => $subject,
                    'topic_id' => $topics[$subjectIndex],
                ],
                'attempt_context' => [
                    'board' => 'civil-service',
                    'subject' => $subject,
                    'civil_service_level' => 'PROFESSIONAL',
                ],
            ],
        ];
    }
    $attempts[] = [
        'id' => 'synthetic-attempt-' . $attemptIndex,
        'session_id' => 'synthetic-session-' . $attemptIndex,
        'completed' => true,
        'board' => 'civil-service',
        'subject' => '*',
        'civil_service_level' => 'PROFESSIONAL',
        'mode' => 'practice',
        'started_at' => sprintf('2026-10-%02dT08:00:00Z', ($attemptIndex % 27) + 1),
        'completed_at' => sprintf('2026-10-%02dT08:05:00Z', ($attemptIndex % 27) + 1),
        'score' => $correct,
        'total' => count($review),
        'answered' => count($review),
        'percentage' => (int) round(100 * $correct / max(1, count($review))),
        'review' => $review,
    ];
}
$preferences = ['preferred_board' => 'CSE', 'civil_service_level' => 'PROFESSIONAL'];
$measure = static function (callable $callback): array {
    $coldStarted = microtime(true);
    $callback();
    $cold = round((microtime(true) - $coldStarted) * 1000, 3);
    $callback(); // explicit warm-up after the cold observation
    $times = [];
    for ($run = 0; $run < 7; $run++) {
        $started = microtime(true);
        $callback();
        $times[] = round((microtime(true) - $started) * 1000, 3);
    }
    $sorted = $times;
    sort($sorted, SORT_NUMERIC);
    return [
        'cold_ms' => $cold,
        'warmup_count' => 1,
        'measured_runs' => count($times),
        'warm_timings_ms' => $times,
        'warm_median_ms' => $sorted[intdiv(count($sorted), 2)],
        'warm_min_ms' => min($times),
        'warm_max_ms' => max($times),
    ];
};

$resetReadinessCatalog = static function (): void {
    $class = new ReflectionClass(LearningQuestionCatalog::class);
    $class->getProperty('topicByQuestionId')->setValue(null, null);
    $class->getProperty('eligibleCountsByScope')->setValue(null, []);
};

$results = [
    'subject_aggregation' => $measure(static fn() => SubjectPerformanceService::summarize($attempts)),
    'topic_aggregation' => $measure(static fn() => TopicPerformanceService::summarize($attempts)),
    'full_study_dashboard' => $measure(static fn() => StudyDashboardService::build($attempts, $preferences)),
];
$resetReadinessCatalog();
$results['readiness_aggregation'] = $measure(static fn() => LearningReadinessService::build($attempts, $preferences));
echo json_encode([
    'report' => 'BATCH_721_LEARNING_ANALYTICS_PERFORMANCE',
    'result' => 'MEASURED',
    'fixture' => [
        'storage' => 'in-memory synthetic attempts; no learner store writes',
        'completed_attempts' => count($attempts),
        'answer_events' => array_sum(array_map(static fn(array $attempt): int => count($attempt['review']), $attempts)),
        'subject_count' => count($subjects),
        'topic_count' => count($topics),
    ],
    'results' => $results,
    'process' => [
        'php_peak_memory_bytes' => memory_get_peak_usage(true),
        'question_bank_reads' => 'not instrumented in this service benchmark; see batch721-recommendation-read-profile.json for an instrumented read-only storage profile',
        'other_storage_read_count' => 'not instrumented',
    ],
    'limits' => [
        'synthetic workload measures service cost, not production learner outcomes or concurrent load',
        'cold means application request-local indexes cleared; operating-system file cache may remain warm',
        'cold/warm values are device-specific',
    ],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL;
