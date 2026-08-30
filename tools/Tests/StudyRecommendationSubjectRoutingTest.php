<?php
require_once dirname(__DIR__,2).'/app/Core/Autoloader.php'; App\Core\Autoloader::register();
use App\Services\Learning\StudyRecommendationService;
$r=StudyRecommendationService::build([1,2,3],[['topic'=>'Algebra','subject'=>'Mathematics','average'=>45]],3);
if(($r[0]['subject']??'')!=='Mathematics'||!str_contains($r[0]['action']??'','subject=Mathematics')){throw new RuntimeException('recommendation routed to wrong subject');}
echo "[PASS] Study recommendation routes to the weak topic subject.\n";
