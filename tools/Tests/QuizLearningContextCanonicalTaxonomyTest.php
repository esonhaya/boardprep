<?php
require_once dirname(__DIR__,2).'/app/Core/Autoloader.php'; App\Core\Autoloader::register();
use App\Services\Quiz\QuizLearningContextService;
$q=[['taxonomy'=>['board_id'=>'LET','subject_id'=>'Mathematics','domain_id'=>'Algebra','topic_id'=>'Linear Equations']]];
$r=QuizLearningContextService::enrichAttempt([],[],$q);
if(($r['subject']??'')!=='Mathematics'||($r['domain']??'')!=='Algebra'||($r['topic']??'')!=='Linear Equations'){throw new RuntimeException('canonical taxonomy fallback failed');}
echo "[PASS] Attempt context derives canonical question taxonomy.\n";
