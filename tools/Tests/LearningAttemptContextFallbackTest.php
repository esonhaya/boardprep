<?php
require_once dirname(__DIR__,2).'/app/Core/Autoloader.php'; App\Core\Autoloader::register();
use App\Services\Learning\Recommendation\LearningAttemptContext;
$r=LearningAttemptContext::fromAttempt(['learning_context'=>['subject'=>'Filipino','topic'=>'Wika']]);
if(($r['subject']??null)!=='Filipino'||($r['topic']??null)!=='Wika'
    ||($r['board']??null)!==''||($r['civil_service_level']??null)!==''){
    throw new RuntimeException('learning_context fallback failed');
}
echo "[PASS] Attempt context reads persisted learning context fallback.\n";
