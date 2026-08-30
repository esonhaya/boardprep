<?php
require_once dirname(__DIR__,2).'/app/Core/Autoloader.php'; App\Core\Autoloader::register();
$r=App\Services\Quiz\Result\QuizAnswerStatisticsPlan::build([['id'=>'q1','answer'=>'A']],[]);
if(count($r)!==1||$r[0]['correct']!==null) throw new RuntimeException('unanswered plan failed');
echo "[PASS] Statistics plan distinguishes unanswered questions.\n";
