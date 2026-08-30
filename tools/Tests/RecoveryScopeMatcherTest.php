<?php
require_once dirname(__DIR__,2).'/app/Core/Autoloader.php'; App\Core\Autoloader::register();
$r=new SelectionRequest('English','Grammar',['easy'=>2],2,'Parts','Nouns');
$c=new RecoveryQuestionContext('active','English','Grammar','Parts','Nouns');
foreach([RecoveryScope::Concept,RecoveryScope::Topic,RecoveryScope::Domain,RecoveryScope::Subject] as $s){if(!RecoveryScopeMatcher::matches($c,$r,$s)){throw new RuntimeException('expected scope match '.$s->value);}}
$wrong=new RecoveryQuestionContext('active','Math','Grammar','Parts','Nouns');
if(RecoveryScopeMatcher::matches($wrong,$r,RecoveryScope::Subject)){throw new RuntimeException('subject widening crossed subject boundary');}
echo "[PASS] Recovery scope matching verified.\n";
