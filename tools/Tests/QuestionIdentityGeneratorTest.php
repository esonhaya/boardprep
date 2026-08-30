<?php
require_once dirname(__DIR__,2).'/app/Core/Autoloader.php'; App\Core\Autoloader::register();
use App\Services\Question\Authoring\QuestionIdentityGenerator;
if(QuestionIdentityGenerator::resolve(7,null)!==7){throw new RuntimeException('requested question id not preserved');}
if(QuestionIdentityGenerator::resolve(0,['id'=>'eng-existing'])!=='eng-existing'){throw new RuntimeException('existing question id not preserved');}
$id=QuestionIdentityGenerator::resolve(0,null);
if(!is_string($id)||!str_starts_with($id,'q')||strlen($id)<2){throw new RuntimeException('new question id not generated');}
echo "[PASS] Question authoring identity generation verified.\n";
