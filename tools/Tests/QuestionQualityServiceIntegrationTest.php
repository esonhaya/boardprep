<?php
require_once dirname(__DIR__,2).'/app/Core/Autoloader.php'; App\Core\Autoloader::register();
use App\Services\Question\QuestionQualityService;
$r=QuestionQualityService::analyze();
foreach(['report','healthScore','issues','issueGroups','issueGroupLabels','severitySummary','missingChoices','duplicateChoices'] as $key){if(!array_key_exists($key,$r)){throw new RuntimeException('missing quality output key: '.$key);}}
if($r['issues']!==$r['report']->issues){throw new RuntimeException('quality output detached from repository health report');}
echo "[PASS] Question quality production integration verified.\n";
