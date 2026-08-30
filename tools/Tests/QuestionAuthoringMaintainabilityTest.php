<?php
$root=dirname(__DIR__,2); $service=file_get_contents($root.'/app/Services/Question/QuestionAuthoringService.php'); $controller=file_get_contents($root.'/app/Controllers/QuestionEditorController.php');
if(substr_count($service,'QuestionAuthoringDecision::allows')<2){throw new RuntimeException('authoring decision duplicated outside shared policy');}
if(!str_contains($service,'QuestionAuthoringPersistence::persist')){throw new RuntimeException('authoring persistence not isolated');}
if(substr_count($controller,'QuestionAuthoringService::submit')!==1){throw new RuntimeException('controller save/update do not share submission helper');}
echo "[PASS] Question authoring maintainability boundaries verified.\n";
