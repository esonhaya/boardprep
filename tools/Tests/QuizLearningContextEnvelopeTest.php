<?php
require_once dirname(__DIR__,2).'/app/Core/Autoloader.php'; App\Core\Autoloader::register();
use App\Services\Quiz\QuizLearningContextService;
$q=[['taxonomy'=>['board_id'=>'LET','subject_id'=>'Science','domain_id'=>'Biology','topic_id'=>'Cells']]];
$r=QuizLearningContextService::enrichAttempt([],['mode'=>'exam','difficulty'=>'hard'],$q);
$e=$r['learning_context']??[];
foreach(['board'=>'LET','subject'=>'Science','domain'=>'Biology','topic'=>'Cells','mode'=>'exam','difficulty'=>'hard'] as $k=>$v){if(($e[$k]??null)!==$v){throw new RuntimeException("learning envelope missing $k");}}
echo "[PASS] Persisted learning context carries the complete quiz context.\n";
