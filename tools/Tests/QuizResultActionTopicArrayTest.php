<?php
require_once dirname(__DIR__,2).'/app/Core/Autoloader.php'; App\Core\Autoloader::register();
use App\Services\Quiz\QuizResultActionService;
$r=QuizResultActionService::build(['subject'=>'Science','topics'=>['Biology'],'mode'=>'practice','difficulty'=>'hard','question_count'=>10],['percentage'=>85]);
$url=$r[0]['url']??'';
if(!str_contains($url,'topic=Biology')||!str_contains($url,'difficulty=hard')){throw new RuntimeException('retake array context failed');}
echo "[PASS] Result retake preserves topic-array context.\n";
