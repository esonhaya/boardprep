<?php

declare(strict_types=1);

$path = dirname(__DIR__, 2) . "/app/Services/Quiz/Blueprint/BlueprintAllocationReconciler.php";
$source = file_get_contents($path);

assert(is_string($source));
assert(str_contains($source, "BlueprintAllocationTargetGuard::validate"));
assert(str_contains($source, "BlueprintAllocationDeltaCalculator::calculate"));
assert(str_contains($source, "BlueprintAllocationIncrementer::apply"));
assert(str_contains($source, "BlueprintAllocationDecrementer::apply"));

echo "[PASS] Blueprint allocation reconciler extraction integration verified.\n";
