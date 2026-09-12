<?php
declare(strict_types=1);

$root=dirname(__DIR__,2);
$questions=json_decode((string)file_get_contents($root.'/storage/questions.json'),true,512,JSON_THROW_ON_ERROR);
$metadata=json_decode((string)file_get_contents($root.'/storage/question-reuse-metadata.json'),true,512,JSON_THROW_ON_ERROR);
$eligibility=json_decode((string)file_get_contents($root.'/storage/question-eligibility.json'),true,512,JSON_THROW_ON_ERROR);
$links=json_decode((string)file_get_contents($root.'/storage/study-question-links.json'),true,512,JSON_THROW_ON_ERROR);
$coverage=json_decode((string)file_get_contents($root.'/storage/board-concept-coverage.json'),true,512,JSON_THROW_ON_ERROR);
$sources=json_decode((string)file_get_contents($root.'/storage/sources.json'),true,512,JSON_THROW_ON_ERROR);
$sourceIds=array_fill_keys(array_map(static fn(array $x):string=>(string)$x['id'],$sources),true);
$new=array_values(array_filter($questions,static fn(array $q):bool=>(int)($q['id']??0)>=3100&&(int)($q['id']??0)<=3300));
if(count($new)!==201)throw new RuntimeException('Expected 201 Batch 617 questions');
$seen=[];$metadataBy=[];$linksBy=[];$eligible=[];
foreach($metadata as $row)$metadataBy[(string)($row['question_id']??'')]= $row;
foreach($links as $row)$linksBy[(string)($row['question_id']??'')][]=$row;
foreach($eligibility as $row)foreach($row['question_ids']??[] as $id)$eligible[(string)$id][]=$row;
$conceptCounts=[];$depths=[];$contexts=[];
foreach($new as $q){
    $id=(int)$q['id']; if(isset($seen[$id]))throw new RuntimeException('Duplicate new ID '.$id);$seen[$id]=true;
    if(($q['board']??'')!=='registered-electrical-engineering')throw new RuntimeException('Wrong board '.$id);
    $concept=$q['taxonomy']['concept_id']??'';$allowed=['ree-concept-ree-pro-circuits','ree-concept-ree-pro-ac','ree-concept-ree-pro-machines','ree-concept-ree-pro-power','ree-concept-ree-allied-electronics'];
    if(!in_array($concept,$allowed,true))throw new RuntimeException('Unexpected concept '.$id);
    if(count($q['choices']??[])!==4||!in_array($q['answer'],$q['choices'],true))throw new RuntimeException('Invalid choices/answer '.$id);
    $m=$metadataBy[(string)$id]['reuse']??null;if(!$m||$m['reuse_policy']!=='REVIEW'||!in_array($m['required_depth'],[3,4],true)||!in_array($m['context_mode'],['NEUTRAL','PROFESSIONAL'],true))throw new RuntimeException('Invalid reuse metadata '.$id);
    if(count($linksBy[(string)$id]??[])!==1)throw new RuntimeException('Missing/duplicate study link '.$id);
    foreach($linksBy[(string)$id][0]['source_ids']??[] as $source)if(!isset($sourceIds[$source]))throw new RuntimeException('Invalid source '.$source);
    $hasRee=false;foreach($eligible[(string)$id]??[] as $e)if(in_array('registered-electrical-engineering',$e['exam_ids']??[],true))$hasRee=true;if(!$hasRee)throw new RuntimeException('Missing REE eligibility '.$id);
    $conceptCounts[$concept]=($conceptCounts[$concept]??0)+1;$depths[(string)$m['required_depth']]=($depths[(string)$m['required_depth']??'UNKNOWN']??0)+1;$contexts[$m['context_mode']]=($contexts[$m['context_mode']]??0)+1;
}
$expected=['ree-concept-ree-pro-circuits'=>41,'ree-concept-ree-pro-ac'=>40,'ree-concept-ree-pro-machines'=>42,'ree-concept-ree-pro-power'=>39,'ree-concept-ree-allied-electronics'=>39];if($conceptCounts!==$expected)throw new RuntimeException('Concept allocation mismatch');
$coverageBy=[];foreach($coverage as $row)if(($row['board_id']??'')==='registered-electrical-engineering')$coverageBy[$row['concept_id']]=$row;
foreach(array_keys($expected) as $concept)if(($coverageBy[$concept]['max_depth']??null)!==4)throw new RuntimeException('Coverage depth not calibrated '.$concept);
echo json_encode(['status'=>'PASS','new_questions'=>count($new),'concepts'=>$conceptCounts,'depths'=>$depths,'contexts'=>$contexts,'coverage_rows_calibrated'=>5],JSON_UNESCAPED_SLASHES).PHP_EOL;
