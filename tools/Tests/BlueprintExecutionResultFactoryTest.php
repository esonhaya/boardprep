<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . "/app/Core/Autoloader.php";
\App\Core\Autoloader::register();

$result = BlueprintExecutionResultFactory::create(
    [["id" => 1]],
    [],
    [],
    [],
    ["version" => 3],
    ["English" => ["version" => 7]],
    "English"
);

assert($result instanceof BlueprintExecutionResult);
assert($result->questions === [["id" => 1]]);
assert($result->boardBlueprintVersion === 3);
assert($result->subjectBlueprintVersion === 7);

echo "[PASS] Blueprint execution result factory assertions verified.\n";
