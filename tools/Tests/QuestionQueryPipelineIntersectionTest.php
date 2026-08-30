<?php
require_once dirname(__DIR__,2).'/app/Core/Autoloader.php'; App\Core\Autoloader::register();
use App\Services\Question\Query\QuestionQueryFilters;
use App\Services\Question\Query\QuestionQueryPipeline;
$rows=[
 ['id'=>'a','question'=>'Noun identification','difficulty'=>'easy','taxonomy'=>['domain_id'=>'grammar','topic_id'=>'parts-of-speech','concept_id'=>'noun']],
 ['id'=>'b','question'=>'Verb identification','difficulty'=>'easy','taxonomy'=>['domain_id'=>'grammar','topic_id'=>'parts-of-speech','concept_id'=>'verb']],
 ['id'=>'c','question'=>'Noun identification','difficulty'=>'hard','taxonomy'=>['domain_id'=>'grammar','topic_id'=>'parts-of-speech','concept_id'=>'noun']],
 'malformed'
];
$r=QuestionQueryPipeline::apply($rows,QuestionQueryFilters::from(['search'=>'noun','domain'=>'grammar','difficulty'=>'easy','topic'=>'parts-of-speech']));
if(count($r)!==1||($r[0]['id']??null)!=='a'){throw new RuntimeException('combined query did not produce exact intersection');}
echo "[PASS] Question query pipeline intersection verified.\n";
