<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . "/app/Core/Autoloader.php";
\App\Core\Autoloader::register();

$result = \BlueprintDistributionResultFactory::create([
    ["subject" => "English", "questionCount" => 5, "allocationIndex" => 0],
]);

assert($result === [
    ["subject" => "English", "questionCount" => 5],
]);

echo "[PASS] Blueprint distribution result factory assertions verified.\n";
