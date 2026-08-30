<?php
require_once dirname(__DIR__,2).'/app/Core/Autoloader.php'; App\Core\Autoloader::register();
use App\Core\App; use App\Repositories\QuestionRepository; use App\Services\Question\QuestionAuthoringService;
$existing=['id'=>7,'status'=>'approved','source'=>'manual','taxonomy'=>['board_id'=>'LET','subject_id'=>'English','domain_id'=>'Grammar','topic_id'=>'Parts','concept_id'=>'Nouns'],'difficulty'=>'medium','type'=>'multiple_choice','question'=>'Old wording','options'=>[['id'=>'option-1','text'=>'A','correct'=>true],['id'=>'option-2','text'=>'B','correct'=>false],['id'=>'option-3','text'=>'C','correct'=>false],['id'=>'option-4','text'=>'D','correct'=>false]],'explanation'=>'Existing explanation text.','createdAt'=>'2026-01-01T00:00:00+00:00'];
$input=['question'=>'Updated wording for the noun question','option_1'=>'Teacher','option_2'=>'Run','option_3'=>'Quickly','option_4'=>'Blue','correct_option'=>'option-1','explanation'=>'Teacher is the noun in this list.'];
$repo=new class($existing) extends QuestionRepository { public int $updates=0; private array $existing; public function __construct(array $e){$this->existing=$e;} public function find(string $id): ?array{return $this->existing;} public function all(): array{return [$this->existing];} public function update(string $id,array $data): ?array{$this->updates++; return $data;} };
App::container()->bind(QuestionRepository::class,static fn()=>$repo);
$r=QuestionAuthoringService::submit(7,$input);
if(($r['saved']??false)!==true||$repo->updates!==1){throw new RuntimeException('clean authoring update did not persist once');}
if(($r['persisted']['createdAt']??null)!=='2026-01-01T00:00:00+00:00'){throw new RuntimeException('update lost creation metadata');}
echo "[PASS] Question authoring update preserves identity metadata and persists once.\n";
