<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . "/app/Core/Autoloader.php";
\App\Core\Autoloader::register();

$requests = [
    new \SelectionRequest("English", "Grammar", [], 3),
    new \SelectionRequest("Science", "Biology", [], 2),
];

$result = \BlueprintAllocationDecrementer::apply($requests, -3);

assert($result[0]->questionCount === 1);
assert($result[1]->questionCount === 1);

$threw = false;
try {
    \BlueprintAllocationDecrementer::apply(
        [new \SelectionRequest("English", "Grammar", [], 0)],
        -1
    );
} catch (\InvalidArgumentException $exception) {
    $threw = true;
}

assert($threw);
echo "[PASS] Blueprint allocation decrementer assertions verified.\n";
