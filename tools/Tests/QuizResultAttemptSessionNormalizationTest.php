<?php
require_once dirname(__DIR__,2).'/app/Core/Autoloader.php'; App\Core\Autoloader::register();
$_SESSION = [];
$r=QuizResultAttemptFactory::create(['id'=>['bad'],'subject'=>['bad']],[],[]);
if($r['session_id']!==''||!str_starts_with($r['user_id'],'learner-')||$r['subject']!=='') throw new RuntimeException('session normalization failed');
echo "[PASS] Result attempt normalizes malformed session metadata.\n";
