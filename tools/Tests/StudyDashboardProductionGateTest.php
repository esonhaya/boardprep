<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . "/app/Core/Autoloader.php";
\App\Core\Autoloader::register();

use App\Controllers\StudyDashboardController;

$data = StudyDashboardController::data();

foreach ([
    "progress",
    "topics",
    "weakestTopics",
    "streak",
    "insight",
    "recommendations",
    "studyPlan",
] as $key) {
    if (!array_key_exists($key, $data)) {
        fwrite(STDERR, "[FAIL] production data {$key}\n");
        exit(1);
    }
    echo "[PASS] production data {$key}\n";
}

if (
    empty($data["studyPlan"]) ||
    !str_starts_with(
        (string) ($data["studyPlan"][0]["action"] ?? ""),
        "/quiz?"
    )
) {
    fwrite(STDERR, "[FAIL] production study plan action\n");
    exit(1);
}
echo "[PASS] production study plan action\n";

ob_start();
StudyDashboardController::index();
$html = (string) ob_get_clean();

if ($html === "") {
    fwrite(STDERR, "[FAIL] production dashboard rendered\n");
    exit(1);
}

foreach ([
    "Study Dashboard",
    "Today's Study Plan",
    "Recommended Next Steps",
    "Focus Areas",
    "Topic Performance",
] as $marker) {
    if (strpos($html, $marker) === false) {
        fwrite(STDERR, "[FAIL] production marker {$marker}\n");
        exit(1);
    }
    echo "[PASS] production marker {$marker}\n";
}

echo "production_study_html_bytes=" . strlen($html) . "\n";
echo "[PASS] Production study dashboard rendered.\n";
