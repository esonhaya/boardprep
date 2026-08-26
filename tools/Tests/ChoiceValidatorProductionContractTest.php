<?php
declare(strict_types=1);
require_once dirname(__DIR__,2)."/app/Core/Autoloader.php";
\App\Core\Autoloader::register();
$x=\App\Services\Quality\Validators\ChoiceValidator::validate(["choices"=>[" A","B","B","C"],"answer"=>"D"]); $t=array_column($x,"type"); assert(in_array("choice-whitespace",$t,true)); assert(in_array("duplicate-choices",$t,true)); assert(in_array("invalid-answer",$t,true));
echo "[PASS] ChoiceValidatorProductionContractTest.php assertions verified.\n";
