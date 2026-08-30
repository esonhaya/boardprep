<?php
require_once dirname(__DIR__,2).'/app/Core/Autoloader.php'; App\Core\Autoloader::register();
use App\Services\Learning\Recommendation\LearningAttemptContext;
$r=LearningAttemptContext::fromAttempt(['subject'=>['bad'],'topic'=>['bad'],'domain'=>'Fallback']);
if(($r['subject']??null)!==''||($r['topic']??'')!=='Fallback'){throw new RuntimeException('malformed values were not handled safely');}
echo "[PASS] Recommendation context tolerates malformed scalar inputs.\n";
