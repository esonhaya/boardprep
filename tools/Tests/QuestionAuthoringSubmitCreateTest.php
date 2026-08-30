<?php
require_once dirname(__DIR__,2).'/app/Core/Autoloader.php'; App\Core\Autoloader::register();
use App\Core\App; use App\Repositories\QuestionRepository; use App\Services\Question\QuestionAuthoringService;
$input=['board_id'=>'LET','subject_id'=>'English','domain_id'=>'Grammar','topic_id'=>'Parts','concept_id'=>'Nouns','difficulty'=>'medium','type'=>'multiple_choice','question'=>'Which word names a person?','option_1'=>'Quickly','option_2'=>'Teacher','option_3'=>'Beautiful','option_4'=>'Run','correct_option'=>'option-2','explanation'=>'Teacher names a person.'];
$repo=new class extends QuestionRepository { public int $creates=0; public function __construct(){} public function all(): array { return []; } public function create(array $data): array {$this->creates++; $data['id']='101'; return $data;} };
App::container()->bind(QuestionRepository::class,static fn()=>$repo);
$r=QuestionAuthoringService::submit(0,$input);
if(($r['saved']??false)!==true||($r['persisted']['id']??null)!=='101'||$repo->creates!==1){throw new RuntimeException('clean authoring create did not persist once');}
echo "[PASS] Question authoring persists clean create exactly once.\n";
