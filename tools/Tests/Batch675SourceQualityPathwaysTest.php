<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/Core/Autoloader.php';
\App\Core\Autoloader::register();

use Tools\Doctor\Project\BoardPrep\Checks\SourceScopeAdjudicationCheck;

$root = dirname(__DIR__, 2);
$assert = static function (bool $ok, string $message): void {
    if (!$ok) throw new RuntimeException('Batch 675 source-quality pathway failed: ' . $message);
};
$read = static fn(string $file): array => json_decode((string) file_get_contents($root . '/storage/' . $file . '.json'), true, 512, JSON_THROW_ON_ERROR);
$questions = $read('questions');
$questions = array_is_list($questions) ? $questions : (array) ($questions['questions'] ?? []);
$byId = [];
foreach ($questions as $q) $byId[(string) ($q['id'] ?? '')] = $q;
$assert(count($questions) === 8962, 'canonical question total is unchanged');
$assert(count($byId) === count($questions), 'canonical identities remain unique');
$assert(($byId['1306']['answer'] ?? '') === 'P30,000', 'Q1306 answer identity is preserved');
$assert(($byId['1306']['taxonomy']['topic_id'] ?? '') === 'cpa-management-services-make-buy', 'Q1306 is classified as make-or-buy');
$assert(($byId['1306']['taxonomy']['concept_id'] ?? '') === 'cpa-management-services-make-buy-core', 'Q1306 maps to the existing make-or-buy concept');
$assert(str_contains((string) ($byId['1306']['explanation'] ?? ''), 'not necessarily a sunk cost'), 'Q1306 distinguishes unchanged from sunk cost');
$assert(($byId['1316']['answer'] ?? '') === 'B, because it contributes P24 per constrained hour', 'Q1316 answer identity is preserved');
$assert(($byId['1316']['taxonomy']['topic_id'] ?? '') === 'cpa-management-services-constraints', 'Q1316 is classified under constrained resources');
$assert(($byId['1234']['answer'] ?? '') === '0.20', 'Civil Engineering Q1234 answer identity is preserved');
$assert(in_array('fhwa-soils-foundations-reference', (array) ($byId['1234']['source_ids'] ?? []), true), 'Q1234 carries the exact FHWA seepage reference');
$assert(str_contains((string) ($byId['1234']['explanation'] ?? ''), 'dimensionless ratio') && str_contains((string) ($byId['1234']['explanation'] ?? ''), 'distinct from hydraulic conductivity'), 'Q1234 explains the gradient units and separates i from k');

$sources = [];
foreach ($read('sources') as $row) $sources[$row['id']] = $row;
$exactSources = [
 '1300' => ['openstax-managerial-accounting-10-2-special-order','https://openstax.org/books/principles-managerial-accounting/pages/10-2-evaluate-and-determine-whether-to-accept-or-reject-a-special-order'],
 '1306' => ['openstax-managerial-accounting-10-3-make-buy','https://openstax.org/books/principles-managerial-accounting/pages/10-3-evaluate-and-determine-whether-to-make-or-buy-a-component'],
 '1316' => ['openstax-managerial-accounting-10-6-constrained-resources','https://openstax.org/books/principles-managerial-accounting/pages/10-6-evaluate-and-determine-how-to-make-decisions-when-resources-are-constrained'],
];
foreach ($exactSources as $qid => [$sourceId,$url]) {
    $assert(in_array($sourceId, (array) ($byId[$qid]['source_ids'] ?? []), true), 'Q' . $qid . ' cites its exact OpenStax section');
    $assert(($sources[$sourceId]['url'] ?? '') === $url, 'Q' . $qid . ' source record resolves to the checked section URL');
    $assert(($sources[$sourceId]['publication_date'] ?? null) === null, 'no unverified OpenStax section publication date is invented');
}
$links = $read('study-question-links');
$civilSeepageLink = array_values(array_filter($links, static fn(array $r): bool => (string) ($r['question_id'] ?? '') === '1234' && (string) ($r['material_id'] ?? '') === 'ce-489--oil-echanics-and-oundations'));
$assert(count($civilSeepageLink) === 1 && in_array('fhwa-soils-foundations-reference', (array) ($civilSeepageLink[0]['source_ids'] ?? []), true), 'Q1234 retains its exact source-first study pathway');
$linkRows = array_values(array_filter($links, static fn(array $r): bool => in_array((string) ($r['question_id'] ?? ''), ['1300','1306','1316'], true)));
$assert(!in_array('cpa-management-performance-foundation', array_column($linkRows, 'material_id'), true), 'Q1316 no longer links the unrelated generic performance material');
foreach ($linkRows as $row) {
    $qid=(string)$row['question_id'];
    $assert(in_array($exactSources[$qid][0], (array) ($row['source_ids'] ?? []), true), 'Q' . $qid . ' study link carries the exact supporting section');
}
$audits=[]; foreach ($read('content-fit-audits') as $r) $audits[(string)$r['question_id']]=$r;
foreach (['1300','1306','1316'] as $qid) $assert(($audits[$qid]['fit_classification'] ?? '') === 'CORRECT', 'Q' . $qid . ' has a reviewed, corrected fit classification');
$coverage=$read('board-concept-coverage');
foreach ([
 'cpa-management-services-make-buy-core'=>'1306',
 'cpa-management-services-constraints-core'=>'1316',
] as $concept=>$qid) {
    $found=false;
    foreach ($coverage as $row) if (($row['board_id']??'')==='cpa' && ($row['concept_id']??'')===$concept) {
        foreach ((array)($row['provenance']??[]) as $p) if (($p['reference']??'')==='question:'.$qid) $found=true;
    }
    $assert($found, 'CPA coverage provenance includes Q' . $qid . ' in ' . $concept);
}
$materials=[]; foreach ($read('study-materials') as $r) $materials[$r['id']]=$r;
$assert(count($materials['environmental-public-interest-foundation']['notes'] ?? []) >= 5, 'environmental material now has a scoped CSE evidence and reading pathway');
$assert(count($materials['ra-6713-review-foundation']['notes'] ?? []) >= 6, 'RA 6713 lesson distinguishes Section 4 norms from Section 5 duties');
$assert(in_array('openstax-managerial-accounting-10-3-make-buy', (array)($materials['cpa-management-relevant-decisions-foundation']['source_ids']??[]), true), 'make-or-buy material cites its exact section');
$civilSeepageNotes = (array) ($materials['ce-489--oil-echanics-and-oundations']['notes'] ?? []);
$civilSeepage = array_values(array_filter($civilSeepageNotes, static fn(array $note): bool => ($note[0] ?? $note['heading'] ?? '') === 'Seepage'));
$assert(count($civilSeepage) === 1 && str_contains((string) ($civilSeepage[0][1] ?? $civilSeepage[0]['body'] ?? ''), 'hydraulic gradient i'), 'Civil Engineering seepage material distinguishes hydraulic gradient from conductivity');

unset($questions, $byId, $sources, $links, $linkRows, $audits, $coverage, $materials);
gc_collect_cycles();
$check=(new SourceScopeAdjudicationCheck())->run();
$assert($check->status==='PASS', 'the Doctor source-scope adjudication check passes: ' . implode(' | ', $check->details));
echo "BATCH675_SOURCE_QUALITY_PATHWAYS=PASS\n";
