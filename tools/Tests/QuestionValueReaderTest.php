<?php
require_once dirname(__DIR__,2).'/app/Core/Autoloader.php'; App\Core\Autoloader::register();
use App\Services\Question\Query\QuestionValueReader;
$q=['difficulty'=>['bad'],'taxonomy'=>['topic_id'=>123,'domain_id'=>['bad']]];
if(QuestionValueReader::text($q,'difficulty')!==''||QuestionValueReader::taxonomy($q,'topic_id')!=='123'||QuestionValueReader::taxonomy($q,'domain_id')!==''){throw new RuntimeException('safe question value reading failed');}
echo "[PASS] Question value reader malformed-data handling verified.\n";
