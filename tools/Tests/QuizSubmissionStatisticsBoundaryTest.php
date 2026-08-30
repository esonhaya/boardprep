<?php
$root=dirname(__DIR__,2);
$source=file_get_contents($root.'/app/Services/Quiz/QuizSubmissionService.php');
if(strpos($source,'QuestionStatisticsService')!==false||strpos($source,'QuizAnswerStatisticsRecorder')!==false) throw new RuntimeException('statistics must not run per submit click');
echo "[PASS] Submission clicks do not directly inflate persisted statistics.\n";
