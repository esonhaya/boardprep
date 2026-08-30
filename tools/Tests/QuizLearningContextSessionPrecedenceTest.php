<?php
require_once dirname(__DIR__,2).'/app/Core/Autoloader.php'; App\Core\Autoloader::register();
use App\Services\Quiz\QuizLearningContextService;
$q=[['taxonomy'=>['subject_id'=>'Science','topic_id'=>'Biology']]];
$r=QuizLearningContextService::enrichAttempt([],['subject'=>'Mathematics','topics'=>['Geometry']],$q);
if($r['subject']!=='Mathematics'||$r['topic']!=='Geometry'){throw new RuntimeException('session precedence failed');}
echo "[PASS] Session context takes precedence over question fallback.\n";
