<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/Core/Autoloader.php';
\App\Core\Autoloader::register();

use App\Services\Learning\LearningHistoryService;
use App\Services\Learning\LearningProgressService;
use App\Services\Learning\LearningTimelineService;
use App\Services\Learning\PerformanceAnalyticsService;
use App\Services\Learning\SubjectPerformanceService;
use App\Services\Learning\TopicPerformanceService;

$attempts = [
    ['id' => 'old', 'session_id' => 's1', 'subject' => 'English', 'topic' => 'Grammar', 'mode' => 'practice', 'score' => 1, 'total' => 5, 'percentage' => 99, 'completed_at' => '2026-08-01T08:00:00+00:00'],
    ['id' => 'duplicate-old', 'session_id' => 's1', 'subject' => 'English', 'topic' => 'Grammar', 'mode' => 'practice', 'score' => 3, 'total' => 5, 'completed_at' => '2026-08-02T08:00:00+00:00'],
    ['id' => 'partial', 'subject' => 'English', 'topic' => 'Grammar', 'mode' => 'exam', 'session_type' => 'exam_simulation', 'score' => 4, 'total' => 10, 'unanswered' => 4, 'completed_at' => '2026-08-03T08:00:00+00:00'],
    ['id' => 'math-a', 'subject' => 'Mathematics', 'topic' => 'Algebra', 'mode' => 'practice', 'score' => 8, 'total' => 10, 'completed_at' => '2026-08-04T08:00:00+00:00'],
    ['id' => 'math-b', 'subject' => 'Mathematics', 'topic' => 'Algebra', 'mode' => 'practice', 'score' => 2, 'total' => 10, 'answered' => 5, 'completed_at' => '2026-08-05T08:00:00+00:00'],
    ['id' => 'tie-first', 'subject' => 'Science', 'topic' => 'Biology', 'score' => 1, 'total' => 2, 'completed_at' => '2026-08-06T08:00:00+00:00'],
    ['id' => 'tie-latest', 'subject' => 'Science', 'topic' => 'Biology', 'score' => 2, 'total' => 2, 'completed_at' => '2026-08-06T08:00:00+00:00'],
    ['id' => 'incomplete', 'completed' => false, 'score' => 10, 'total' => 10],
    ['id' => 'nan', 'percentage' => 'NAN'],
    ['id' => 'empty'],
    'legacy garbage',
];

$progress = LearningProgressService::build($attempts);
$analytics = PerformanceAnalyticsService::summary($attempts);
$history = LearningHistoryService::ordered($attempts);
$timeline = LearningTimelineService::build($attempts);
$subjects = SubjectPerformanceService::summarize($attempts);
$topics = TopicPerformanceService::summarize($attempts);
$emptyProgress = LearningProgressService::build([]);
$emptyAnalytics = PerformanceAnalyticsService::summary([]);
$sparseProgress = LearningProgressService::build([
    ['id' => 'few-answers', 'score' => 1, 'total' => 4, 'answered' => 4, 'completed_at' => '2026-08-07T08:00:00+00:00'],
]);

$fail = static function (bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};

$fail($progress['completedAttempts'] === 6, 'duplicate, incomplete, and malformed attempts affected totals');
$fail($progress['totalAttempts'] === 7, 'incomplete attempts were not distinguished from completions');
$fail($progress['correctCount'] === 20 && $progress['answeredCount'] === 30
    && $progress['incorrectCount'] === 10 && $progress['unansweredCount'] === 9,
    'answer counts were not reconciled to quiz totals');
$fail($progress['accuracy'] === 67, 'answer accuracy is unsafe or inconsistent');
$fail(($progress['recentAccuracy'] ?? null) === 68
    && ($progress['recentAnsweredCount'] ?? 0) === 25
    && ($progress['recentAttemptCount'] ?? 0) === 5
    && ($progress['recentAccuracyStatus'] ?? '') === 'AVAILABLE',
    'recent pooled answer accuracy did not use the latest bounded completed-attempt window');
$fail(($analytics['recentAccuracy'] ?? null) === 68
    && ($analytics['recentAnsweredCount'] ?? 0) === 25
    && ($analytics['recentAttemptLimit'] ?? 0) === 5,
    'performance analytics summary diverged from recent answer-event accuracy');
$fail(array_key_exists('recentAccuracy', $emptyProgress) && $emptyProgress['recentAccuracy'] === null
    && ($emptyProgress['recentAccuracyStatus'] ?? '') === 'NO_ANSWERED_QUESTIONS'
    && array_key_exists('recentAccuracy', $emptyAnalytics) && $emptyAnalytics['recentAccuracy'] === null,
    'new-learner recent accuracy did not retain an honest empty state');
$fail(array_key_exists('recentAccuracy', $sparseProgress) && $sparseProgress['recentAccuracy'] === null
    && ($sparseProgress['recentAccuracyStatus'] ?? '') === 'INSUFFICIENT_SAMPLE'
    && ($sparseProgress['recentAnsweredCount'] ?? 0) === 4,
    'recent accuracy was reported from fewer than the minimum answer events');
$fail($progress['averageScore'] === 58 && $analytics['averageScore'] === 58,
    'canonical per-attempt average diverged between services');
$fail($analytics['latestScore'] === 100 && $analytics['trend']['direction'] === 'improving',
    'latest state and equal-time trend are not deterministic');
$fail(($history[0]['id'] ?? '') === 'tie-latest' && ($timeline[0]['topic'] ?? '') === 'Biology',
    'history and timeline disagree on equal timestamps');
$fail(count($subjects) === 3 && count($topics) === 3,
    'subject/topic isolation failed across repeated attempts');
$fail(($progress['examAttempts'] ?? 0) === 1 && ($progress['practiceAttempts'] ?? 0) === 3,
    'ordinary and simulation modes were not counted compatibly');

echo "[PASS] multi-attempt overall/recent answer accuracy, sample handling, safety, aggregation, and trends verified.\n";
