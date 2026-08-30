<?php
require_once dirname(__DIR__,2).'/app/Core/Autoloader.php'; App\Core\Autoloader::register();
use App\Services\Quiz\QuizLearningContextService;
$q=[['taxonomy'=>['topic_id'=>'Fractions']],['topic'=>'Legacy']];
$r=QuizLearningContextService::topics([], $q);
if($r!==['Fractions','Legacy']){throw new RuntimeException('topics compatibility failed');}
echo "[PASS] Legacy topics API reads canonical and legacy question shapes.\n";
