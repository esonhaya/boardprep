<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/Core/Autoloader.php';
App\Core\Autoloader::register();

use App\Services\RepositoryHealth\Readiness\SourceReadinessRecord;
use Tools\Doctor\Project\BoardPrep\Checks\LetSourceCrosswalkCheck;
use Tools\Doctor\Registry\CheckRegistry;

$root = dirname(__DIR__);
$fail = static function (string $message): never {
    fwrite(STDERR, 'BATCH673_TEST_FAIL=' . $message . PHP_EOL);
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
$report = $read('storage/let-source-crosswalk-batch673.json');
$audit = LetSourceCrosswalkCheck::audit($report, $collections);
if ($audit['errors'] !== []) $fail('Crosswalk/Doctor mismatch: ' . implode(' | ', $audit['errors']));
if (count($report['crosswalks'] ?? []) !== 35) $fail('Expected 35 reviewed LET crosswalks.');
if (($audit['summary']['verified'] ?? -1) !== 15 || ($audit['summary']['correctable'] ?? -1) !== 19 || ($audit['summary']['detector_limitations'] ?? -1) !== 1) {
    $fail('Expected 15 verified, 19 correctable, and one detector limitation.');
}
if (($audit['summary']['target_unknown'] ?? -1) !== 1 || ($audit['summary']['all_let_unknown'] ?? -1) !== 4) {
    $fail('Unresolved scope count changed or structural rows were conflated.');
}

$crosswalkById = [];
foreach ($report['crosswalks'] as $row) $crosswalkById[(string) $row['leaf_id']] = $row;
foreach (['cse-constitutional-principles', 'cse-environmental-public-interest', 'cse-ethical-public-service'] as $structuralId) {
    if (isset($crosswalkById[$structuralId])) $fail('Structural CSE placeholder entered the 35-row LET review.');
}
$expectedClassifications = [
    'inclusive-access' => 'VERIFIED',
    'let-ethical-duties' => 'VERIFIED',
    'piaget-stages' => 'CORRECTABLE',
    'prof-development-readiness' => 'DETECTOR_LIMITATION',
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
$inclusive = $crosswalkById['inclusive-universal-design'] ?? null;
if (!is_array($inclusive) || !in_array('let-inclusive-education-review', (array) ($inclusive['material_ids'] ?? []), true)) {
    $fail('Inclusive-education crosswalk lacks its source-first study pathway.');
}
foreach (['301', '312', '5928', '5932', '5937'] as $questionId) {
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
$doctor = (new LetSourceCrosswalkCheck())->run();
if ($doctor->status !== 'WARNING') $fail('Doctor should surface the documented detector limitation as WARNING, got ' . $doctor->status);
if (!str_contains(implode(' ', $doctor->details), 'crosswalks=35')) $fail('Doctor summary omits the audited crosswalk total.');
$registeredChecks = (new CheckRegistry())->fromDirectories([$root . '/tools/Doctor/Project/BoardPrep/Checks']);
$registeredCrosswalkChecks = array_filter($registeredChecks, static fn(object $check): bool => $check instanceof LetSourceCrosswalkCheck);
if (count($registeredCrosswalkChecks) !== 1) $fail('Doctor directory discovery did not register exactly one LET crosswalk check.');

fwrite(STDOUT, 'BATCH673_TEST=PASS' . PHP_EOL);
fwrite(STDOUT, 'CROSSWALKS=35 VERIFIED=15 CORRECTABLE=19 DETECTOR_LIMITATION=1' . PHP_EOL);
fwrite(STDOUT, 'LEARNING_THEORY_EXPLANATIONS_REWRITTEN=' . count($rewrittenIds) . PHP_EOL);
fwrite(STDOUT, 'UNIVERSAL_DESIGN_QUESTIONS=' . count($inclusive['question_ids']) . PHP_EOL);
