<?php
require_once dirname(__DIR__,2).'/app/Core/Autoloader.php'; App\Core\Autoloader::register();
$r=new SelectionRequest('English','Grammar',['easy'=>1],1,'Parts','Nouns');
$q=[['id'=>7,'taxonomy'=>['subject_id'=>'English','domain_id'=>'Grammar','topic_id'=>'Parts','concept_id'=>'Nouns']]];
$out=RecoveryCandidateService::candidates($q,$r,RecoveryScope::Concept);
if(count($out)!==1||($out[0]['id']??null)!==7){throw new RuntimeException('production entry point failed');}
echo "[PASS] RecoveryCandidateService production contract verified.\n";
