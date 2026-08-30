<?php

declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/app/Core/Autoloader.php';
\App\Core\Autoloader::register();
if (!(class_exists('BlueprintDistributionService') && method_exists('BlueprintDistributionService','distribution'))) { exit("[FAIL] BlueprintDistribution production contract unavailable.\n"); }
echo "[PASS] BlueprintDistribution production contract verified.\n";
