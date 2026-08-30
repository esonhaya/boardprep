<?php
require_once dirname(__DIR__,2).'/app/Core/Autoloader.php'; App\Core\Autoloader::register();
$q=['status'=>'APPROVED','taxonomy'=>['subject_id'=>'English','domain_id'=>'Grammar','topic_id'=>'Parts','concept_id'=>'Nouns']];
$c=RecoveryQuestionContextFactory::create($q);
if($c->status!=='approved'||$c->subject!=='English'||$c->domain!=='Grammar'||$c->topic!=='Parts'||$c->concept!=='Nouns'){throw new RuntimeException('context resolution failed');}
echo "[PASS] Recovery question context resolution verified.\n";
