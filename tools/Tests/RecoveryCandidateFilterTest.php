<?php
require_once dirname(__DIR__,2).'/app/Core/Autoloader.php'; App\Core\Autoloader::register();
$r=new SelectionRequest('English','Grammar',['easy'=>2],2,'Parts','Nouns');
$q=[
 ['id'=>1,'status'=>'active','taxonomy'=>['subject_id'=>'English','domain_id'=>'Grammar','topic_id'=>'Parts','concept_id'=>'Nouns']],
 ['id'=>2,'status'=>'draft','taxonomy'=>['subject_id'=>'English','domain_id'=>'Grammar','topic_id'=>'Parts','concept_id'=>'Nouns']],
 ['id'=>3,'status'=>'active','taxonomy'=>['subject_id'=>'Math','domain_id'=>'Grammar','topic_id'=>'Parts','concept_id'=>'Nouns']]
];
$out=RecoveryCandidateFilter::filter($q,$r,RecoveryScope::Concept);
if(array_column($out,'id')!==[1]){throw new RuntimeException('candidate filter failed');}
echo "[PASS] Recovery candidate filtering verified.\n";
