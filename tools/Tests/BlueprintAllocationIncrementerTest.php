<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . "/app/Core/Autoloader.php";
\App\Core\Autoloader::register();

$requests = [
    new \SelectionRequest("English", "Grammar", [], 1),
    new \SelectionRequest("Science", "Biology", [], 1),
];

$result = \BlueprintAllocationIncrementer::apply($requests, 3);

assert($result[0]->questionCount === 3);
assert($result[1]->questionCount === 2);

echo "[PASS] Blueprint allocation incrementer assertions verified.\n";
