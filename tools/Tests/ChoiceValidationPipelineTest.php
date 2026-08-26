<?php
declare(strict_types=1);
require_once dirname(__DIR__,2)."/app/Core/Autoloader.php";
\App\Core\Autoloader::register();
$x=\App\Services\Quality\Validators\Choice\ChoiceValidationPipeline::validate(["answer"=>"A"],["A","B","B"]); assert(in_array("duplicate-choices",array_column($x,"type"),true));
echo "[PASS] ChoiceValidationPipelineTest.php assertions verified.\n";
