<?php
require_once dirname(__DIR__,2).'/app/Core/Autoloader.php'; App\Core\Autoloader::register();
use App\Services\Question\Authoring\QuestionAuthoringDecision;
if(!QuestionAuthoringDecision::allows(['valid'=>true,'errors'=>[],'duplicates'=>[]])){throw new RuntimeException('clean authoring result rejected');}
if(QuestionAuthoringDecision::allows(['valid'=>true,'errors'=>[],'duplicates'=>[['id'=>1]]])){throw new RuntimeException('duplicate authoring result allowed');}
if(QuestionAuthoringDecision::allows(['valid'=>false,'errors'=>['bad'],'duplicates'=>[]])){throw new RuntimeException('invalid authoring result allowed');}
echo "[PASS] Question authoring save decision verified.\n";
