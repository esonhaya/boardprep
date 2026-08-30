<?php
$root=dirname(__DIR__,2);
$s=file_get_contents($root.'/app/Services/Quiz/Result/QuizResultAttemptFactory.php');
if(substr_count($s,"\n")>55) throw new RuntimeException('factory remains too large');
foreach(['AttemptSessionContext','AttemptQuestionSet','AttemptScoreSummary','AttemptRecordFactory'] as $name) if(strpos($s,$name)===false) throw new RuntimeException("missing collaborator: $name");
echo "[PASS] Result attempt factory maintainability boundary verified.\n";
