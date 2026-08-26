<?php
declare(strict_types=1);
require_once dirname(__DIR__,2)."/app/Core/Autoloader.php";
\App\Core\Autoloader::register();
assert(\App\Services\Quality\Validators\Choice\ChoiceDuplicateDetector::validate(["A","B"])===[]); assert(count(\App\Services\Quality\Validators\Choice\ChoiceDuplicateDetector::validate(["A","A"]))===1);
echo "[PASS] ChoiceDuplicateDetectorTest.php assertions verified.\n";
