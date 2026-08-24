<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . "/app/Core/Autoloader.php";

\App\Core\Autoloader::register();

use App\Services\Quiz\QuizLearningContextService;
use Tools\Doctor\Project\BoardPrep\Checks\ServiceContractCheck;

$checks = ServiceContractCheck::run(
    QuizLearningContextService::class,
    ["enrichAttempt", "topics"]
);

foreach ($checks as $label => $passed) {
    if (!$passed) {
        fwrite(STDERR, "[FAIL] {$label}\n");
        exit(1);
    }

    echo "[PASS] {$label}\n";
}

echo "[PASS] Generic Service Doctor contract verified. Assertions: "
    . count($checks) . "\n";
