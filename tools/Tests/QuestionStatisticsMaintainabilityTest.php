<?php
$root=dirname(__DIR__,2);
$service=file_get_contents($root.'/app/Services/Question/QuestionStatisticsService.php');
$updater=file_get_contents($root.'/app/Services/Question/Statistics/QuestionStatisticsUpdater.php');
if(substr_count($service,"\n")>55||substr_count($updater,"\n")>55) throw new RuntimeException('statistics boundary too large');
echo "[PASS] Question statistics maintainability boundary verified.\n";
