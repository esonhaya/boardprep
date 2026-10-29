<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . "/app/Core/Autoloader.php";
\App\Core\Autoloader::register();

use App\Services\Learning\LearningHistoryService;
use App\Services\Learning\DashboardStudyContinuityService;
use App\Services\Learning\LearningProgressService;
use App\Services\Learning\LearningTimelineService;
use App\Services\Learning\StudyDashboardService;

$attempts = [
    ['completed' => false, 'mode' => 'exam', 'percentage' => 100],
    ['completed' => 'false', 'mode' => 'practice', 'percentage' => 100],
    ['mode' => 'practice', 'subject' => 'Mathematics', 'topic' => 'Algebra', 'percentage' => 0.75, 'date' => '2026-08-28 10:00:00'],
    ['mode' => 'exam', 'subject' => 'English', 'topic' => 'Algebra', 'percentage' => 150, 'completed_at' => '2026-08-29 10:00:00'],
    ['mode' => 'practice', 'topic' => 'Ignored', 'percentage' => 'bad'],
    'malformed',
];

$progress = LearningProgressService::build($attempts);
if ($progress['totalAttempts'] !== 4 || $progress['completedAttempts'] !== 2
    || $progress['practiceAttempts'] !== 1 || $progress['examAttempts'] !== 1) {
    throw new RuntimeException('dashboard attempt population/counts are inconsistent');
}
if ($progress['averageScore'] !== 88 || $progress['bestScore'] !== 100) {
    throw new RuntimeException('dashboard percentage normalization failed');
}

$dashboard = StudyDashboardService::build($attempts);
if (count($dashboard['recommendations']) < 1 || count($dashboard['studyPlan']) < 1) {
    throw new RuntimeException('dashboard downstream consumers lost normalized attempts');
}

$englishAlgebra = array_values(array_filter(
    $dashboard['topics'],
    static fn(array $topic): bool => ($topic['subject'] ?? '') === 'English'
));
$mathAlgebra = array_values(array_filter(
    $dashboard['topics'],
    static fn(array $topic): bool => ($topic['subject'] ?? '') === 'Mathematics'
));
if (count($englishAlgebra) !== 1 || count($mathAlgebra) !== 1) {
    throw new RuntimeException('subject/topic learning context was not preserved');
}

$latest = LearningHistoryService::timestampOf([
    'completed_at' => 'not-a-date',
    'date' => '2026-08-30 09:00:00',
]);
if ($latest !== strtotime('2026-08-30 09:00:00')) {
    throw new RuntimeException('latest attempt timestamp fallback failed');
}

$timeline = LearningTimelineService::build([
    ['percentage' => 20, 'date' => '2026-08-20 10:00:00'],
    ['percentage' => 90, 'completed_at' => '2026-08-29 10:00:00'],
]);
if (($timeline[0]['percentage'] ?? 0) !== 90 || ($timeline[0]['completed'] ?? false) !== true) {
    throw new RuntimeException('latest attempt ordering or completion normalization failed');
}

$emptyContinuity = DashboardStudyContinuityService::build([]);
if (($emptyContinuity['state'] ?? '') !== 'NO_HISTORY'
    || ($emptyContinuity['has_mistake_or_skipped_review'] ?? true) !== false) {
    throw new RuntimeException('new learner was given unsupported continuation or mistake state');
}
$continuity = DashboardStudyContinuityService::build([
    [
        'id' => 'attempt-latest-owned',
        'board' => 'let',
        'subject' => 'English',
        'score' => 1,
        'total' => 2,
        'percentage' => 50,
        'review' => [
            ['question' => ['id' => 'q-latest'], 'answered' => true, 'correct' => true],
        ],
    ],
    [
        'id' => 'attempt-older-owned',
        'review' => [
            ['question' => ['id' => 'q-old'], 'answered' => true, 'correct' => false],
        ],
    ],
]);
if (($continuity['state'] ?? '') !== 'RETURNING_LEARNER'
    || ($continuity['latest_result_url'] ?? '') !== '/history/result?id=attempt-latest-owned'
    || ($continuity['latest_review_available'] ?? false) !== true
    || ($continuity['has_mistake_or_skipped_review'] ?? false) !== true) {
    throw new RuntimeException('returning learner continuation did not preserve latest result and historical mistakes');
}
$legacyContinuity = DashboardStudyContinuityService::build([[
    'score' => 1, 'total' => 1, 'percentage' => 100, 'review' => [],
]]);
if (($legacyContinuity['latest_result_url'] ?? '') !== '/history'
    || ($legacyContinuity['latest_review_available'] ?? true) !== false) {
    throw new RuntimeException('legacy result without an ID or review was presented as directly reviewable');
}
$unknownOutcome = DashboardStudyContinuityService::build([[
    'id' => 'attempt-unknown-outcome',
    'review' => [['question' => ['id' => 'q-unknown'], 'correct' => false]],
]]);
if (($unknownOutcome['has_mistake_or_skipped_review'] ?? true) !== false) {
    throw new RuntimeException('missing answered state was incorrectly classified as a skipped question');
}

echo "[PASS] learner dashboard normalizes one consistent attempt population.\n";
echo "[PASS] learner dashboard preserves subject/topic context and timestamp fallback.\n";
echo "[PASS] dashboard continuity distinguishes new learners, latest results, legacy reviews, and historical mistakes.\n";
