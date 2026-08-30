<?php
require_once dirname(__DIR__,2).'/app/Core/Autoloader.php'; App\Core\Autoloader::register();
use App\Core\App; use App\Repositories\QuestionRepository; use App\Services\Question\QuestionDuplicateService;
$repo=new class extends QuestionRepository { public function __construct(){} public function all(): array { return [['id'=>'7','question'=>'Keep me'],['id'=>'8','question'=>'Other']]; } };
App::container()->bind(QuestionRepository::class,static fn()=>$repo);
if(QuestionDuplicateService::find(['id'=>7,'question'=>' keep   me '])!==[]){throw new RuntimeException('question detected itself as duplicate during update');}
echo "[PASS] Question duplicate scan exempts current update record.\n";
