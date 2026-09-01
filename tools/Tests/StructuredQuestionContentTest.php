<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/app/Core/Autoloader.php';
\App\Core\Autoloader::register();
require dirname(__DIR__, 2) . '/bootstrap/app.php';
use App\Services\Question\StructuredContentService;
use App\Services\Shared\QuestionValidationService;
$base=['id'=>'structured-fixture','taxonomy'=>['board_id'=>'civil-service','subject_id'=>'analytical-logical','domain_id'=>'analytical-reasoning','topic_id'=>'analytical-assumptions','concept_id'=>'analytical-assumption-identification'],'difficulty'=>'medium','question'=>'A calculation uses the supplied data.','choices'=>['1','2','3','4'],'answer'=>'2','explanation'=>'The supplied relationship produces the second option.'];
$structured=$base+['content_blocks'=>[['type'=>'equation','value'=>'A = b × h','fallback'=>'A equals base times height'],['type'=>'table','columns'=>['Length','Width'], 'rows'=>[['4 m','3 m']]],['type'=>'chart','columns'=>['Month','Count'],'rows'=>[['Jan','4'],['Feb','6']]],['type'=>'figure','asset'=>'assets/diagram.svg','alt'=>'A rectangle labelled with base and height']]];
if (StructuredContentService::validate($structured)!==[] || !str_contains(StructuredContentService::render($structured),'question-data')) throw new RuntimeException('valid structured content failed');
foreach ([['type'=>'table','columns'=>['A','B'],'rows'=>[['1']]],['type'=>'figure','asset'=>'assets/x.svg'],['type'=>'unknown','value'=>'x']] as $block) if (StructuredContentService::validate($base+['content_blocks'=>[$block]])===[]) throw new RuntimeException('malformed block accepted');
if (StructuredContentService::validate($base) !== [] || QuestionValidationService::validate($base)['valid'] !== true) throw new RuntimeException('text-only compatibility failed');
echo "[PASS] Structured equation, unit-safe text, table/chart, figure validation/rendering, and legacy compatibility verified.\n";
