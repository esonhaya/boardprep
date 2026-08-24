<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . "/app/Core/Autoloader.php";
\App\Core\Autoloader::register();

use App\Services\Learning\StudyDashboardService;
use App\Services\Learning\StudyInsightService;
use App\Services\Learning\StudyRecommendationService;

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
        "completed" => true,
        "completed_at" => "2026-08-23 10:00:00",
        "mode" => "practice",
        "topic" => "Grammar",
        "percentage" => 40,
    ],
    [
        "completed" => true,
        "completed_at" => "2026-08-22 10:00:00",
        "mode" => "practice",
        "topic" => "Reading",
        "percentage" => 60,
    ],
    [
        "completed" => true,
        "completed_at" => "2026-08-21 10:00:00",
        "mode" => "exam",
        "topic" => "Grammar",
        "percentage" => 50,
    ],
];

$dashboard = StudyDashboardService::build($attempts);

check(!empty($dashboard["progress"]), "dashboard progress");
check(!empty($dashboard["topics"]), "dashboard topics");
check(!empty($dashboard["weakestTopics"]), "dashboard weakest topics");
check(isset($dashboard["streak"]), "dashboard streak");
check(!empty($dashboard["insight"]["headline"]), "dashboard insight");
check(!empty($dashboard["recommendations"]), "dashboard recommendations");

$insight = StudyInsightService::build(
    $attempts,
    $dashboard["weakestTopics"]
);

check(
    $insight["headline"] === "Build your foundation",
    "low-score insight"
);

check(
    count($insight["actions"]) >= 2,
    "insight includes actionable guidance"
);

$recommendations =
    StudyRecommendationService::build(
        $attempts,
        $dashboard["weakestTopics"],
        2
    );

check(count($recommendations) <= 2, "recommendation limit");
check(
    ($recommendations[0]["type"] ?? "") === "topic",
    "topic recommendation prioritized"
);

$empty = StudyDashboardService::build([]);

check(
    $empty["insight"]["headline"] === "Start your first quiz",
    "empty-state insight"
);

check(
    !empty($empty["recommendations"]),
    "empty-state recommendation"
);

echo "[PASS] Study insights contract verified. Assertions: {$assertions}\n";
