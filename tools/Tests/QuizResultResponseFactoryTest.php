<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . "/app/Core/Autoloader.php";
\App\Core\Autoloader::register();

$summary = [
    "score" => 2,
    "total" => 3,
    "percentage" => 67,
    "results" => [["id" => 1], ["id" => 2], ["id" => 3]],
];

$result = QuizResultResponseFactory::create($summary);

assert($result["summary"] === $summary);
assert($result["review"] === $summary["results"]);

echo "[PASS] QuizResultResponseFactory assertions verified.\n";
