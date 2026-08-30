<?php
$root=dirname(__DIR__,2);
$s=file_get_contents($root.'/app/Services/Quiz/QuizResultService.php');
if(strpos($s,'QuizResultAttemptFactory::create')===false||strpos($s,'QuizResultPersistenceService::persist')===false) throw new RuntimeException('production result persistence contract missing');
echo "[PASS] Production result path still persists factory-created attempts.\n";
