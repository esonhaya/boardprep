<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . "/app/Core/Autoloader.php";
\App\Core\Autoloader::register();

$requests = [
    new \SelectionRequest(
        "English",
        "Grammar",
        ["easy" => 1],
        0,
        "parts",
        "sentence"
    ),
    new \SelectionRequest(
        "Science",
        "Biology",
        ["medium" => 1],
        0,
        "cells",
        "mitosis"
    ),
];

$result = \BlueprintAllocationReconciler::reconcile($requests, 3);

assert($result[0]->topic === "parts");
assert($result[0]->concept === "sentence");
assert($result[1]->topic === "cells");
assert($result[1]->concept === "mitosis");
assert(array_sum(array_map(
    static fn(\SelectionRequest $request): int => $request->questionCount,
    $result
)) === 3);

echo "[PASS] Blueprint allocation reconciler production integration verified.\n";
