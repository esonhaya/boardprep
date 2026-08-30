<?php
require_once dirname(__DIR__,2).'/app/Core/Autoloader.php'; App\Core\Autoloader::register();
$r=new SelectionRequest('English','Grammar',['easy'=>2],2,'Parts','Nouns');
$result=new SelectionResult([],false,$r);
$pool=[
 ['id'=>1,'taxonomy'=>['subject_id'=>'English','domain_id'=>'Grammar','topic_id'=>'Parts','concept_id'=>'Nouns']],
 ['id'=>2,'taxonomy'=>['subject_id'=>'English','domain_id'=>'Grammar','topic_id'=>'Parts','concept_id'=>'Verbs']],
 ['id'=>3,'taxonomy'=>['subject_id'=>'English','domain_id'=>'Reading','topic_id'=>'Comprehension','concept_id'=>'MainIdea']],
];
$out=ShortageRecoveryService::recover($result,$pool);
if(array_column($out,'id')!==[1,2]){throw new RuntimeException('expected widening to topic before subject');}
echo "[PASS] Hierarchical shortage recovery verified.\n";
