<?php
require_once dirname(__DIR__, 2) . '/app/Core/Autoloader.php'; App\Core\Autoloader::register();
use App\Services\Blueprint\Creation\BlueprintCreationInput; use App\Services\Blueprint\Creation\BlueprintRecordFactory; use App\Services\Shared\BlueprintValidator;
$record=BlueprintRecordFactory::build(BlueprintCreationInput::from(['board'=>'LET','subject'=>'English','name'=>'','questionCount'=>0,'easy'=>50,'medium'=>30,'hard'=>10]),1);
$v=BlueprintValidator::validate($record); if($v['valid']||count($v['errors'])<3){throw new RuntimeException('canonical creation record bypasses expected validation');}
echo "[PASS] Blueprint creation and validator contract aligned.\n";
