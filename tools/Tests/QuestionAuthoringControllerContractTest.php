<?php
$root=dirname(__DIR__,2); $source=file_get_contents($root.'/app/Controllers/QuestionEditorController.php');
if(!str_contains($source,'QuestionAuthoringService::submit')){throw new RuntimeException('editor controller bypasses authoring service');}
if(str_contains($source,'QuestionService::validateForSave')){throw new RuntimeException('editor controller retains duplicate-prone direct validation path');}
if(!str_contains($source,'"duplicates" => $result["duplicates"] ?? []')){throw new RuntimeException('duplicate feedback not returned to authoring form');}
echo "[PASS] Question editor routes saves through authoring service.\n";
