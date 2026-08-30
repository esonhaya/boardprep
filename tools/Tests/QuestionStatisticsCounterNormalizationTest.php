<?php
require_once dirname(__DIR__,2).'/app/Core/Autoloader.php'; App\Core\Autoloader::register();
use App\Services\Question\Statistics\QuestionStatisticsCounter;
$cases = [[null,0],['bad',0],[-4,0],[' 7 ',7],[3.9,3],[5,5]];
foreach($cases as [$in,$out]) if(QuestionStatisticsCounter::read($in)!==$out) throw new RuntimeException('counter normalization failed');
echo "[PASS] Question statistics counters normalize malformed legacy values.\n";
