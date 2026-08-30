<?php
require_once dirname(__DIR__,2).'/app/Core/Autoloader.php'; App\Core\Autoloader::register();
use App\Services\Learning\StudyPlanService;
$r=StudyPlanService::build(['weakestTopics'=>[['topic'=>'Biology','subject'=>'Science','average'=>50]],'recommendations'=>[]]);
if(($r[0]['subject']??'')!=='Science'||!str_contains($r[0]['action']??'','subject=Science')){throw new RuntimeException('study plan lost subject');}
echo "[PASS] Study plan preserves weakness subject routing.\n";
