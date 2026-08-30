<?php
require_once dirname(__DIR__,2).'/app/Core/Autoloader.php'; App\Core\Autoloader::register();
use App\Services\Question\Builder\QuestionTaxonomyResolver;
$r=QuestionTaxonomyResolver::resolve(['subject'=>'english','domain'=>'grammar','topic'=>'parts-of-speech','concept'=>'parts-of-speech-nouns']);
$expected=['board_id'=>'let','subject_id'=>'english','domain_id'=>'grammar','topic_id'=>'parts-of-speech','concept_id'=>'parts-of-speech-nouns'];
if($r!==$expected){throw new RuntimeException('context taxonomy was not resolved to canonical hierarchy: '.json_encode($r));}
echo "[PASS] Scoped workspace taxonomy resolves to canonical persisted hierarchy.\n";
