<?php
require_once dirname(__DIR__,2).'/app/Core/Autoloader.php'; App\Core\Autoloader::register();
use App\Services\Question\Query\QuestionFilterMatcher;
use App\Services\Question\Query\QuestionQueryFilters;
$q=['difficulty'=>'medium','taxonomy'=>['domain_id'=>'grammar','topic_id'=>'verbs']];
$f=QuestionQueryFilters::from(['domain'=>'grammar','difficulty'=>'medium','topic'=>'verbs']);
if(!QuestionFilterMatcher::matches($q,$f)){throw new RuntimeException('matching structured filters rejected');}
if(QuestionFilterMatcher::matches($q,QuestionQueryFilters::from(['topic'=>'nouns']))){throw new RuntimeException('mismatched topic accepted');}
echo "[PASS] Question structured filter matcher verified.\n";
