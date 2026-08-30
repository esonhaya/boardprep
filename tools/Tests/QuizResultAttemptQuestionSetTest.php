<?php
require_once dirname(__DIR__,2).'/app/Core/Autoloader.php'; App\Core\Autoloader::register();
$r=QuizResultAttemptFactory::create(['id'=>'s1','question_count'=>99,'question_ids'=>['stale']], [['id'=>'q1'],['id'=>'q2']], ['score'=>1,'total'=>2]);
if($r['question_count']!==2||$r['question_ids']!==['q1','q2']) throw new RuntimeException('question set mismatch');
echo "[PASS] Result attempt derives question count and ids from completed questions.\n";
