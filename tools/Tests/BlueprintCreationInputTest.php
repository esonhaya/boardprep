<?php
require_once dirname(__DIR__, 2) . '/app/Core/Autoloader.php'; App\Core\Autoloader::register();
use App\Services\Blueprint\Creation\BlueprintCreationInput;
$i=BlueprintCreationInput::from(['board'=>' LET ','subject'=>' English ','name'=>' Test ','questionCount'=>'20','easy'=>'30','medium'=>'50','hard'=>'20']);
if($i->boardId!=='LET'||$i->subjectId!=='English'||$i->name!=='Test'||$i->questionCount!==20){throw new RuntimeException('creation input normalization failed');}
$i=BlueprintCreationInput::from(['board'=>[]]); if($i->boardId!==''){throw new RuntimeException('non-scalar blueprint input must normalize safely');}
echo "[PASS] Blueprint creation input normalization verified.\n";
