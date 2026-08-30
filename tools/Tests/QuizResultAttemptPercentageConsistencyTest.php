<?php
require_once dirname(__DIR__,2).'/app/Core/Autoloader.php'; App\Core\Autoloader::register();
$r=QuizResultAttemptFactory::create(['id'=>'s1'],[['id'=>'q1'],['id'=>'q2'],['id'=>'q3']],['score'=>'2','total'=>'3','percentage'=>1]);
if($r['score']!==2||$r['total']!==3||abs($r['percentage']-66.67)>0.001) throw new RuntimeException('percentage consistency failed');
echo "[PASS] Result attempt recomputes percentage from normalized score and total.\n";
