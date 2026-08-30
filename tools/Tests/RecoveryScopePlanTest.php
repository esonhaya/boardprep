<?php
require_once dirname(__DIR__,2).'/app/Core/Autoloader.php'; App\Core\Autoloader::register();
$r=new SelectionRequest('English','Grammar',['easy'=>2],2,'Parts','Nouns');
$v=array_map(fn($s)=>$s->value,RecoveryScopePlan::forRequest($r));
if($v!==['concept','topic','domain','subject']){throw new RuntimeException('hierarchy plan failed');}
$r2=new SelectionRequest('English',null,['easy'=>2],2);
$v2=array_map(fn($s)=>$s->value,RecoveryScopePlan::forRequest($r2));
if($v2!==['subject']){throw new RuntimeException('minimal hierarchy plan failed');}
echo "[PASS] Recovery scope plan verified.\n";
