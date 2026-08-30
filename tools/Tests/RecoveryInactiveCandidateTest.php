<?php
require_once dirname(__DIR__,2).'/app/Core/Autoloader.php'; App\Core\Autoloader::register();
$r=new SelectionRequest('English',null,['easy'=>1],1);
$q=[['id'=>1,'status'=>'archived','subject'=>'English'],['id'=>2,'status'=>'approved','subject'=>'English']];
$out=RecoveryCandidateService::candidates($q,$r,RecoveryScope::Subject);
if(array_column($out,'id')!==[2]){throw new RuntimeException('inactive candidate leaked into recovery');}
echo "[PASS] Recovery excludes inactive candidates.\n";
