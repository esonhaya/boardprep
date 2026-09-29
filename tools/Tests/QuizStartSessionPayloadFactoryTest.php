<?php
require_once dirname(__DIR__,2).'/app/Core/Autoloader.php'; App\Core\Autoloader::register();
use App\Services\Quiz\Start\QuizStartSpecificationFactory; use App\Services\Quiz\Start\QuizStartSessionPayloadFactory;
$s=QuizStartSpecificationFactory::create(['exam'=>'LET','subject'=>'English','topic'=>'Grammar','count'=>2]);
$p=QuizStartSessionPayloadFactory::create($s,[['id'=>1],['id'=>2]]);
if(!str_starts_with($p['id'],'quiz-')||$p['question_count']!==2||$p['question_ids']!==['1','2']||$p['subject']!=='English'){throw new RuntimeException('payload failed');}
$cse=QuizStartSpecificationFactory::create(['exam'=>'CSE','subject'=>'general-information','civil_service_level'=>'SUBPROFESSIONAL']);
$csePayload=QuizStartSessionPayloadFactory::create($cse,[['id'=>3]]);
if(($csePayload['civil_service_level']??null)!=='SUBPROFESSIONAL'){throw new RuntimeException('Civil Service level was not persisted in the quiz session.');}
$attemptContext=App\Services\Quiz\Result\Attempt\AttemptSessionContext::fromSession($csePayload);
if(($attemptContext['civil_service_level']??null)!=='SUBPROFESSIONAL'){throw new RuntimeException('Civil Service level was not preserved in attempt context.');}
echo "[PASS] Quiz start session payload verified.\n";
