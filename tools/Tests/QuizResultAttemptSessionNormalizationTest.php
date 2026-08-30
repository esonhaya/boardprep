<?php
require_once dirname(__DIR__,2).'/app/Core/Autoloader.php'; App\Core\Autoloader::register();
$r=QuizResultAttemptFactory::create(['id'=>['bad'],'subject'=>['bad']],[],[]);
if($r['session_id']!==''||$r['user_id']!==''||$r['subject']!=='') throw new RuntimeException('session normalization failed');
echo "[PASS] Result attempt normalizes malformed session metadata.\n";
