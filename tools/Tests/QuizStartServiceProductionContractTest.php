<?php
$root=dirname(__DIR__,2); $source=file_get_contents($root.'/app/Services/Quiz/QuizStartService.php');
$required=['QuizStartPreparationService::prepare','QuizStartSessionWriter::write','QuizNavigationService::reset','QuizStartViewModelFactory::create','Response::redirect'];
foreach($required as $needle){if(strpos($source,$needle)===false){throw new RuntimeException('Missing production call: '.$needle);}}
if(substr_count($source,"SessionService::set")>0){throw new RuntimeException('Session mutation leaked back into orchestrator');}
echo "[PASS] QuizStartService production orchestration contract verified.\n";
