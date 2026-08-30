<?php
require_once dirname(__DIR__,2).'/app/Core/Autoloader.php'; App\Core\Autoloader::register();
$r=QuizResultAttemptFactory::create(['id'=>'s1'],[],['score'=>4,'total'=>0]);
if($r['score']!==0||$r['total']!==0||$r['percentage']!==0.0) throw new RuntimeException('zero total failed');
echo "[PASS] Result attempt handles zero-total summaries safely.\n";
