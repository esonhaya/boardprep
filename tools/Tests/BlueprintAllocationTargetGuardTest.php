<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . "/app/Core/Autoloader.php";
\App\Core\Autoloader::register();

\BlueprintAllocationTargetGuard::validate(0);

$threw = false;
try {
    \BlueprintAllocationTargetGuard::validate(-1);
} catch (\InvalidArgumentException $exception) {
    $threw = true;
}

assert($threw);
echo "[PASS] Blueprint allocation target guard assertions verified.\n";
