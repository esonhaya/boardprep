<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/app/Core/Autoloader.php';
\App\Core\Autoloader::register();
require dirname(__DIR__, 2) . '/bootstrap/app.php';
use App\Services\Question\StructuredContentService;
use App\Services\Shared\QuestionValidationService;
$base=['id'=>'structured-fixture','taxonomy'=>['board_id'=>'civil-service','subject_id'=>'analytical-logical','domain_id'=>'analytical-reasoning','topic_id'=>'analytical-assumptions','concept_id'=>'analytical-assumption-identification'],'difficulty'=>'medium','question'=>'A calculation uses the supplied data.','choices'=>['1','2','3','4'],'answer'=>'2','explanation'=>'The supplied relationship produces the second option.'];
$structured=$base+['content_blocks'=>[['type'=>'equation','value'=>'A = b × h','fallback'=>'A equals base times height'],['type'=>'table','columns'=>['Length','Width'], 'rows'=>[['4 m','3 m']]],['type'=>'chart','columns'=>['Month','Count'],'rows'=>[['Jan','4'],['Feb','6']]],['type'=>'figure','asset'=>'assets/me/crank-slider.svg','alt'=>'A crank connected to a slider','caption'=>'Technical mechanism diagram']]];
$rendered=StructuredContentService::render($structured);
if (StructuredContentService::validate($structured)!==[] || !str_contains($rendered,'question-data') || !str_contains($rendered,'alt="A crank connected to a slider"') || !str_contains($rendered,'Technical mechanism diagram')) throw new RuntimeException('valid structured content failed');
foreach ([['type'=>'table','columns'=>['A','B'],'rows'=>[['1']]],['type'=>'figure','asset'=>'assets/x.svg','alt'=>'Missing figure'],['type'=>'figure','asset'=>'assets/me/crank-slider.txt','alt'=>'Wrong type'],['type'=>'unknown','value'=>'x']] as $block) if (StructuredContentService::validate($base+['content_blocks'=>[$block]])===[]) throw new RuntimeException('malformed block accepted');
if (StructuredContentService::validate($base+['content_blocks'=>'invalid'])===[]) throw new RuntimeException('non-array blocks accepted');
$missing=StructuredContentService::render($base+['content_blocks'=>[['type'=>'figure','asset'=>'assets/missing.webp','alt'=>'A missing educational photograph']]]);
if (!str_contains($missing,'Figure unavailable: A missing educational photograph') || str_contains($missing,'<img')) throw new RuntimeException('missing figure fallback failed');
if (StructuredContentService::validate($base) !== [] || QuestionValidationService::validate($base)['valid'] !== true) throw new RuntimeException('text-only compatibility failed');
echo "[PASS] Structured equation, unit-safe text, table/chart, figure validation/rendering, and legacy compatibility verified.\n";
