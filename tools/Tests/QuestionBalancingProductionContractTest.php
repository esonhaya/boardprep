<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/Core/Autoloader.php';
\App\Core\Autoloader::register();

$questions = [
    ["id" => 1, "topic" => "Grammar", "difficulty" => "easy"],
    ["id" => 2, "topic" => "Vocabulary", "difficulty" => "easy"],
    ["id" => 3, "topic" => "Grammar", "difficulty" => "hard"],
];

$result = \QuestionBalancingService::balance($questions, ["difficulty" => "easy"]);

assert(count($result) === 2);
assert(array_column($result, "id") === [1, 2] || array_column($result, "id") === [2, 1]);

echo "[PASS] Question balancing production contract verified.\n";
