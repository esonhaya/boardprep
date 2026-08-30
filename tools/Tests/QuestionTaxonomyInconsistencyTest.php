<?php
require_once dirname(__DIR__,2).'/app/Core/Autoloader.php'; App\Core\Autoloader::register();
use App\Services\Question\Builder\QuestionTaxonomyResolver;
$r=QuestionTaxonomyResolver::resolve(['board'=>'civil-service','subject'=>'numerical-ability','domain'=>'reading-comprehension','topic'=>'parts-of-speech','concept'=>'parts-of-speech-nouns']);
if(($r['board_id']??'')!=='let'||($r['subject_id']??'')!=='english'||($r['domain_id']??'')!=='grammar'){throw new RuntimeException('inconsistent ancestors were not corrected from child hierarchy');}
echo "[PASS] Taxonomy resolver prevents cross-hierarchy persistence.\n";
