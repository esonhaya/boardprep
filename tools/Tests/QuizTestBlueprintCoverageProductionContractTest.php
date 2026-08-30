<?php

declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/app/Core/Autoloader.php';
\App\Core\Autoloader::register();
if (!(class_exists('BlueprintCoverageAnalyzer') && method_exists('BlueprintCoverageAnalyzer','analyze') && class_exists('BlueprintCoverageValidator') && method_exists('BlueprintCoverageValidator','validate'))) { exit("[FAIL] BlueprintCoverage production contract unavailable.\n"); }
echo "[PASS] BlueprintCoverage production contract verified.\n";
