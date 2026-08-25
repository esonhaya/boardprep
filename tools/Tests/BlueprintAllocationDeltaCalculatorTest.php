<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . "/app/Core/Autoloader.php";
\App\Core\Autoloader::register();

$requests = [
    new \SelectionRequest("English", "Grammar", [], 3),
    new \SelectionRequest("Science", "Biology", [], 4),
];

assert(\BlueprintAllocationDeltaCalculator::calculate($requests, 10) === 3);
assert(\BlueprintAllocationDeltaCalculator::calculate($requests, 5) === -2);
assert(\BlueprintAllocationDeltaCalculator::calculate($requests, 7) === 0);

echo "[PASS] Blueprint allocation delta calculator assertions verified.\n";
