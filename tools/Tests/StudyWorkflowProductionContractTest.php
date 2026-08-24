<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . "/app/Core/Autoloader.php";
\App\Core\Autoloader::register();

use App\Services\Learning\StudyActionService;
use App\Services\Learning\StudyDashboardService;
use App\Services\Learning\StudyPlanService;
use App\Services\Learning\StudySessionService;

$checks = [];

$spec = StudyActionService::quizForTopic("Grammar");

$checks["default specification"] =
    $spec === [
        "action" => "start",
        "topic" => "Grammar",
        "subject" => "English",
        "mode" => "practice",
        "count" => 5,
        "difficulty" => "mixed",
    ];

$checks["query contract"] =
    str_contains(StudyActionService::query($spec), "topic=Grammar");

$checks["url contract"] =
    str_starts_with(StudyActionService::url($spec), "/quiz?");

$checks["session normalization"] =
    StudySessionService::normalize([
        "topic" => " Grammar ",
        "count" => 999,
        "mode" => "bad",
        "difficulty" => "bad",
    ]) === [
        "topic" => "Grammar",
        "subject" => "English",
        "count" => 20,
        "difficulty" => "mixed",
        "mode" => "practice",
    ];

$dashboard = StudyDashboardService::build([
    [
        "topic" => "Grammar",
        "subject" => "English",
        "percentage" => 40,
        "mode" => "practice",
        "date" => "2026-08-24",
    ],
]);

$plan = StudyPlanService::build($dashboard);

$checks["production plan exists"] = !empty($plan);
$checks["production plan has quiz action"] =
    str_starts_with((string) ($plan[0]["action"] ?? ""), "/quiz?");

$checks["empty plan fallback"] = (
    count(StudyPlanService::build([])) === 1
    && StudyPlanService::build([])[0]["topic"] === "General"
);

foreach ($checks as $label => $passed) {
    if (!$passed) {
        fwrite(STDERR, "[FAIL] {$label}\n");
        exit(1);
    }

    echo "[PASS] {$label}\n";
}

echo "[PASS] Study workflow production contract verified. Assertions: "
    . count($checks)
    . "\n";
