<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . "/app/Core/Autoloader.php";
\App\Core\Autoloader::register();

$requests = [
    new \SelectionRequest("English", "Grammar", [], 3),
    new \SelectionRequest("Science", "Biology", [], 4),
];

assert(\BlueprintAllocationTotalCalculator::calculate($requests) === 7);
echo "[PASS] Blueprint allocation total calculator assertions verified.\n";
