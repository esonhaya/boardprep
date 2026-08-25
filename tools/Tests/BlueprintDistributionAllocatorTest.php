<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . "/app/Core/Autoloader.php";
\App\Core\Autoloader::register();

$result = \BlueprintDistributionAllocator::allocate([
    ["subject" => "English", "questionCount" => 5],
    ["subject" => "Science", "questionCount" => 4],
]);

assert(count($result) === 2);
assert($result[0]["allocationIndex"] === 0);
assert($result[0]["allocatedCount"] === 5);
assert($result[1]["allocationIndex"] === 1);
assert($result[1]["allocatedCount"] === 4);

echo "[PASS] Blueprint distribution allocator assertions verified.\n";
