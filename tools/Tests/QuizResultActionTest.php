<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . "/app/Core/Autoloader.php";
\App\Core\Autoloader::register();

use App\Services\Quiz\QuizResultActionService;

$session = [
    "topics" => ["Grammar"],
    "subject" => "English",
    "mode" => "practice",
    "difficulty" => "mixed",
    "question_count" => 10,
];

$actions = QuizResultActionService::build(
    $session,
    ["score" => 4, "total" => 10, "percentage" => 40]
);

$checks = [
    "three actions" => count($actions) === 3,
    "primary action present" => !empty($actions[0]["label"]),
    "primary action has reason" => !empty($actions[0]["reason"]),
    "primary action targets quiz" =>
        str_starts_with((string) ($actions[0]["url"] ?? ""), "/quiz?"),
    "topic carried" =>
        strpos((string) $actions[0]["url"], "topic=Grammar") !== false,
    "subject carried" =>
        strpos((string) $actions[0]["url"], "subject=English") !== false,
    "study dashboard action" => ($actions[1]["url"] ?? "") === "/study",
    "progress action" => ($actions[2]["url"] ?? "") === "/progress",
];

foreach ($checks as $label => $passed) {
    if (!$passed) {
        fwrite(STDERR, "[FAIL] {$label}\n");
        exit(1);
    }
    echo "[PASS] {$label}\n";
}

echo "[PASS] Quiz result action contract verified. Assertions: "
    . count($checks) . "\n";
