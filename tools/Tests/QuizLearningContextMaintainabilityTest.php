<?php
$path=dirname(__DIR__,2).'/app/Services/Quiz/QuizLearningContextService.php';
$source=(string)file_get_contents($path);
if(substr_count($source,"private static function")>3){throw new RuntimeException('service orchestration grew too complex');}
foreach(['QuizAttemptContextResolver','QuizAttemptLearningContextFactory'] as $name){if(!str_contains($source,$name)){throw new RuntimeException("missing collaborator $name");}}
echo "[PASS] Learning-context persistence maintainability boundary verified.\n";
