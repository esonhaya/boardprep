<?php
require_once dirname(__DIR__,2).'/app/Core/Autoloader.php'; App\Core\Autoloader::register();
use App\Services\Quiz\Start\QuizStartPreparationService;
$q=[]; foreach([['501','easy'],['502','medium'],['503','hard']] as [$id,$d]){$q[]=['id'=>$id,'question'=>'Start test '.$id,'choices'=>['A','B','C','D'],'answer'=>'A','subject'=>'English','domain'=>'Grammar','difficulty'=>$d,'status'=>'approved','taxonomy'=>['subject_id'=>'English','domain_id'=>'Grammar']];}
$p=QuizStartPreparationService::prepare(['exam'=>'LET','subject'=>'English','count'=>2,'difficulty'=>'mixed'],$q);
if($p->specification->questionCount!==2||count($p->questions)>2){throw new RuntimeException('preparation failed');}
echo "[PASS] Quiz start preparation production path verified.\n";
