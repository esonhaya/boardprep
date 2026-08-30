<?php
require_once dirname(__DIR__,2).'/app/Core/Autoloader.php'; App\Core\Autoloader::register();
use App\Services\Quiz\QuizResultActionService;
$r=QuizResultActionService::build(['subject'=>'Mathematics','topic'=>'Algebra','mode'=>'practice','difficulty'=>'mixed','question_count'=>5],['percentage'=>40]);
$url=$r[0]['url']??'';
if(!str_contains($url,'topic=Algebra')||!str_contains($url,'subject=Mathematics')){throw new RuntimeException('retake lost singular topic context');}
echo "[PASS] Result retake preserves singular topic and subject.\n";
