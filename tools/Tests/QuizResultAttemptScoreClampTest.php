<?php
require_once dirname(__DIR__,2).'/app/Core/Autoloader.php'; App\Core\Autoloader::register();
$r=QuizResultAttemptFactory::create(['id'=>'s1'],[['id'=>'q1'],['id'=>'q2']],['score'=>9,'total'=>2,'percentage'=>999]);
if($r['score']!==2||$r['total']!==2||$r['percentage']!==100.0) throw new RuntimeException('score clamp failed');
echo "[PASS] Result attempt clamps impossible score summaries.\n";
