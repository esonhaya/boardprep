<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . "/app/Core/Autoloader.php";
\App\Core\Autoloader::register();

use Tools\Doctor\Project\BoardPrep\Checks\StudyWorkflowContractCheck;

$result = StudyWorkflowContractCheck::run();

foreach ($result as $label => $passed) {
    if (!$passed) {
        fwrite(STDERR, "[FAIL] {$label}\n");
        exit(1);
    }

    echo "[PASS] {$label}\n";
}

echo "[PASS] Study workflow Doctor contract verified. Assertions: "
    . count($result)
    . "\n";
