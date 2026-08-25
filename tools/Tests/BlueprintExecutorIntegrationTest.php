<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . "/app/Core/Autoloader.php";
\App\Core\Autoloader::register();

$questions = [
    ["id" => 1, "subject" => "English", "domain" => "Grammar", "status" => "approved", "difficulty" => "easy"],
    ["id" => 2, "subject" => "English", "domain" => "Grammar", "status" => "approved", "difficulty" => "easy"],
];
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
