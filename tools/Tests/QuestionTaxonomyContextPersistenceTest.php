<?php
require_once dirname(__DIR__,2).'/app/Core/Autoloader.php'; App\Core\Autoloader::register();
use App\Services\Question\Builder\QuestionTaxonomyResolver;
use App\Services\Shared\TaxonomyStorageService;
$r=QuestionTaxonomyResolver::resolve(['subject'=>'english','domain'=>'grammar','topic'=>'parts-of-speech','concept'=>'parts-of-speech-nouns']);
$expected=['board_id'=>'let','subject_id'=>'english','domain_id'=>'grammar','topic_id'=>'parts-of-speech','concept_id'=>'parts-of-speech-nouns'];
if($r!==$expected){throw new RuntimeException('context taxonomy was not resolved to canonical hierarchy: '.json_encode($r));}
if (!in_array('english', array_column(TaxonomyStorageService::subjects(), 'id'), true)) { throw new RuntimeException('canonical configured taxonomy storage did not expose English subject'); }
echo "[PASS] Scoped workspace taxonomy resolves to canonical persisted hierarchy.\n";
