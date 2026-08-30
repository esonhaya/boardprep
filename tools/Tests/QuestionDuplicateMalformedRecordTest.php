<?php
require_once dirname(__DIR__,2).'/app/Core/Autoloader.php'; App\Core\Autoloader::register();
use App\Core\App; use App\Repositories\QuestionRepository; use App\Services\Question\QuestionDuplicateService;
$repo=new class extends QuestionRepository { public function __construct(){} public function all(): array { return [null,'bad',['id'=>'2','question'=>' Same   question ']]; } };
App::container()->bind(QuestionRepository::class,static fn()=>$repo);
$r=QuestionDuplicateService::find(['id'=>'1','question'=>'same question']);
if(count($r)!==1||($r[0]['id']??null)!=='2'){throw new RuntimeException('duplicate scan failed malformed-record isolation');}
echo "[PASS] Question duplicate scan tolerates malformed records.\n";
