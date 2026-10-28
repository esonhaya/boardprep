<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/Core/Autoloader.php';
\App\Core\Autoloader::register();
require_once dirname(__DIR__, 2) . '/bootstrap/app.php';

use App\Services\Learning\LearningAttemptNormalizer;
use App\Services\Learning\LearningHistoryService;
use App\Services\Learning\LearningProgressService;
use App\Services\Learning\LearningReadinessService;
use App\Services\Learning\LearningStreakService;
use App\Services\Learning\RelativePerformanceComparisonService;
use App\Services\Learning\StudyDashboardService;
use App\Services\Learning\StudyInsightService;
use App\Services\Learning\StudyPlanService;
use App\Services\Learning\StudyRecommendationService;
use App\Services\Learning\SubjectPerformanceService;
use App\Services\Learning\TopicPerformanceService;

$question = static function (string $id, string $subject, string $topic): array {
    return [
        'id' => $id,
        'board' => 'civil-service',
        'subject' => $subject,
        'taxonomy' => [
            'board_id' => 'civil-service',
            'subject_id' => $subject,
            'topic_id' => $topic,
        ],
        'attempt_context' => [
            'board' => 'civil-service',
            'subject' => $subject,
            'civil_service_level' => 'PROFESSIONAL',
        ],
        'difficulty' => 'medium',
    ];
};
$rawAttempts = [
    [
        'id' => 'duplicate-session',
        'session_id' => 'duplicate-session',
        'completed' => true,
        'board' => 'civil-service',
        'subject' => '*',
        'civil_service_level' => 'PROFESSIONAL',
        'mode' => 'practice',
        'completed_at' => '2026-10-25T09:00:00Z',
        'score' => 0,
        'total' => 1,
        'answered' => 1,
        'percentage' => 0,
        'review' => [[
            'answered' => true,
            'correct' => false,
            'question' => $question('old-duplicate', 'verbal-ability', 'reasoning'),
        ]],
    ],
    [
        'id' => 'latest-duplicate-session',
        'session_id' => 'duplicate-session',
        'completed' => true,
        'board' => 'civil-service',
        'subject' => '*',
        'civil_service_level' => 'PROFESSIONAL',
        'mode' => 'practice',
        'completed_at' => '2026-10-26T09:00:00Z',
        'score' => 1,
        'total' => 2,
        'answered' => 2,
        'percentage' => 50,
        'review' => [
            ['answered' => true, 'correct' => true, 'question' => $question('q1', 'verbal-ability', 'reasoning')],
            ['answered' => true, 'correct' => false, 'question' => $question('q2', 'general-information', 'civic-literacy')],
        ],
    ],
    [
        'id' => 'second-session',
        'session_id' => 'second-session',
        'completed' => true,
        'board' => 'civil-service',
        'subject' => '*',
        'civil_service_level' => 'PROFESSIONAL',
        'mode' => 'exam',
        'completed_at' => '2026-10-27T09:00:00Z',
        'score' => 2,
        'total' => 2,
        'answered' => 2,
        'percentage' => 100,
        'review' => [
            ['answered' => true, 'correct' => true, 'question' => $question('q3', 'verbal-ability', 'reasoning')],
            ['answered' => true, 'correct' => true, 'question' => $question('q4', 'general-information', 'civic-literacy')],
        ],
    ],
    [
        'id' => 'unfinished-session',
        'session_id' => 'unfinished-session',
        'completed' => false,
        'board' => 'civil-service',
        'subject' => '*',
        'civil_service_level' => 'PROFESSIONAL',
        'mode' => 'practice',
        'completed_at' => '2026-10-28T09:00:00Z',
        'score' => 0,
        'total' => 1,
        'answered' => 0,
        'percentage' => 0,
        'review' => [],
    ],
];
$preferences = ['preferred_board' => 'CSE', 'civil_service_level' => 'PROFESSIONAL'];
$ordered = LearningHistoryService::ordered($rawAttempts);
$attemptTotal = LearningAttemptNormalizer::total($rawAttempts);

$assertSame = static function (mixed $expected, mixed $actual, string $label): void {
    if ($expected !== $actual) {
        throw new RuntimeException("Prepared analytics changed {$label} output.");
    }
};

$assertSame(
    LearningProgressService::build($rawAttempts),
    LearningProgressService::buildFromOrdered($ordered, $attemptTotal),
    'progress'
);
$assertSame(
    SubjectPerformanceService::summarize($rawAttempts),
    SubjectPerformanceService::summarizeFromOrdered($ordered),
    'subject performance'
);
$topicSummary = TopicPerformanceService::summarize($rawAttempts);
$assertSame(
    $topicSummary,
    TopicPerformanceService::summarizeFromOrdered($ordered),
    'topic performance'
);
$assertSame(
    TopicPerformanceService::weakest($rawAttempts, 3),
    TopicPerformanceService::weakestFromSummary($topicSummary, 3),
    'weakest-topic ranking'
);
$assertSame(
    LearningReadinessService::build($rawAttempts, $preferences),
    LearningReadinessService::buildFromOrdered($ordered, $preferences),
    'readiness'
);

$legacyTopics = TopicPerformanceService::summarize($ordered);
$legacySubjects = SubjectPerformanceService::summarize($ordered);
$legacyWeakest = TopicPerformanceService::weakest($ordered, 3);
$legacyFocus = RelativePerformanceComparisonService::belowContextAverage($legacyTopics, 3);
$legacyProgress = LearningProgressService::build($rawAttempts);
$legacyDashboard = [
    'progress' => $legacyProgress,
    'topics' => $legacyTopics,
    'subjects' => $legacySubjects,
    'weakestTopics' => $legacyWeakest,
    'focusTopics' => $legacyFocus,
    'strongestSubjects' => RelativePerformanceComparisonService::aboveContextAverage($legacySubjects, 3),
    'streak' => LearningStreakService::current($ordered),
    'insight' => StudyInsightService::build($ordered, $legacyFocus),
    'recommendations' => StudyRecommendationService::build($ordered, $legacyWeakest, 3),
    'readiness' => LearningReadinessService::build($ordered, $preferences),
];
$legacyDashboard['studyPlan'] = StudyPlanService::build($legacyDashboard);
$assertSame(
    $legacyDashboard,
    StudyDashboardService::build($rawAttempts, $preferences),
    'complete dashboard'
);

echo "[PASS] Prepared analytics matches public normalization paths for duplicate and incomplete history.\n";
echo "[PASS] Readiness and the complete dashboard preserve exact output while reusing prepared history and topic summaries.\n";
