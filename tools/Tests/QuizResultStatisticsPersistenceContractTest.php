<?php
$root=dirname(__DIR__,2);
$result=file_get_contents($root.'/app/Services/Quiz/QuizResultService.php');
$persist=file_get_contents($root.'/app/Services/Quiz/Result/QuizResultPersistenceService.php');
if(strpos($result,'$answers')===false||strpos($result,'QuizResultPersistenceService::persist')===false) throw new RuntimeException('result answers not forwarded');
if(strpos($persist,'QuizAnswerStatisticsRecorder::record($questions, $answers)')===false) throw new RuntimeException('statistics recorder not wired');
echo "[PASS] Completed-result persistence wires answer statistics once.\n";
