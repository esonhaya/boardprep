<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . "/app/Core/Autoloader.php";
\App\Core\Autoloader::register();

use App\Services\Learning\StudyDashboardService;

$dashboard = StudyDashboardService::build([]);
$plan = $dashboard["studyPlan"] ?? [];
$recommendations = $dashboard["recommendations"] ?? [];

$checks = [
    "one fallback plan" => count($plan) === 1,
    "fallback topic is General" => ($plan[0]["topic"] ?? "") === "General",
    "fallback action targets quiz" =>
        str_starts_with((string) ($plan[0]["action"] ?? ""), "/quiz?"),
    "fallback recommendation exists" => !empty($recommendations),
    "fallback recommendation targets quiz" =>
        str_starts_with((string) ($recommendations[0]["action"] ?? ""), "/quiz?"),
    "empty insight preserved" =>
        ($dashboard["insight"]["headline"] ?? "") === "Start your first quiz",
];

foreach ($checks as $label => $passed) {
    if (!$passed) {
        fwrite(STDERR, "[FAIL] {$label}\n");
        exit(1);
    }
    echo "[PASS] {$label}\n";
}

echo "[PASS] Study dashboard empty-state contract verified. Assertions: "
    . count($checks) . "\n";
