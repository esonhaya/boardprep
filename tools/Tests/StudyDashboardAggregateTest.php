<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . "/app/Core/Autoloader.php";
\App\Core\Autoloader::register();

use App\Services\Learning\StudyDashboardService;

$attempts = [
    [
        "completed" => true,
        "completed_at" => "2026-08-24 09:00:00",
        "subject" => "English",
        "topic" => "Grammar",
        "mode" => "practice",
        "score" => 2,
        "total" => 5,
        "percentage" => 40,
    ],
    [
        "completed" => true,
        "completed_at" => "2026-08-23 09:00:00",
        "subject" => "English",
        "topic" => "Reading",
        "mode" => "practice",
        "score" => 8,
        "total" => 10,
        "percentage" => 80,
    ],
];

$dashboard = StudyDashboardService::build($attempts);

$checks = [
    "progress present" => isset($dashboard["progress"]),
    "topics present" => isset($dashboard["topics"]),
    "weakest topics present" => isset($dashboard["weakestTopics"]),
    "streak present" => array_key_exists("streak", $dashboard),
    "insight present" => !empty($dashboard["insight"]),
    "recommendations present" => !empty($dashboard["recommendations"]),
    "study plan present" => !empty($dashboard["studyPlan"]),
    "study plan bounded" => count($dashboard["studyPlan"]) <= 5,
    "study plan actionable" =>
        str_starts_with((string) ($dashboard["studyPlan"][0]["action"] ?? ""), "/quiz?"),
    "recommendation actionable" =>
        str_starts_with((string) ($dashboard["recommendations"][0]["action"] ?? ""), "/quiz?"),
];

foreach ($checks as $label => $passed) {
    if (!$passed) {
        fwrite(STDERR, "[FAIL] {$label}\n");
        exit(1);
    }
    echo "[PASS] {$label}\n";
}

echo "[PASS] Study dashboard aggregate contract verified. Assertions: "
    . count($checks) . "\n";
