<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . "/app/Core/Autoloader.php";
\App\Core\Autoloader::register();

$request = new \SelectionRequest(
    "English",
    "Grammar",
    ["easy" => 2],
    2,
    "parts",
    "sentence"
);

$result = \BlueprintAllocationRequestFactory::withQuestionCount($request, 5);

assert($result->subject === "English");
assert($result->domain === "Grammar");
assert($result->difficultyDistribution === ["easy" => 2]);
assert($result->questionCount === 5);
assert($result->topic === "parts");
assert($result->concept === "sentence");

echo "[PASS] Blueprint allocation request factory assertions verified.\n";
