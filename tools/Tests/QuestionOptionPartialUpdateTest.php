<?php
require_once dirname(__DIR__,2).'/app/Core/Autoloader.php'; App\Core\Autoloader::register();
use App\Services\Question\Builder\QuestionOptionBuilder;
$existing=['options'=>[
 ['id'=>'option-1','text'=>'One','correct'=>false],
 ['id'=>'option-2','text'=>'Two','correct'=>true],
 ['id'=>'option-3','text'=>'Three','correct'=>false],
 ['id'=>'option-4','text'=>'Four','correct'=>false],
]];
$r=QuestionOptionBuilder::build(['option_1'=>'Updated One'],$existing);
if(($r[0]['text']??'')!=='Updated One'||($r[1]['correct']??false)!==true){throw new RuntimeException('partial option edit lost text or existing correct answer');}
echo "[PASS] Partial option edits preserve the existing correct answer.\n";
