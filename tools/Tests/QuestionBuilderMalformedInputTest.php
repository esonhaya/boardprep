<?php
require_once dirname(__DIR__,2).'/app/Core/Autoloader.php'; App\Core\Autoloader::register();
use App\Services\Question\QuestionBuilderService;
$r=QuestionBuilderService::build(0,['question'=>['bad'],'difficulty'=>['bad'],'subject'=>['bad']]);
if(($r['question']??null)!==''||($r['difficulty']??null)!==''||($r['taxonomy']['subject_id']??null)!==''){throw new RuntimeException('malformed input was coerced into persisted text');}
echo "[PASS] Question builder safely rejects non-scalar text inputs.\n";
