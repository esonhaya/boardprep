<?php
declare(strict_types=1);
require_once dirname(__DIR__,2)."/app/Core/Autoloader.php";
\App\Core\Autoloader::register();
assert(\App\Services\Quality\Validators\Choice\ChoiceDuplicateDetector::validate(["A","B"])===[]); assert(count(\App\Services\Quality\Validators\Choice\ChoiceDuplicateDetector::validate(["A","A"]))===1);
assert(\App\Services\Quality\Validators\Choice\ChoiceDuplicateDetector::validate(["  A   answer!", "a answer!"]) !== []);
assert(\App\Services\Quality\Validators\Choice\ChoiceDuplicateDetector::validate(["1.5 kN", "15 kN"]) === []);
assert((\App\Services\Quality\Validators\Choice\ChoiceDuplicateDetector::validate(["A", " a "])[0]['severity'] ?? '') === 'error');
assert(\App\Services\Quality\Validators\Choice\ChoiceDuplicateDetector::validate(["AB-0072", "AB-0072."]) === []);
echo "[PASS] ChoiceDuplicateDetectorTest.php assertions verified.\n";
