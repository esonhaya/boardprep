<?php
require_once dirname(__DIR__,2).'/app/Core/Autoloader.php'; App\Core\Autoloader::register();
use App\Services\Learning\TopicPerformanceService;
$r=TopicPerformanceService::summarize([['subject'=>'Mathematics','topic'=>'Algebra','percentage'=>40]]);
if(($r[0]['subject']??'')!=='Mathematics'||($r[0]['topic']??'')!=='Algebra'){throw new RuntimeException('subject/topic context lost');}
echo "[PASS] Topic performance preserves subject context.\n";
