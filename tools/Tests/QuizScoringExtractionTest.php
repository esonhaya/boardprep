<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . "/app/Core/Autoloader.php";
\App\Core\Autoloader::register();

$questions = [
    ["id" => 1, "question" => "Q1", "answer" => "Paris", "choices" => ["London", "Paris"]],
    ["id" => 2, "question" => "Q2", "answer" => "Rome", "choices" => ["Rome", "Paris"]],
    ["id" => 3, "question" => "Q3", "answer" => "Tokyo", "choices" => ["Tokyo", "Osaka"]],
];

$result = QuizScoringService::calculate($questions, [1 => "B", 2 => "Paris"]);

assert($result["score"] === 1);
assert($result["correct"] === 1);
assert($result["incorrect"] === 1);
assert($result["unanswered"] === 1);
assert($result["total"] === 3);
assert($result["percentage"] === 33);
assert(count($result["results"]) === 3);

assert(QuizScoringService::checkAnswer($questions[0], "B") === true);
assert(QuizScoringService::checkAnswer($questions[0], "London") === false);

echo "[PASS] QuizScoring extraction integration verified.\n";
