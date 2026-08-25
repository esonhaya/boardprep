<?php

declare(strict_types=1);

$path = dirname(__DIR__, 2) . "/app/Services/Quiz/Blueprint/BlueprintDistributionService.php";
$source = file_get_contents($path);

assert(is_string($source));
assert(str_contains($source, "BlueprintDistributionRequestNormalizer::normalize"));
assert(str_contains($source, "BlueprintDistributionAllocator::allocate"));
assert(str_contains($source, "BlueprintDistributionResultFactory::create"));

echo "[PASS] Blueprint distribution extraction integration verified.\n";
