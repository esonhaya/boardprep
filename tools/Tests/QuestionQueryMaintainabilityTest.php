<?php
$root=dirname(__DIR__,2);
$service=file_get_contents($root.'/app/Services/Question/QuestionQueryService.php');
$search=file_get_contents($root.'/app/Services/Question/QuestionSearchService.php');
if($service===false||$search===false){throw new RuntimeException('unable to read question query production files');}
if(substr_count($service,'->all()')!==1){throw new RuntimeException('QuestionQueryService must own exactly one repository snapshot read');}
if(str_contains($service,'in_array(')||str_contains($service,'array_filter(')){throw new RuntimeException('QuestionQueryService reintroduced orchestration/filtering internals');}
foreach(['QuestionQueryFilters','QuestionQueryPipeline'] as $name){if(!str_contains($service,$name)){throw new RuntimeException('QuestionQueryService missing '.$name);}}
if(!str_contains($search,'QuestionQueryPipeline')){throw new RuntimeException('QuestionSearchService does not share query pipeline');}
echo "[PASS] Question query maintainability boundaries verified.\n";
