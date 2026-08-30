<?php
require_once dirname(__DIR__,2).'/app/Core/Autoloader.php'; App\Core\Autoloader::register();
use App\Services\Learning\TopicPerformanceService;
$r=TopicPerformanceService::summarize([['subject'=>'English','topic'=>'General','percentage'=>90],['subject'=>'Science','topic'=>'General','percentage'=>30]]);
if(count($r)!==2){throw new RuntimeException('same topic across subjects was merged');}
echo "[PASS] Topic performance isolates same-named topics by subject.\n";
