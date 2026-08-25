<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/Core/Autoloader.php';
\App\Core\Autoloader::register();

$spec = new \QuizSpecification(
    board: "LET",
    subject: "English",
    domain: null,
    topics: [],
    concepts: [],
    difficulty: "mixed",
    questionCount: 2,
    mode: "practice",
    adaptive: false,
    shuffle: true,
    boardBlueprintVersion: null,
    subjectBlueprintVersion: null
);

$questions = [
    ["id" => 1, "topic" => "Grammar"],
    ["id" => 2, "topic" => "Vocabulary"],
];

assert(\AdaptiveQuizService::prioritize($questions, $spec) === $questions);
assert(method_exists(\AdaptiveQuizService::class, "prioritize"));

echo "[PASS] Adaptive quiz production contract verified.\n";
