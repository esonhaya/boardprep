<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . "/app/Core/Autoloader.php";
\App\Core\Autoloader::register();

use App\Services\Quiz\QuizLearningContextService;

$attempt = QuizLearningContextService::enrichAttempt(
    [
        "subject" => "English",
        "mode" => "practice",
        "difficulty" => "mixed",
        "percentage" => 80,
    ],
    [
        "id" => "quiz-test",
        "subject" => "English",
        "mode" => "practice",
        "difficulty" => "mixed",
        "topics" => ["Grammar"],
    ],
    [
        ["id" => "q1", "topic" => "Grammar"],
    ]
);

$checks = [
    "production attempt has topic" => ($attempt["topic"] ?? "") === "Grammar",
    "production attempt has topics" =>
        ($attempt["topics"] ?? []) === ["Grammar"],
    "production learning context" =>
        ($attempt["learning_context"]["topic"] ?? "") === "Grammar",
];

foreach ($checks as $label => $passed) {
    if (!$passed) {
        fwrite(STDERR, "[FAIL] {$label}\n");
        exit(1);
    }
    echo "[PASS] {$label}\n";
}

echo "[PASS] Quiz learning context production contract verified. Assertions: "
    . count($checks) . "\n";
