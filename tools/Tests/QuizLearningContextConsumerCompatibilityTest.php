<?php
require_once dirname(__DIR__,2).'/app/Core/Autoloader.php'; App\Core\Autoloader::register();
use App\Services\Quiz\QuizLearningContextService;
use App\Services\Learning\Recommendation\LearningAttemptContext;
$r=QuizLearningContextService::enrichAttempt([],[],[['taxonomy'=>['subject_id'=>'Mathematics','topic_id'=>'Algebra']]]);
$c=LearningAttemptContext::fromAttempt($r);
if($c!==['subject'=>'Mathematics','topic'=>'Algebra']){throw new RuntimeException('learning consumer compatibility failed');}
echo "[PASS] Persisted context feeds study recommendation consumers correctly.\n";
