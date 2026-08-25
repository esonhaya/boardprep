<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . "/app/Core/Autoloader.php";
\App\Core\Autoloader::register();

$question = ["answer" => "Paris", "choices" => ["London", "Paris", "Rome", "Oslo"]];

$correct = QuestionScoreEvaluator::evaluate($question, "B");
$wrong = QuestionScoreEvaluator::evaluate($question, "London");
$empty = QuestionScoreEvaluator::evaluate($question, null);

assert($correct["correct"] === true);
assert($wrong["answered"] === true && $wrong["correct"] === false);
assert($empty["answered"] === false && $empty["correct"] === false);

echo "[PASS] QuestionScoreEvaluator assertions verified.\n";
