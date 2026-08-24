<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . "/app/Core/Autoloader.php";
\App\Core\Autoloader::register();

use App\Controllers\StudyDashboardController;

ob_start();
StudyDashboardController::index();
$html = (string) ob_get_clean();

$checks = [
    "dashboard heading" => strpos($html, "Study Dashboard") !== false,
    "study plan section" => strpos($html, "Today's Study Plan") !== false,
    "insight section" => strpos($html, "Recommended Next Steps") !== false,
    "focus section" => strpos($html, "Focus Areas") !== false,
    "topic section" => strpos($html, "Topic Performance") !== false,
    "history section" => strpos($html, "Recent Activity") !== false,
    "quiz navigation" => strpos($html, 'href="/quiz"') !== false,
    "progress navigation" => strpos($html, 'href="/progress"') !== false,
    "profile navigation" => strpos($html, 'href="/profile"') !== false,
];

foreach ($checks as $label => $passed) {
    if (!$passed) {
        fwrite(STDERR, "[FAIL] {$label}\n");
        exit(1);
    }
    echo "[PASS] {$label}\n";
}

echo "[PASS] Study dashboard view contract verified. Assertions: "
    . count($checks) . "\n";
