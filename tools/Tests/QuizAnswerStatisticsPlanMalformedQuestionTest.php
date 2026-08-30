<?php
require_once dirname(__DIR__,2).'/app/Core/Autoloader.php'; App\Core\Autoloader::register();
$r=App\Services\Quiz\Result\QuizAnswerStatisticsPlan::build([null,[],['id'=>[]],['id'=>' q1 ','answer'=>'A']],['q1'=>'A']);
if(count($r)!==1||$r[0]['question_id']!=='q1') throw new RuntimeException('malformed question handling failed');
echo "[PASS] Statistics plan skips malformed question records safely.\n";
