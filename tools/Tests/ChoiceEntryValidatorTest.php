<?php
declare(strict_types=1);
require_once dirname(__DIR__,2)."/app/Core/Autoloader.php";
\App\Core\Autoloader::register();
$x=\App\Services\Quality\Validators\Choice\ChoiceEntryValidator::validate(["  "," Valid "]); assert($x[0]["type"]==="empty-choice"); assert($x[1]["type"]==="choice-whitespace");
echo "[PASS] ChoiceEntryValidatorTest.php assertions verified.\n";
