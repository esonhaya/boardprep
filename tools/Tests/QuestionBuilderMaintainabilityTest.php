<?php
$root=dirname(__DIR__,2);
$builder=(string)file_get_contents($root.'/app/Services/Question/QuestionBuilderService.php');
if(substr_count($builder,"\n")>60){throw new RuntimeException('QuestionBuilderService exceeds orchestration boundary');}
foreach(['QuestionTaxonomyResolver::resolve','QuestionOptionBuilder::build','QuestionInputReader::text'] as $needle){if(!str_contains($builder,$needle)){throw new RuntimeException('builder does not delegate '.$needle);}}
echo "[PASS] Question builder maintainability boundary verified.\n";
