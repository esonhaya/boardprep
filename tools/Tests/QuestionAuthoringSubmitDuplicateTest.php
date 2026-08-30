<?php
require_once dirname(__DIR__,2).'/app/Core/Autoloader.php'; App\Core\Autoloader::register();
use App\Core\App; use App\Repositories\QuestionRepository; use App\Services\Question\QuestionAuthoringService;
$input=['board_id'=>'LET','subject_id'=>'English','domain_id'=>'Grammar','topic_id'=>'Parts','concept_id'=>'Nouns','difficulty'=>'medium','type'=>'multiple_choice','question'=>'Which word is a noun?','option_1'=>'Quickly','option_2'=>'Teacher','option_3'=>'Beautiful','option_4'=>'Run','correct_option'=>'option-2','explanation'=>'Teacher is a noun.'];
$repo=new class extends QuestionRepository { public int $creates=0; public function __construct(){} public function all(): array { return [['id'=>'42','question'=>'  WHICH word is a noun?  ']]; } public function create(array $data): array {$this->creates++; return $data+['id'=>'99'];} };
App::container()->bind(QuestionRepository::class,static fn()=>$repo);
$r=QuestionAuthoringService::submit(0,$input);
if(($r['saved']??true)!==false||empty($r['duplicates'])){throw new RuntimeException('duplicate submission not blocked');}
if($repo->creates!==0){throw new RuntimeException('duplicate submission reached persistence');}
echo "[PASS] Question authoring blocks duplicate create before persistence.\n";
