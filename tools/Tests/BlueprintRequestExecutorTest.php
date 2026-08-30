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
$request = new SelectionRequest(
    subject: "English",
    domain: "Grammar",
    difficultyDistribution: ["easy" => 2],
    questionCount: 2
);

$selected = BlueprintRequestExecutor::execute($questions, [$request]);

assert(count($selected) === 2);
assert($selected[0]["id"] !== $selected[1]["id"]);

echo "[PASS] Blueprint request executor assertions verified.\n";
