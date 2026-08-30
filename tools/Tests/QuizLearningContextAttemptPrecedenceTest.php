<?php
require_once dirname(__DIR__,2).'/app/Core/Autoloader.php'; App\Core\Autoloader::register();
use App\Services\Quiz\QuizLearningContextService;
$r=QuizLearningContextService::enrichAttempt(['subject'=>'Filipino','topic'=>'Wika'],['subject'=>'English','topics'=>['Grammar']],[]);
if($r['subject']!=='Filipino'||$r['topic']!=='Wika'){throw new RuntimeException('attempt precedence failed');}
echo "[PASS] Existing attempt context remains authoritative.\n";
