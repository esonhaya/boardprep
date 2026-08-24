<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . "/app/Core/Autoloader.php";
\App\Core\Autoloader::register();

$summary = [
    "score" => 4,
    "total" => 10,
    "percentage" => 40,
];

$review = [];
$actions = [
    [
        "label" => "Practice this again",
        "reason" => "Your score shows this area needs more practice.",
        "url" => "/quiz?action=start&topic=Grammar",
    ],
    [
        "label" => "Back to Study Dashboard",
        "reason" => "Use your study plan and recommendations for the next step.",
        "url" => "/study",
    ],
    [
        "label" => "View Progress",
        "reason" => "See how this result changed your learning history.",
        "url" => "/progress",
    ],
];

ob_start();
include dirname(__DIR__, 2) . "/app/Views/quiz/result.php";
$html = (string) ob_get_clean();

$checks = [
    "result heading" => strpos($html, "Quiz Result") !== false,
    "score shown" => strpos($html, "40%") !== false,
    "next steps shown" => strpos($html, "What Next?") !== false,
    "practice action shown" => strpos($html, "Practice this again") !== false,
    "study action shown" => strpos($html, "/study") !== false,
    "progress action shown" => strpos($html, "/progress") !== false,
    "review section shown" => strpos($html, "Answer Review") !== false,
];

foreach ($checks as $label => $passed) {
    if (!$passed) {
        fwrite(STDERR, "[FAIL] {$label}\n");
        exit(1);
    }
    echo "[PASS] {$label}\n";
}

echo "[PASS] Quiz result view contract verified. Assertions: "
    . count($checks) . "\n";
