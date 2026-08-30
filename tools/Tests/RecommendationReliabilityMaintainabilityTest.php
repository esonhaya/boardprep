<?php
$files=['app/Services/Learning/TopicPerformanceService.php','app/Services/Learning/StudyRecommendationService.php','app/Services/Quiz/QuizResultActionService.php'];
foreach($files as $file){$text=file_get_contents(dirname(__DIR__,2).'/'.$file); if($text===false||substr_count($text,"\n")>120){throw new RuntimeException("maintainability boundary exceeded: {$file}");}}
echo "[PASS] Recommendation reliability maintainability boundaries verified.\n";
