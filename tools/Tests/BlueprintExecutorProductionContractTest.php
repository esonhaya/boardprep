<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . "/app/Core/Autoloader.php";
\App\Core\Autoloader::register();

$classes = [
    "BlueprintExecutor",
    "BlueprintRequestPlanBuilder",
    "BlueprintRequestExecutor",
    "BlueprintCoverageFinalizer",
    "BlueprintExecutionResultFactory",
];

foreach ($classes as $class) {
    assert(class_exists($class));
}

$method = new ReflectionMethod("BlueprintExecutor", "execute");
assert($method->isStatic());
assert($method->getNumberOfParameters() === 4);

echo "[PASS] Blueprint executor production contract verified.\n";
