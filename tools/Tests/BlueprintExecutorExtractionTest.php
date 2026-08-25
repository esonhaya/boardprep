<?php
declare(strict_types=1);

$source = file_get_contents(
    dirname(__DIR__, 2) . "/app/Services/Quiz/Blueprint/BlueprintExecutor.php"
);

assert(is_string($source));
assert(str_contains($source, "BlueprintRequestPlanBuilder::build"));
assert(str_contains($source, "BlueprintRequestExecutor::execute"));
assert(str_contains($source, "BlueprintCoverageFinalizer::analyze"));
assert(str_contains($source, "BlueprintExecutionResultFactory::create"));

echo "[PASS] Blueprint executor extraction integration verified.\n";
