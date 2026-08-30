<?php
require_once dirname(__DIR__, 2) . '/app/Core/Autoloader.php'; App\Core\Autoloader::register();
use App\Services\Blueprint\Creation\BlueprintCreationInput; use App\Services\Blueprint\Creation\BlueprintRecordFactory;
$b=BlueprintRecordFactory::build(BlueprintCreationInput::from(['board'=>'LET','subject'=>'English','name'=>'LET English','questionCount'=>20,'easy'=>30,'medium'=>50,'hard'=>20]),2);
foreach(['scope'=>'subject','board_id'=>'LET','subject_id'=>'English','status'=>'active','version'=>2] as $k=>$v){if(($b[$k]??null)!==$v){throw new RuntimeException('record factory mismatch: '.$k);}}
if(($b['difficulty']['medium']??null)!==50){throw new RuntimeException('difficulty not preserved');}
echo "[PASS] Blueprint canonical record factory verified.\n";
