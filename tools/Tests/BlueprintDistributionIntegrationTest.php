<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . "/app/Core/Autoloader.php";
\App\Core\Autoloader::register();

$result = \BlueprintDistributionService::distribute([
    ["subject" => "English", "domain" => "Grammar", "questionCount" => 5],
    ["subject" => "Science", "domain" => "Biology", "questionCount" => 4],
]);

assert(count($result) === 2);
assert($result[0]["subject"] === "English");
assert($result[0]["questionCount"] === 5);
assert($result[0]["allocatedCount"] === 5);
assert($result[1]["subject"] === "Science");
assert($result[1]["allocatedCount"] === 4);

echo "[PASS] Blueprint distribution production integration verified.\n";
