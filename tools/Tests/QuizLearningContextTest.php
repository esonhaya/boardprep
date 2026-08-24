<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . "/app/Core/Autoloader.php";
\App\Core\Autoloader::register();

use App\Services\Quiz\QuizLearningContextService;

$attempt = QuizLearningContextService::enrichAttempt(
    [
        "subject" => "English",
        "mode" => "practice",
        "percentage" => 60,
    ],
    [
        "subject" => "English",
        "mode" => "practice",
        "difficulty" => "mixed",
        "topics" => ["Grammar"],
    ]
);

$checks = [
    "topic persisted" => ($attempt["topic"] ?? "") === "Grammar",
    "topics persisted" => ($attempt["topics"] ?? []) === ["Grammar"],
    "learning context present" => isset($attempt["learning_context"]),
    "context topic" =>
        ($attempt["learning_context"]["topic"] ?? "") === "Grammar",
    "context subject" =>
        ($attempt["learning_context"]["subject"] ?? "") === "English",
    "context mode" =>
        ($attempt["learning_context"]["mode"] ?? "") === "practice",
    "context difficulty" =>
        ($attempt["learning_context"]["difficulty"] ?? "") === "mixed",
];

foreach ($checks as $label => $passed) {
    if (!$passed) {
        fwrite(STDERR, "[FAIL] {$label}\n");
        exit(1);
    }
    echo "[PASS] {$label}\n";
}

$questionFallback = QuizLearningContextService::enrichAttempt(
    [],
    ["subject" => "English"],
    [["topic" => "Reading"]]
);

if (($questionFallback["topic"] ?? "") !== "Reading") {
    fwrite(STDERR, "[FAIL] question topic fallback\n");
    exit(1);
}

echo "[PASS] question topic fallback\n";

$generalFallback = QuizLearningContextService::enrichAttempt([], [], []);

if (($generalFallback["topic"] ?? "") !== "General") {
    fwrite(STDERR, "[FAIL] General fallback\n");
    exit(1);
}

echo "[PASS] General fallback\n";
echo "[PASS] Quiz learning context contract verified. Assertions: "
    . (count($checks) + 2) . "\n";
