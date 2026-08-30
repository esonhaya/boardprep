<?php
require_once dirname(__DIR__,2).'/app/Core/Autoloader.php'; App\Core\Autoloader::register();
use App\Services\Quiz\QuizLearningContextService;
$q=[['taxonomy'=>['topic_id'=>'Algebra']],['taxonomy'=>['topic_id'=>'Geometry']],['taxonomy'=>['topic_id'=>'Algebra']]];
$r=QuizLearningContextService::enrichAttempt([],[],$q);
if($r['topics']!==['Algebra','Geometry']||$r['topic']!=='Algebra'){throw new RuntimeException('multi-topic preservation failed');}
echo "[PASS] Multiple canonical topics are preserved without duplication.\n";
