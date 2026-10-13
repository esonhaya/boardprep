<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/Core/Autoloader.php';
\App\Core\Autoloader::register();
putenv('APP_ENV=testing');
putenv('APP_DEBUG=false');
require_once dirname(__DIR__, 2) . '/bootstrap/app.php';

use App\Core\App;
use App\Services\Question\QuestionEligibilityIndex;
use App\Services\Question\QuestionEligibilityService;
use App\Services\Question\QuestionReuseEligibilityResolver;

$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException('Batch672 educational quality assertion failed: ' . $message);
    }
};

$questions = App::storage()->all('questions');
$materials = App::storage()->all('study-materials');
$links = App::storage()->all('study-question-links');
$sources = App::storage()->all('sources');

$questionById = [];
foreach ($questions as $question) {
    if (!is_array($question)) continue;
    $id = trim((string) ($question['id'] ?? ''));
    $assert($id !== '' && !isset($questionById[$id]), 'canonical question ID is unique: ' . $id);
    $questionById[$id] = $question;
}
$assert(count($questionById) === 8962, 'canonical question total remains 8,962');
$materialById = [];
foreach ($materials as $material) {
    if (is_array($material)) $materialById[(string) ($material['id'] ?? '')] = $material;
}
$sourceById = [];
foreach ($sources as $source) {
    if (is_array($source)) $sourceById[(string) ($source['id'] ?? '')] = $source;
}
$linkByQuestionMaterial = [];
$linkIds = [];
$linkPairs = [];
foreach ($links as $link) {
    if (!is_array($link)) continue;
    $linkId = (string) ($link['id'] ?? '');
    $assert($linkId !== '' && !isset($linkIds[$linkId]), 'study link ID is unique: ' . $linkId);
    $linkIds[$linkId] = true;
    $key = (string) ($link['question_id'] ?? '') . '|' . (string) ($link['material_id'] ?? '');
    $assert(!isset($linkPairs[$key]), 'question/material link pair is unique: ' . $key);
    $linkPairs[$key] = true;
    $linkByQuestionMaterial[$key] = $link;
}

$requireLink = static function (string $questionId, string $materialId) use ($assert, $linkByQuestionMaterial, $materialById, $sourceById): array {
    $key = $questionId . '|' . $materialId;
    $assert(isset($linkByQuestionMaterial[$key]), 'question ' . $questionId . ' links to ' . $materialId);
    $link = $linkByQuestionMaterial[$key];
    $material = $materialById[$materialId] ?? null;
    $assert(is_array($material), 'linked study material exists: ' . $materialId);
    foreach ((array) ($link['source_ids'] ?? []) as $sourceId) {
        $sourceId = (string) $sourceId;
        $assert(isset($sourceById[$sourceId]), 'linked source exists: ' . $sourceId);
        $assert(in_array($sourceId, (array) ($material['source_ids'] ?? []), true), 'linked source is declared by study material: ' . $sourceId);
    }
    return $link;
};

$civicMaterial = $materialById['constitution-review-foundation'] ?? [];
$assert(($civicMaterial['title'] ?? '') === 'Civic, Rights, and Public-Interest Decisions', 'shared civic material has a board-neutral title');
$assert(array_diff(['let', 'civil-service', 'criminologist'], (array) ($civicMaterial['exams'] ?? [])) === [], 'shared civic material remains available to its three boards');
foreach (['42', '44', '48', '2434', '2435', '2436', '2437', '2438', '2441', '2442', '2443'] as $questionId) {
    $requireLink($questionId, 'constitution-review-foundation');
}
$assert(isset($sourceById['republic-act-11106']), 'the authoritative FSL law source is registered');
$requireLink('48', 'constitution-review-foundation');
$requireLink('2434', 'constitution-review-foundation');
$requireLink('2438', 'constitution-review-foundation');
$requireLink('2443', 'constitution-review-foundation');
$assert(in_array('integrated-bar-v-atienza-2010', (array) ($questionById['2438']['source_ids'] ?? []), true), 'public-assembly route item cites the relevant Supreme Court decision');
$assert(in_array('official-gazette-ra10173', (array) ($questionById['2434']['source_ids'] ?? []), true), 'privacy question cites the Official Gazette Data Privacy Act text');
$assert(in_array('bp344-2024-irr', (array) ($questionById['48']['source_ids'] ?? []), true), 'accessibility question cites the 2024 BP 344 rules');
$assert(in_array('republic-act-11106', (array) ($questionById['2443']['source_ids'] ?? []), true), 'accessible participation question cites RA 11106');
$assert(($questionById['2442']['answer'] ?? '') === 'The public purpose, the factual basis, and how the rule is applied.', 'historical correct-answer text remains stable for Q2442');
$assert(($questionById['2438']['answer'] ?? '') === 'The restriction should be assessed for necessity and proportionality while preserving peaceful participation.', 'historical correct-answer text remains stable for Q2438');
$requireLink('4798', 'ple-487--ymptom-atterns-and-unctioning');
$assert(in_array('republic-act-11036', (array) ($questionById['4798']['source_ids'] ?? []), true), 'psychometrician legal-context item cites RA 11036');
$assert(in_array('republic-act-11036', (array) ($materialById['ple-487--ymptom-atterns-and-unctioning']['source_ids'] ?? []), true), 'psychometrician material supports its source link with RA 11036');
$psychReadiness = json_decode((string) file_get_contents(dirname(__DIR__, 2) . '/storage/source-readiness-psychometrician.json'), true, 512, JSON_THROW_ON_ERROR);
$lawLeaf = array_values(array_filter($psychReadiness, static fn(array $row): bool => ($row['leaf_id'] ?? '') === 'psych-abnormal-mental-health-law'))[0] ?? [];
$assert(($lawLeaf['readiness'] ?? '') === 'DEFER' && ($lawLeaf['defer_reason'] ?? '') === 'SCOPE_AND_CURRENTNESS_REVIEW', 'source availability improved without falsely clearing unresolved scope/currentness review');

foreach (['46', '50'] as $questionId) {
    $requireLink($questionId, 'constitution-review-foundation');
    $letMath = $requireLink($questionId, 'let-mathematics-essentials-review');
    $cseMath = $requireLink($questionId, 'cse-numerical-foundation');
    $assert(in_array('let', (array) ($materialById[$letMath['material_id']]['exams'] ?? []), true), 'LET math resource is scoped to LET');
    $assert(in_array('civil-service', (array) ($materialById[$cseMath['material_id']]['exams'] ?? []), true), 'CSE numeric resource is scoped to Civil Service');
    $expectedSource = $questionId === '46' ? 'openstax-prealgebra-proportions-chapter6-5' : 'openstax-statistics-graphs-chapter2-2';
    $assert(in_array($expectedSource, (array) ($questionById[$questionId]['source_ids'] ?? []), true), 'quantitative question cites its exact OpenStax section: ' . $questionId);
}
$assert(str_contains($questionById['46']['explanation'] ?? '', '80 ÷ 100 = 0.80') && str_contains($questionById['46']['explanation'] ?? '', '90 ÷ 150 = 0.60'), 'proportion rationale uses each group denominator');
$assert(str_contains(strtolower($questionById['50']['explanation'] ?? ''), 'truncated axis'), 'chart rationale explains the effect of a truncated axis');
$assert(($sourceById['openstax-prealgebra-proportions-chapter6-5']['publication_date'] ?? '') === '2020-03-11', 'OpenStax Prealgebra 2e publication date matches its official citation metadata');
$assert(($sourceById['openstax-statistics-graphs-chapter2-2']['publication_date'] ?? '') === '2023-12-13', 'OpenStax Introductory Statistics 2e publication date matches its official citation metadata');

foreach (['1300', '1316'] as $questionId) {
    $requireLink($questionId, 'cpa-management-relevant-decisions-foundation');
}
$assert(str_contains($questionById['1300']['explanation'] ?? '', 'P12,000') && str_contains($questionById['1300']['explanation'] ?? '', 'P6,000'), 'special-order rationale shows contribution and setup');
$assert(str_contains($questionById['1306']['explanation'] ?? '', 'continues under either option'), 'make-or-buy rationale excludes unchanged allocated fixed cost');
$assert(str_contains($questionById['1316']['explanation'] ?? '', 'P15 per machine hour') && str_contains($questionById['1316']['explanation'] ?? '', 'P24 per machine hour'), 'bottleneck rationale compares contribution per hour');

$normalizeStem = static function (string $stem): string {
    return strtolower((string) preg_replace('/\s+/', ' ', trim($stem)));
};
$assert($normalizeStem((string) $questionById['86']['question']) !== $normalizeStem((string) $questionById['6298']['question']), 'LET Q86 and CSE Q6298 have distinct stem fingerprints');
$index = new QuestionEligibilityIndex(
    App::storage()->all('question-eligibility'),
    App::storage()->all('blueprints'),
    QuestionReuseEligibilityResolver::productionDerivedRelationships(),
    App::storage()->all('question-reuse-overrides')
);
foreach (['let', 'civil-service', 'criminologist'] as $boardId) {
    foreach (['46', '50'] as $questionId) {
        $assert(QuestionEligibilityService::forExam($questionById[$questionId], $boardId, $index) !== null, 'Q' . $questionId . ' cross-board eligibility remains for ' . $boardId);
    }
}
$assert(QuestionEligibilityService::forExam($questionById['86'], 'civil-service', $index) !== null, 'shared LET Q86 remains eligible in CSE');
$assert(QuestionEligibilityService::forExam($questionById['6298'], 'civil-service', $index) !== null, 'CSE Q6298 remains eligible in CSE');
$csePool = QuestionPoolFilter::filter(
    $questions,
    new SelectionRequest('*', null, [], 1, null, null, 'civil-service'),
    $index
);
$cseIds = array_fill_keys(array_map(static fn(array $question): string => (string) $question['id'], $csePool), true);
$assert(isset($cseIds['86'], $cseIds['6298']), 'production CSE selection retains both distinct questions sharing the former stem');

echo "BATCH672_EDUCATIONAL_QUALITY=PASS\n";
echo "CSE_Q86_Q6298=RETAINED\n";
