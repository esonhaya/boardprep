<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . "/app/Core/Autoloader.php";

\App\Core\Autoloader::register();

use App\Services\Learning\LearningHistoryService;
use App\Services\Learning\LearningProgressService;
use App\Services\Learning\LearningStreakService;
use App\Services\Learning\LearningTimelineService;
use App\Services\Learning\PerformanceAnalyticsService;
use App\Services\Learning\TopicPerformanceService;

$assertions = 0;

function check(bool $condition, string $message): void
{
    global $assertions;
    $assertions++;

    if (!$condition) {
        fwrite(STDERR, "[FAIL] {$message}\n");
        exit(1);
    }

    echo "[PASS] {$message}\n";
}

$attempts = [
    [
        "id" => "new",
        "completed" => true,
        "completed_at" => "2026-08-23 10:00:00",
        "mode" => "practice",
        "subject" => "English",
        "topic" => "Grammar",
        "score" => 8,
        "total" => 10,
        "percentage" => 80,
    ],
    [
        "id" => "legacy",
        "date" => "2026-08-22 10:00:00",
        "mode" => "exam",
        "subject" => "English",
        "topic" => "Reading",
        "score" => 6,
        "total" => 10,
        "percentage" => 60,
    ],
    [
        "id" => "started",
        "started_at" => "2026-08-21 10:00:00",
        "mode" => "practice",
        "subject" => "English",
        "topic" => "Grammar",
        "score" => 7,
        "total" => 10,
        "percentage" => 70,
    ],
];

$history = LearningHistoryService::recent(10);
check(count($history) >= 0, "production history service callable");

$summary = LearningProgressService::build($attempts);
check($summary["totalAttempts"] === 3, "progress total");
check($summary["completedAttempts"] === 3, "progress completed count");
check($summary["averageScore"] === 70, "progress average");
check($summary["bestScore"] === 80, "progress best");
check($summary["practiceAttempts"] === 2, "practice count");
check($summary["examAttempts"] === 1, "exam count");

$topics = TopicPerformanceService::summarize($attempts);
check(count($topics) === 2, "topic aggregation");
check($topics[0]["topic"] === "Grammar", "topic ranking");
check($topics[0]["average"] === 75, "topic average");

$weakest = TopicPerformanceService::weakest($attempts, 1);
check(count($weakest) === 1, "weakest topic limit");
check($weakest[0]["topic"] === "Reading", "weakest topic selection");

$streak = LearningStreakService::current($attempts);
check($streak === 3, "consecutive learning streak");

$timeline = LearningTimelineService::build($attempts);
check($timeline[0]["timestamp"] >= $timeline[1]["timestamp"], "timeline ordering");
check($timeline[0]["topic"] === "Grammar", "timeline topic normalization");

$analytics = PerformanceAnalyticsService::summary($attempts);
check($analytics["totalQuizzes"] === 3, "analytics total");
check($analytics["averageScore"] === 70, "analytics average");
check($analytics["bestScore"] === 80, "analytics best");

echo "[PASS] Learning progress contract verified. Assertions: {$assertions}\n";
