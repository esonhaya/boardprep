<?php
declare(strict_types=1);
require_once dirname(__DIR__,2)."/app/Core/Autoloader.php";
\App\Core\Autoloader::register();
assert(count(\App\Services\Quality\Validators\Choice\ChoiceCountValidator::validate([1,2,3]))===1); assert(\App\Services\Quality\Validators\Choice\ChoiceCountValidator::validate([1,2,3,4])===[]);
echo "[PASS] ChoiceCountValidatorTest.php assertions verified.\n";
