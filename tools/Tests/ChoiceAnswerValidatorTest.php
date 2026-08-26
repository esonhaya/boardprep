<?php
declare(strict_types=1);
require_once dirname(__DIR__,2)."/app/Core/Autoloader.php";
\App\Core\Autoloader::register();
assert(\App\Services\Quality\Validators\Choice\ChoiceAnswerValidator::validate(["answer"=>"A"],["A","B"])===[]); assert(count(\App\Services\Quality\Validators\Choice\ChoiceAnswerValidator::validate(["answer"=>"C"],["A","B"]))===1);
echo "[PASS] ChoiceAnswerValidatorTest.php assertions verified.\n";
