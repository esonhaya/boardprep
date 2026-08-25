<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . "/app/Core/Autoloader.php";
\App\Core\Autoloader::register();

$reflection = new ReflectionClass(QuizResultService::class);
$method = $reflection->getMethod("build");

assert($method->isStatic());
assert($method->getNumberOfParameters() === 0);
assert($method->getReturnType()?->getName() === "array");

echo "[PASS] QuizResultService public contract verified.\n";
