<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . "/app/Core/Autoloader.php";
\App\Core\Autoloader::register();

use App\Controllers\StudyDashboardController;

if (!class_exists(StudyDashboardController::class)) {
    fwrite(STDERR, "[FAIL] StudyDashboardController does not load.\n");
    exit(1);
}

echo "[PASS] StudyDashboardController loads.\n";

ob_start();
StudyDashboardController::index();
$html = ob_get_clean();

if (strlen($html) < 500) {
    fwrite(STDERR, "[FAIL] Study dashboard output is unexpectedly small.\n");
    exit(1);
}

echo "study_html_bytes=" . strlen($html) . "\n";

foreach ([
    "Study Dashboard",
    "At a Glance",
    "Recommended Next Steps",
    "Focus Areas",
    "Topic Performance",
    "Recent Activity",
] as $marker) {
    if (strpos($html, $marker) === false) {
        fwrite(STDERR, "[FAIL] Missing dashboard marker: {$marker}\n");
        exit(1);
    }

    echo "[PASS] {$marker}\n";
}

echo "[PASS] Production study dashboard rendered.\n";
