<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . "/app/Core/Autoloader.php";
\App\Core\Autoloader::register();

use App\Services\Learning\StudyDashboardService;
use App\Services\Learning\StudyPlanService;

$attempts = [
    [
        "subject" => "English",
        "topic" => "Grammar",
        "score" => 2,
        "total" => 5,
        "percentage" => 40,
        "mode" => "practice",
        "completed_at" => "2026-08-24T18:00:00+08:00",
    ],
    [
        "subject" => "English",
        "topic" => "Reading",
        "score" => 4,
        "total" => 5,
        "percentage" => 80,
        "mode" => "practice",
        "completed_at" => "2026-08-23T18:00:00+08:00",
    ],
];

$dashboard = StudyDashboardService::build($attempts);
$plan = StudyPlanService::build($dashboard);

$checks = [
    "plan has focus item" =>
        ($plan[0]["topic"] ?? "") === "Grammar",

    "plan preserves subject" =>
        ($plan[0]["subject"] ?? "") === "English",

    "plan has action" =>
        str_starts_with(
            (string) ($plan[0]["action"] ?? ""),
            "/quiz?"
        ),

    "plan action carries topic" =>
        str_contains(
            (string) ($plan[0]["action"] ?? ""),
            "topic=Grammar"
        ),

    "plan has actionable label" =>
        ($plan[0]["label"] ?? "") === "Practice Grammar",

    "plan preserves average" =>
        ($plan[0]["average"] ?? null) === 40,

    "empty plan remains actionable" =>
        count(StudyPlanService::build([])) === 1
        && StudyPlanService::build([])[0]["topic"] === "General"
        && str_starts_with(
            (string) StudyPlanService::build([])[0]["action"],
            "/quiz?"
        ),
];

foreach ($checks as $label => $passed) {
    if (!$passed) {
        fwrite(STDERR, "[FAIL] {$label}\n");
        exit(1);
    }

    echo "[PASS] {$label}\n";
}

echo "[PASS] Study dashboard actionability verified. Assertions: "
    . count($checks)
    . "\n";
