<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . "/app/Core/Autoloader.php";
\App\Core\Autoloader::register();

$result = \BlueprintDistributionRequestNormalizer::normalize([
    ["subject" => " English ", "domain" => "Grammar", "questionCount" => "5"],
    ["subject" => "", "questionCount" => 3],
    ["subject" => "Science", "count" => 4],
    ["subject" => "History", "questionCount" => 0],
]);

assert(count($result) === 2);
assert($result[0]["subject"] === "English");
assert($result[0]["questionCount"] === 5);
assert($result[1]["subject"] === "Science");
assert($result[1]["questionCount"] === 4);

echo "[PASS] Blueprint distribution request normalizer assertions verified.\n";
