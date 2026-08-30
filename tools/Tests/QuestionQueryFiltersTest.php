<?php
require_once dirname(__DIR__,2).'/app/Core/Autoloader.php'; App\Core\Autoloader::register();
use App\Services\Question\Query\QuestionQueryFilters;
$f=QuestionQueryFilters::from(['search'=>'  noun ','domain'=>['bad'],'difficulty'=>null,'topic'=>42]);
if($f->search!=='noun'||$f->domain!==''||$f->difficulty!==''||$f->topic!=='42'){throw new RuntimeException('query filter normalization failed');}
echo "[PASS] Question query filter normalization verified.\n";
