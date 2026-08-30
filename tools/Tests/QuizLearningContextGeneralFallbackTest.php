<?php
require_once dirname(__DIR__,2).'/app/Core/Autoloader.php'; App\Core\Autoloader::register();
use App\Services\Quiz\QuizLearningContextService;
$r=QuizLearningContextService::enrichAttempt([],[],[]);
if($r['topic']!=='General'||$r['topics']!==[]){throw new RuntimeException('general fallback failed');}
echo "[PASS] Empty context falls back to General without fabricated topics.\n";
