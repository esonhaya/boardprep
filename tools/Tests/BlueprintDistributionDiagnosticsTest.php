<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . "/app/Core/Autoloader.php";
\App\Core\Autoloader::register();

$result = \BlueprintDistributionDiagnostics::summarize([
    ["subject" => "English", "questionCount" => 5],
    ["subject" => "Science", "questionCount" => 4],
    ["subject" => "English", "questionCount" => 2],
]);

assert($result["request_count"] === 3);
assert($result["total_questions"] === 11);
assert($result["subjects"] === ["English", "Science"]);

echo "[PASS] Blueprint distribution diagnostics assertions verified.\n";
