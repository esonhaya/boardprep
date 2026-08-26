<?php
declare(strict_types=1);
require_once dirname(__DIR__,2)."/app/Core/Autoloader.php";
\App\Core\Autoloader::register();
$c=["App\\Services\\Quality\\Validators\\ChoiceValidator","App\\Services\\Quality\\Validators\\Choice\\ChoiceIssueFactory","App\\Services\\Quality\\Validators\\Choice\\ChoiceCountValidator","App\\Services\\Quality\\Validators\\Choice\\ChoiceEntryValidator","App\\Services\\Quality\\Validators\\Choice\\ChoiceDuplicateDetector","App\\Services\\Quality\\Validators\\Choice\\ChoiceAnswerValidator","App\\Services\\Quality\\Validators\\Choice\\ChoiceListReader","App\\Services\\Quality\\Validators\\Choice\\ChoiceValidationPipeline"]; foreach($c as $x) assert(class_exists($x)); assert(\App\Services\Quality\Validators\ChoiceValidator::validate(["choices"=>["A","B","C","D"],"answer"=>"D"])===[]);
echo "[PASS] ChoiceValidatorExtractionTest.php assertions verified.\n";
