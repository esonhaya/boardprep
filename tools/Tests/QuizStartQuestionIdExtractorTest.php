<?php
require_once dirname(__DIR__,2).'/app/Core/Autoloader.php'; App\Core\Autoloader::register();
use App\Services\Quiz\Start\QuizStartQuestionIdExtractor;
$ids=QuizStartQuestionIdExtractor::fromQuestions([['id'=>501],['question'=>'missing'],['id'=>'503']]);
if($ids!==['501','503']){throw new RuntimeException('id extraction failed');}
echo "[PASS] Quiz start question ID extraction verified.\n";
