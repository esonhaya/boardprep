<?php
require_once dirname(__DIR__,2).'/app/Core/Autoloader.php'; App\Core\Autoloader::register();
use App\Services\Quiz\QuizLearningContextService;
$q=[null,'bad',['taxonomy'=>'bad'],['taxonomy'=>['topic_id'=>['bad']]],['topic'=>'Legacy Topic']];
$r=QuizLearningContextService::enrichAttempt([],[],$q);
if($r['topics']!==['Legacy Topic']){throw new RuntimeException('malformed question handling failed');}
echo "[PASS] Context extraction tolerates malformed question records.\n";
