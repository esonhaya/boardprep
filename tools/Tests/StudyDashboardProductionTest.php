<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . "/app/Core/Autoloader.php";
\App\Core\Autoloader::register();

use App\Controllers\StudyDashboardController;
use App\Services\Learning\StudyDashboardService;

if (!class_exists(StudyDashboardController::class)) {
    fwrite(STDERR, "[FAIL] Study dashboard controller unavailable.\n");
    exit(1);
}

$data = StudyDashboardController::data();

foreach ([
    "progress",
    "topics",
    "weakestTopics",
    "streak",
    "insight",
    "recommendations",
] as $key) {
    if (!array_key_exists($key, $data)) {
        fwrite(STDERR, "[FAIL] Production dashboard data missing {$key}.\n");
        exit(1);
    }
    echo "[PASS] production data {$key}\n";
}

ob_start();
StudyDashboardController::index();
$html = ob_get_clean();

if (strpos($html, "Study Dashboard") === false) {
    fwrite(STDERR, "[FAIL] Production study dashboard page did not render.\n");
    exit(1);
}

echo "production_study_html_bytes=" . strlen($html) . "\n";
echo "[PASS] Production study dashboard path executed.\n";
