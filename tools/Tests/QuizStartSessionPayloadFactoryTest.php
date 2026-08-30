<?php
require_once dirname(__DIR__,2).'/app/Core/Autoloader.php'; App\Core\Autoloader::register();
use App\Services\Quiz\Start\QuizStartSpecificationFactory; use App\Services\Quiz\Start\QuizStartSessionPayloadFactory;
$s=QuizStartSpecificationFactory::create(['exam'=>'LET','subject'=>'English','topic'=>'Grammar','count'=>2]);
$p=QuizStartSessionPayloadFactory::create($s,[['id'=>1],['id'=>2]]);
if(!str_starts_with($p['id'],'quiz-')||$p['question_count']!==2||$p['question_ids']!==['1','2']||$p['subject']!=='English'){throw new RuntimeException('payload failed');}
echo "[PASS] Quiz start session payload verified.\n";
