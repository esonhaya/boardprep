<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . "/app/Core/Autoloader.php";
\App\Core\Autoloader::register();

use App\Services\Learning\LearningHistoryService;
use App\Services\Learning\StudyDashboardService;

$attempts = [
    [
        "completed" => true,
        "completed_at" => "2026-08-24 10:00:00",
        "mode" => "practice",
        "topic" => "Grammar",
        "percentage" => 80,
        "score" => 4,
        "total" => 5,
    ],
    [
        "completed" => true,
        "completed_at" => "2026-08-23 10:00:00",
        "mode" => "practice",
        "topic" => "Reading",
        "percentage" => 40,
        "score" => 2,
        "total" => 5,
    ],
    [
        "completed" => true,
        "completed_at" => "2026-08-22 10:00:00",
        "mode" => "exam",
        "topic" => "Reading",
        "percentage" => 60,
        "score" => 3,
        "total" => 5,
    ],
];

function dashboardHasTopic(array $topics, string $wanted): bool
{
    foreach ($topics as $key => $topic) {
        if (
            (is_string($key) && strcasecmp($key, $wanted) === 0)
            || (
                is_array($topic)
                && strcasecmp((string) ($topic["topic"] ?? ""), $wanted) === 0
            )
        ) {
            return true;
        }
    }

    return false;
}

$dashboard = StudyDashboardService::build($attempts);

$checks = [
    "progress is present" => !empty($dashboard["progress"]),
    "topics are present" => !empty($dashboard["topics"]),
    "weakest topics are present" => !empty($dashboard["weakestTopics"]),
    "streak is present" => isset($dashboard["streak"]),
    "insight is present" => !empty($dashboard["insight"]["headline"]),
    "recommendations are present" => !empty($dashboard["recommendations"]),
    "grammar is represented" => dashboardHasTopic($dashboard["topics"], "Grammar"),
    "reading is represented" => dashboardHasTopic($dashboard["topics"], "Reading"),
];

foreach ($checks as $label => $passed) {
    if (!$passed) {
        fwrite(STDERR, "[FAIL] {$label}\n");
        exit(1);
    }
    echo "[PASS] {$label}\n";
}

$empty = StudyDashboardService::build([]);

if ($empty["insight"]["headline"] !== "Start your first quiz") {
    fwrite(STDERR, "[FAIL] empty-state dashboard insight\n");
    exit(1);
}

echo "[PASS] empty-state dashboard insight\n";

if (empty($empty["recommendations"])) {
    fwrite(STDERR, "[FAIL] empty-state dashboard recommendations\n");
    exit(1);
}

echo "[PASS] empty-state dashboard recommendations\n";
echo "[PASS] Study dashboard contract verified.\n";
