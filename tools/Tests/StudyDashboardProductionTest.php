<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . "/app/Core/Autoloader.php";
\App\Core\Autoloader::register();

use App\Controllers\StudyDashboardController;

$data = StudyDashboardController::data();

$required = [
    "progress",
    "topics",
    "weakestTopics",
    "streak",
    "insight",
    "recommendations",
    "studyPlan",
];

foreach ($required as $key) {
    if (!array_key_exists($key, $data)) {
        fwrite(STDERR, "[FAIL] production data {$key}\n");
        exit(1);
    }

    echo "[PASS] production data {$key}\n";
}

$plan = $data["studyPlan"];

if (count($plan) < 1 || count($plan) > 5) {
    fwrite(STDERR, "[FAIL] study plan bounds\n");
    exit(1);
}

foreach ($plan as $item) {
    if (
        !isset($item["topic"], $item["subject"], $item["action"], $item["label"])
        || !str_starts_with((string) $item["action"], "/quiz?")
    ) {
        fwrite(STDERR, "[FAIL] actionable study plan item\n");
        exit(1);
    }
}

echo "[PASS] production study plan is actionable\n";
echo "[PASS] Production study dashboard contract verified.\n";
