<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . "/app/Core/Autoloader.php";
\App\Core\Autoloader::register();

use App\Controllers\StudyDashboardController;

$data = StudyDashboardController::data();

$checks = [
    "controller returns dashboard" => is_array($data),
    "controller returns progress" => isset($data["progress"]),
    "controller returns topics" => isset($data["topics"]),
    "controller returns weakest topics" => isset($data["weakestTopics"]),
    "controller returns streak" => array_key_exists("streak", $data),
    "controller returns insight" => isset($data["insight"]),
    "controller returns recommendations" => isset($data["recommendations"]),
    "controller returns study plan" => isset($data["studyPlan"]),
    "study plan is bounded" =>
        count($data["studyPlan"] ?? []) <= 5,
];

foreach ($checks as $label => $passed) {
    if (!$passed) {
        fwrite(STDERR, "[FAIL] {$label}\n");
        exit(1);
    }
    echo "[PASS] {$label}\n";
}

echo "[PASS] Study dashboard controller contract verified. Assertions: "
    . count($checks) . "\n";
