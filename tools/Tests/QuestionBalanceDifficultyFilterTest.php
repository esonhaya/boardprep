<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/Core/Autoloader.php';
\App\Core\Autoloader::register();

$questions = [
    ["id" => 1, "difficulty" => "easy"],
    ["id" => 2, "difficulty" => "hard"],
];

assert(count(\QuestionBalanceDifficultyFilter::filter($questions, "mixed")) === 2);
assert(array_column(\QuestionBalanceDifficultyFilter::filter($questions, "easy"), "id") === [1]);

echo "[PASS] Question balance difficulty filter assertions verified.\n";
