<?php
require_once dirname(__DIR__, 2) . '/app/Core/Autoloader.php'; App\Core\Autoloader::register();
use App\Services\Blueprint\Creation\BlueprintIdGenerator;
if(BlueprintIdGenerator::generate('LET Board','English / Grammar',3)!=='let-board-english-grammar-v3'){throw new RuntimeException('blueprint id normalization failed');}
if(BlueprintIdGenerator::generate('','',1)!=='board-subject-v1'){throw new RuntimeException('blueprint id fallback failed');}
echo "[PASS] Blueprint ID generation verified.\n";
