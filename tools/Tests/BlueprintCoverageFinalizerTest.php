<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . "/app/Core/Autoloader.php";
\App\Core\Autoloader::register();

$coverage = BlueprintCoverageFinalizer::analyze(
    [["id" => 1, "subject" => "English", "domain" => "Grammar"]],
    [],
    [],
    [new SelectionRequest(
        subject: "English",
        domain: "Grammar",
        difficultyDistribution: ["easy" => 100],
        questionCount: 1
    )]
);

assert(is_array($coverage));
assert(BlueprintCoverageFinalizer::validate($coverage) === []);

echo "[PASS] Blueprint coverage finalizer assertions verified.\n";
