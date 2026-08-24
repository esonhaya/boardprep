<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . "/app/Core/Autoloader.php";
\App\Core\Autoloader::register();

use App\Services\Learning\StudyPlanService;

$plan = StudyPlanService::build([]);

$checks = [
    "empty plan has one fallback" => count($plan) === 1,
    "fallback topic is General" =>
        ($plan[0]["topic"] ?? "") === "General",
    "fallback action targets quiz" =>
        str_starts_with(
            (string) ($plan[0]["action"] ?? ""),
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

echo "[PASS] Study plan Doctor contract verified.\n";
