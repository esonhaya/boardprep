<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . "/app/Core/Autoloader.php";
\App\Core\Autoloader::register();

$service = new ReflectionClass("BlueprintDistributionService");

assert($service->hasMethod("distribute"));
assert($service->getMethod("distribute")->isStatic());

foreach ([
    "BlueprintDistributionRequestNormalizer",
    "BlueprintDistributionAllocator",
    "BlueprintDistributionResultFactory",
    "BlueprintDistributionDiagnostics",
    "BlueprintDistributionGuard",
] as $class) {
    assert(class_exists($class));
}

echo "[PASS] Blueprint distribution production contract verified.\n";
