<?php
require_once dirname(__DIR__,2).'/app/Core/Autoloader.php'; App\Core\Autoloader::register();
use App\Services\Question\Query\QuestionSearchMatcher;
$q=['question'=>'Which word is a NOUN?','taxonomy'=>['subject_id'=>'english','domain_id'=>'grammar','topic_id'=>'parts-of-speech','concept_id'=>'noun']];
foreach(['noun','GRAMMAR','parts-of-speech','english'] as $term){if(!QuestionSearchMatcher::matches($q,$term)){throw new RuntimeException('expected search match for '.$term);}}
if(QuestionSearchMatcher::matches($q,'algebra')){throw new RuntimeException('unexpected search match');}
if(!QuestionSearchMatcher::matches(['question'=>['bad'],'taxonomy'=>'bad'],' ')){throw new RuntimeException('blank search must match malformed row safely');}
echo "[PASS] Question search matcher contract verified.\n";
