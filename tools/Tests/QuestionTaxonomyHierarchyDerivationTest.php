<?php
require_once dirname(__DIR__,2).'/app/Core/Autoloader.php'; App\Core\Autoloader::register();
use App\Services\Question\Builder\QuestionTaxonomyResolver;
$r=QuestionTaxonomyResolver::resolve(['concept_id'=>'parts-of-speech-nouns']);
if(($r['topic_id']??'')!=='parts-of-speech'||($r['domain_id']??'')!=='grammar'||($r['subject_id']??'')!=='english'||($r['board_id']??'')!=='let'){throw new RuntimeException('child taxonomy did not derive canonical ancestors');}
echo "[PASS] Taxonomy resolver derives canonical ancestors from concept.\n";
