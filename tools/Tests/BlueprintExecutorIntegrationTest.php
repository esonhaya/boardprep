<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . "/app/Core/Autoloader.php";
\App\Core\Autoloader::register();

$question = static fn(int $id): array => [
    "id" => $id,
    "subject" => "English",
    "domain" => "Grammar",
    "status" => "approved",
    "difficulty" => "easy",
    "question" => "Production-selectable question {$id}?",
    "choices" => ["Yes", "No"],
    "answer" => "Yes",
    "explanation" => "A complete explanation.",
];
$questions = [$question(1), $question(2)];
$board = ["version" => 1, "subjects" => [["subject" => "English", "percentage" => 100]]];
$subjects = ["English" => [
    "version" => 2,
    "domains" => [["domain" => "Grammar", "percentage" => 100]],
    "difficulty" => ["easy" => 100],
]];
$spec = new QuizSpecification(
    board: "LET", subject: "English", domain: null, topics: [], concepts: [],
    difficulty: "mixed", questionCount: 2, mode: "practice", adaptive: false,
    shuffle: true, boardBlueprintVersion: null, subjectBlueprintVersion: null
);

$result = BlueprintExecutor::execute($questions, $board, $subjects, $spec);

assert($result instanceof BlueprintExecutionResult);
assert(count($result->questions) === 2);
assert($result->boardBlueprintVersion === 1);
assert($result->subjectBlueprintVersion === 2);

echo "[PASS] Blueprint executor production integration verified.\n";
