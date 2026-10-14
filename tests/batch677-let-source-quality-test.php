<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/Core/Autoloader.php';
App\Core\Autoloader::register();

use App\Services\RepositoryHealth\Readiness\SourceReadinessRecord;
use Tools\Doctor\Project\BoardPrep\Checks\LetCompetencyDecompositionCheck;
use Tools\Doctor\Project\BoardPrep\Checks\LetSourceCrosswalkCheck;
use Tools\Doctor\Registry\CheckRegistry;

$root = dirname(__DIR__);
$fail = static function (string $message): never {
    fwrite(STDERR, 'BATCH677_TEST_FAIL=' . $message . PHP_EOL);
    exit(1);
};
$read = static function (string $path) use ($root): array {
    $value = json_decode((string) file_get_contents($root . '/' . $path), true, 512, JSON_THROW_ON_ERROR);
    return is_array($value) ? $value : [];
};
$collections = [
    'questions' => $read('storage/questions.json'),
    'study-question-links' => $read('storage/study-question-links.json'),
    'study-materials' => $read('storage/study-materials.json'),
    'sources' => $read('storage/sources.json'),
    'source-readiness-let' => $read('storage/source-readiness-let.json'),
];
$report = $read('storage/let-source-crosswalk-batch677.json');
$audit = LetSourceCrosswalkCheck::audit($report, $collections);
if ($audit['errors'] !== []) $fail('Crosswalk/Doctor mismatch: ' . implode(' | ', $audit['errors']));
if (count($report['crosswalks'] ?? []) !== 42) $fail('Expected 34 existing crosswalks plus eight decomposed LET study categories.');
if (($audit['summary']['verified'] ?? -1) !== 15 || ($audit['summary']['correctable'] ?? -1) !== 27 || ($audit['summary']['detector_limitations'] ?? -1) !== 0) {
    $fail('Expected 15 verified, 27 correctable, and no aggregate detector limitation.');
}
if (($audit['summary']['target_unknown'] ?? -1) !== 0 || ($audit['summary']['all_let_unknown'] ?? -1) !== 3) {
    $fail('Expected the LET aggregate to resolve while retaining three separate CSE structural UNKNOWN rows.');
}
$decompositionCollections = [
    'questions' => $collections['questions'],
    'concepts' => $read('storage/taxonomy/concepts.json'),
    'topics' => $read('storage/taxonomy/topics.json'),
    'materials' => $collections['study-materials'],
    'links' => $collections['study-question-links'],
    'sources' => $collections['sources'],
    'readiness' => $collections['source-readiness-let'],
];
$decompositionAudit = LetCompetencyDecompositionCheck::audit($decompositionCollections, $report);
if ($decompositionAudit['errors'] !== []) $fail('LET decomposition/Doctor mismatch: ' . implode(' | ', $decompositionAudit['errors']));
if (($decompositionAudit['summary']['children'] ?? -1) !== 8 || ($decompositionAudit['summary']['questions'] ?? -1) !== 67) {
    $fail('Expected exactly eight child categories and 67 unique reassigned questions.');
}
$decompositionChildCount = (int) $decompositionAudit['summary']['children'];
$badDecomposition = $decompositionCollections;
$targetDecompositionLink = [];
foreach ($report['crosswalks'] as $crosswalkRow) {
    if (($crosswalkRow['parent_leaf_id'] ?? '') === 'prof-development-readiness') {
        $targetDecompositionLink = $crosswalkRow['question_links'][0] ?? [];
        break;
    }
}
$badDecomposition['links'] = array_values(array_filter(
    $badDecomposition['links'],
    static fn(array $link): bool => !((string) ($link['question_id'] ?? '') === (string) ($targetDecompositionLink['question_id'] ?? '')
        && (string) ($link['material_id'] ?? '') === (string) ($targetDecompositionLink['material_id'] ?? ''))
));
$badDecompositionAudit = LetCompetencyDecompositionCheck::audit($badDecomposition, $report);
if ($badDecompositionAudit['errors'] === []) $fail('LET decomposition Doctor regression did not detect a dangling study pathway.');
unset($decompositionCollections, $badDecomposition, $badDecompositionAudit, $decompositionAudit);

$crosswalkById = [];
foreach ($report['crosswalks'] as $row) $crosswalkById[(string) $row['leaf_id']] = $row;
foreach (['cse-constitutional-principles', 'cse-environmental-public-interest', 'cse-ethical-public-service'] as $structuralId) {
    if (isset($crosswalkById[$structuralId])) $fail('Structural CSE placeholder entered the LET review.');
}
$expectedClassifications = [
    'inclusive-access' => 'VERIFIED',
    'let-ethical-duties' => 'VERIFIED',
    'piaget-stages' => 'CORRECTABLE',
    'let-developmental-readiness-scaffolding' => 'CORRECTABLE',
    'let-assessment-evidence-use' => 'CORRECTABLE',
];
foreach ($expectedClassifications as $leafId => $classification) {
    if (($crosswalkById[$leafId]['classification'] ?? '') !== $classification) $fail('Unexpected classification for ' . $leafId);
}

$sourceById = [];
foreach ($collections['sources'] as $source) $sourceById[(string) $source['id']] = $source;
$materialById = [];
foreach ($collections['study-materials'] as $material) $materialById[(string) $material['id']] = $material;
foreach ($collections['source-readiness-let'] as $row) {
    if (($row['board_id'] ?? '') !== 'let') continue;
    if (!hash_equals(SourceReadinessRecord::evidenceHash($row), (string) ($row['evidence_hash'] ?? ''))) {
        $fail('Stale readiness evidence hash for ' . (string) ($row['leaf_id'] ?? ''));
    }
}

$questionById = [];
foreach ($collections['questions'] as $question) $questionById[(string) $question['id']] = $question;
$linkByPair = [];
foreach ($collections['study-question-links'] as $link) $linkByPair[(string) $link['question_id'] . '|' . (string) $link['material_id']] = $link;
$clusterBaselineById = [];
foreach ($read('tests/fixtures/batch674-question-identity-baseline.json') as $question) {
    $clusterBaselineById[(string) $question['id']] = $question;
}
if (count($clusterBaselineById) !== 67) $fail('Batch 674 identity fixture must contain all 67 original question IDs.');
foreach ($clusterBaselineById as $questionId => $baseline) {
    $question = $questionById[$questionId] ?? null;
    if (!is_array($question)) $fail('Canonical question ID was lost during LET decomposition: ' . $questionId);
    foreach (['answer', 'board'] as $field) {
        if (($question[$field] ?? null) !== ($baseline[$field] ?? null)) $fail('Historical ' . $field . ' changed for question ' . $questionId);
    }
}
$rewrittenQuestionIds = [5898, 5899, 5900, 5901, 5902, 132, 135, 138, 139, 140, 2818, 2819, 2820, 2821, 2822, 2823, 2824, 2825, 2826, 2827];
$stemRewrittenIds = ['5898', '5899', '5900', '5901', '5902'];
foreach ($rewrittenQuestionIds as $id) {
    $key = (string) $id;
    $question = $questionById[$key] ?? null;
    $baseline = $clusterBaselineById[$key] ?? null;
    if (!is_array($question) || !is_array($baseline)
        || !isset($baseline['original_question_sha256'], $baseline['original_explanation_sha256'])) {
        $fail('Missing Batch 674 rewrite fingerprint for question ' . $key);
    }
    if (hash('sha256', (string) ($question['explanation'] ?? '')) === $baseline['original_explanation_sha256']) {
        $fail('Expected a rewritten, question-specific rationale for question ' . $key);
    }
    if (strlen((string) ($question['explanation'] ?? '')) < 100) {
        $fail('Rewritten rationale is too short to explain its decision for question ' . $key);
    }
    $stemUnchanged = hash('sha256', (string) ($question['question'] ?? '')) === $baseline['original_question_sha256'];
    if ($stemUnchanged === in_array($key, $stemRewrittenIds, true)) {
        $fail('Stem revision does not match the reviewed Batch 674 question set for question ' . $key);
    }
}
foreach ([5898, 5899, 5900, 5901, 5902] as $questionId) {
    $question = $questionById[(string) $questionId] ?? [];
    $choices = array_map('strval', (array) ($question['choices'] ?? []));
    if (count($choices) !== 4 || count(array_unique($choices)) !== 4) $fail('Rewritten readiness question must have four distinct choices: ' . $questionId);
    if (count(array_filter($choices, static fn(string $choice): bool => $choice === (string) ($question['answer'] ?? ''))) !== 1) $fail('Correct answer must appear exactly once in question ' . $questionId);
    if (strlen((string) ($question['explanation'] ?? '')) < 100) $fail('Rewritten readiness question needs a worked rationale: ' . $questionId);
}
$privacyMaterial = $materialById['constitution-review-foundation'] ?? null;
if (!is_array($privacyMaterial)) $fail('Shared civic/privacy study material is missing.');
if (!str_contains((string) ($privacyMaterial['summary'] ?? ''), 'Nursing, Psychometrician, and CPA')) $fail('Shared privacy material summary omits one or more newly scoped boards.');
if (!str_contains((string) ($privacyMaterial['summary'] ?? ''), 'does not establish examination scope')) $fail('Shared privacy material must avoid implying board exam scope.');
foreach (['let', 'civil-service', 'criminologist', 'nursing', 'psychometrician', 'cpa'] as $boardId) {
    if (!in_array($boardId, (array) ($privacyMaterial['exams'] ?? []), true)) $fail('Shared privacy resource is not discoverable for ' . $boardId);
}
foreach (['441', '480', '498', '542', '728', '8832', '889', '1047', '1091', '1108', '8257', '8267'] as $questionId) {
    $pair = $questionId . '|constitution-review-foundation';
    if (!isset($linkByPair[$pair])) $fail('Missing cross-board privacy study link ' . $pair);
    foreach (['lawphil-ra10173', 'official-gazette-ra10173'] as $sourceId) {
        if (!in_array($sourceId, (array) ($linkByPair[$pair]['source_ids'] ?? []), true)) $fail('Missing privacy source ' . $sourceId . ' on ' . $pair);
    }
}
if (!isset($linkByPair['444|let-education-foundations-review'], $linkByPair['448|let-education-foundations-review'])) {
    $fail('Sociology-of-education source path must support the exact LET policy and social-purpose questions.');
}
$inclusive = $crosswalkById['inclusive-universal-design'] ?? null;
if (!is_array($inclusive) || !in_array('let-inclusive-education-review', (array) ($inclusive['material_ids'] ?? []), true)) {
    $fail('Inclusive-education crosswalk lacks its source-first study pathway.');
}
foreach (array_map('strval', (array) ($inclusive['question_ids'] ?? [])) as $questionId) {
    if (!isset($questionById[$questionId])) $fail('Missing inclusive question ' . $questionId);
    $pair = $questionId . '|let-inclusive-education-review';
    if (!isset($linkByPair[$pair])) $fail('Missing inclusive resource link ' . $pair);
    foreach ((array) $linkByPair[$pair]['source_ids'] as $sourceId) {
        if (!isset($sourceById[$sourceId])) $fail('Unresolved inclusive source ' . $sourceId);
        if (!in_array($sourceId, (array) $materialById['let-inclusive-education-review']['source_ids'], true)) {
            $fail('Inclusive link cites a source absent from its material: ' . $sourceId);
        }
    }
}
foreach ([
    '275|let-child-development-review' => 'openstax-lifespan-development',
    '286|let-learning-theories-review' => 'open-university-guided-discovery',
    '287|let-learning-theories-review' => 'openlearn-bruner-representations',
    '292|let-learning-theories-review' => 'ferrero-multiple-intelligences-review-2021',
    '293|let-assessment-review' => 'cornell-bloom-taxonomy',
] as $pair => $sourceId) {
    if (!isset($linkByPair[$pair])) $fail('Missing specific study pathway ' . $pair);
    if (!in_array($sourceId, (array) ($linkByPair[$pair]['source_ids'] ?? []), true)) $fail('Missing supporting source ' . $sourceId . ' on ' . $pair);
}

$rewrittenIds = [259, 260, 261, 262, 263, 265, 267, 268, 269, 270, 271, 272, 280, 281, 282, 283, 284, 285, 286, 287, 288, 289];
$baselineById = [];
foreach ($read('tests/fixtures/batch673-question-identity-baseline.json') as $question) {
    $baselineById[(string) $question['id']] = $question;
}
foreach ($rewrittenIds as $id) {
    $key = (string) $id;
    $question = $questionById[$key] ?? null;
    $baseline = $baselineById[$key] ?? null;
    if (!is_array($question) || !is_array($baseline)) $fail('Missing rewritten question ' . $key);
    foreach (['answer', 'board', 'taxonomy'] as $field) {
        if (($question[$field] ?? null) !== ($baseline[$field] ?? null)) $fail('Unexpected answer/identity change for question ' . $key . ' field ' . $field);
    }
    foreach ([
        'The scenario’s evidence supports',
        'because the teacher must interpret the learner or classroom condition',
        'because the teacher must connect the observed learning condition',
    ] as $boilerplate) {
        if (str_contains((string) ($question['explanation'] ?? ''), $boilerplate)) $fail('Boilerplate explanation remains on question ' . $key);
    }
    if (strlen((string) ($question['explanation'] ?? '')) < 80) $fail('Explanation is too short to show its reasoning for question ' . $key);
}

$badReport = $report;
$badReport['crosswalks'][0]['question_ids'][0] = 'nonexistent-673';
$badAudit = LetSourceCrosswalkCheck::audit($badReport, $collections);
if ($badAudit['errors'] === []) $fail('Doctor regression did not detect a stale question ID.');

$missingPathReport = $report;
$missingPathCollections = $collections;
$pathRowIndex = null;
foreach ($missingPathReport['crosswalks'] as $index => $row) {
    if (($row['question_ids'] ?? []) !== [] && ($row['material_ids'] ?? []) !== []) {
        $pathRowIndex = $index;
        break;
    }
}
if ($pathRowIndex === null) $fail('No crosswalk row is available for the missing-path regression.');
$pathRow = $missingPathReport['crosswalks'][$pathRowIndex];
$pathQuestionId = (string) $pathRow['question_ids'][0];
$pathMaterialIds = array_map('strval', (array) $pathRow['material_ids']);
$missingPathReport['crosswalks'][$pathRowIndex]['question_links'] = array_values(array_filter(
    $pathRow['question_links'],
    static fn(array $link): bool => (string) ($link['question_id'] ?? '') !== $pathQuestionId
));
$missingPathCollections['study-question-links'] = array_values(array_filter(
    $missingPathCollections['study-question-links'],
    static fn(array $link): bool => !((string) ($link['question_id'] ?? '') === $pathQuestionId
        && in_array((string) ($link['material_id'] ?? ''), $pathMaterialIds, true))
));
$missingPathAudit = LetSourceCrosswalkCheck::audit($missingPathReport, $missingPathCollections);
if (!array_filter($missingPathAudit['errors'], static fn(string $error): bool => str_contains($error, 'has no linked material'))) {
    $fail('Doctor regression did not detect a question with no pathway to a declared study material.');
}

$claimLimitReport = $report;
$claimLimitReport['crosswalks'][0]['evidence'][0]['claim_limit'] = '';
$claimLimitAudit = LetSourceCrosswalkCheck::audit($claimLimitReport, $collections);
if (!array_filter($claimLimitAudit['errors'], static fn(string $error): bool => str_contains($error, 'claim limit'))) {
    $fail('Doctor regression did not detect evidence with no limit on what it supports.');
}

$staleSourceCollections = $collections;
$staleSourceId = (string) $report['crosswalks'][0]['evidence'][0]['source_id'];
foreach ($staleSourceCollections['sources'] as &$source) {
    if ((string) ($source['id'] ?? '') === $staleSourceId) $source['retrieved_at'] = 'not-a-date';
}
unset($source);
$staleSourceAudit = LetSourceCrosswalkCheck::audit($report, $staleSourceCollections);
if (!array_filter($staleSourceAudit['errors'], static fn(string $error): bool => str_contains($error, 'status/currentness metadata'))) {
    $fail('Doctor regression did not detect malformed source-currentness metadata.');
}

$unknownCurrentnessCollections = $collections;
foreach ($unknownCurrentnessCollections['source-readiness-let'] as &$readinessRecord) {
    if ((string) ($readinessRecord['leaf_id'] ?? '') === (string) $report['crosswalks'][0]['leaf_id']) {
        $readinessRecord['source_currentness'] = 'UNKNOWN';
    }
}
unset($readinessRecord);
$unknownCurrentnessAudit = LetSourceCrosswalkCheck::audit($report, $unknownCurrentnessCollections);
if ($unknownCurrentnessAudit['errors'] !== []
    || !array_filter($unknownCurrentnessAudit['warnings'], static fn(string $warning): bool => str_contains($warning, 'source currentness is UNKNOWN'))) {
    $fail('Unknown source currentness must remain a review warning, not a hard failure.');
}

unset($collections, $questionById, $linkByPair, $sourceById, $materialById, $crosswalkById, $baselineById, $badReport, $badAudit, $missingPathReport, $missingPathCollections, $missingPathAudit, $claimLimitReport, $claimLimitAudit, $staleSourceCollections, $staleSourceAudit, $unknownCurrentnessCollections, $unknownCurrentnessAudit, $audit);
gc_collect_cycles();
$registeredChecks = (new CheckRegistry())->fromDirectories([$root . '/tools/Doctor/Project/BoardPrep/Checks']);
$registeredCrosswalkChecks = array_filter($registeredChecks, static fn(object $check): bool => $check instanceof LetSourceCrosswalkCheck);
if (count($registeredCrosswalkChecks) !== 1) $fail('Doctor directory discovery did not register exactly one LET crosswalk check.');
$registeredDecompositionChecks = array_filter($registeredChecks, static fn(object $check): bool => $check instanceof LetCompetencyDecompositionCheck);
if (count($registeredDecompositionChecks) !== 1) $fail('Doctor directory discovery did not register exactly one LET decomposition check.');

fwrite(STDOUT, 'BATCH677_SOURCE_QUALITY_TEST=PASS' . PHP_EOL);
fwrite(STDOUT, 'CROSSWALKS=42 VERIFIED=15 CORRECTABLE=27 DETECTOR_LIMITATION=0' . PHP_EOL);
fwrite(STDOUT, 'BATCH673_LEARNING_THEORY_EXPLANATIONS_REWRITTEN=' . count($rewrittenIds) . PHP_EOL);
fwrite(STDOUT, 'BATCH674_QUESTIONS_REWRITTEN=' . count($rewrittenQuestionIds) . ' STEMS_REVISED=' . count($stemRewrittenIds) . PHP_EOL);
fwrite(STDOUT, 'LET_CHILD_CATEGORIES=' . $decompositionChildCount . ' LET_UDL_PATHS=' . count($inclusive['question_ids']) . PHP_EOL);
