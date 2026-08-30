<?php
require_once dirname(__DIR__,2).'/app/Core/Autoloader.php'; App\Core\Autoloader::register();
$r=App\Services\Quiz\Result\Attempt\AttemptQuestionSet::fromQuestions([null,[],['id'=>[]],['id'=>' q1 '],['id'=>'q1']]);
if($r['question_count']!==5||$r['question_ids']!==['q1']) throw new RuntimeException('malformed question handling failed');
echo "[PASS] Result attempt question-id extraction tolerates malformed records.\n";
