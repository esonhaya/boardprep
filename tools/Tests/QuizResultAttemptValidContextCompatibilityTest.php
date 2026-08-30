<?php
require_once dirname(__DIR__,2).'/app/Core/Autoloader.php'; App\Core\Autoloader::register();
$r=QuizResultAttemptFactory::create(['id'=>'quiz-1','board'=>'LET','subject'=>'English','domain'=>'Grammar','mode'=>'practice','difficulty'=>'mixed','started_at'=>'2026-08-30T00:00:00+00:00'],[['id'=>'q1']],['score'=>1,'total'=>1]);
foreach(['session_id'=>'quiz-1','user_id'=>'session:quiz-1','board'=>'LET','subject'=>'English','domain'=>'Grammar','mode'=>'practice','difficulty'=>'mixed'] as $k=>$v) if($r[$k]!==$v) throw new RuntimeException("compatibility failed: $k");
echo "[PASS] Result attempt preserves valid session context.\n";
