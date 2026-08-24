<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . "/app/Core/Autoloader.php";
\App\Core\Autoloader::register();

use App\Services\Learning\LearningProgressService;
use App\Services\Learning\LearningStreakService;
use App\Services\Learning\StudyDashboardService;
use App\Services\Learning\StudyInsightService;
use App\Services\Learning\StudyRecommendationService;
use App\Services\Learning\TopicPerformanceService;

$attempts = [[
    "completed" => true,
    "completed_at" => "2026-08-24 10:00:00",
    "mode" => "practice",
    "topic" => "Grammar",
    "percentage" => 80,
]];

$progress = LearningProgressService::build($attempts);
$topics = TopicPerformanceService::summarize($attempts);
$weakest = TopicPerformanceService::weakest($attempts, 3);
$streak = LearningStreakService::current($attempts);
$insight = StudyInsightService::build($attempts, $weakest);
$recommendations = StudyRecommendationService::build($attempts, $weakest, 3);
$dashboard = StudyDashboardService::build($attempts);

$checks = [
    "progress API callable" => is_array($progress),
    "topic summarize API callable" => is_array($topics),
    "topic weakest API callable" => is_array($weakest),
    "streak API callable" => is_int($streak),
    "insight API callable" => is_array($insight),
    "recommendation API callable" => is_array($recommendations),
    "dashboard API callable" => is_array($dashboard),
    "dashboard uses progress contract" =>
        ($dashboard["progress"]["averageScore"] ?? null) ===
        ($progress["averageScore"] ?? null),
    "dashboard uses topic contract" =>
        ($dashboard["topics"][0]["topic"] ?? null) ===
        ($topics[0]["topic"] ?? null),
    "dashboard has study plan" => !empty($dashboard["studyPlan"]),
];

foreach ($checks as $label => $passed) {
    if (!$passed) {
        fwrite(STDERR, "[FAIL] {$label}\n");
        exit(1);
    }
    echo "[PASS] {$label}\n";
}

echo "[PASS] Study dashboard API boundary verified. Assertions: "
    . count($checks) . "\n";
