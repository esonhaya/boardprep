<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/Core/Autoloader.php';

use App\Core\App;
use App\Core\Autoloader;
use App\Services\RepositoryHealth\Engine\RepositoryHealthEngine;
use App\Services\RepositoryHealth\Engine\RepositoryContextFactory;
use App\Services\RepositoryHealth\Readiness\SourceReadinessService;
use App\Services\Shared\QuestionValidationService;

Autoloader::register();
putenv('APP_ENV=testing');
putenv('APP_DEBUG=false');
App::boot();

$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};

$collections = [];
foreach (RepositoryContextFactory::collectionNames() as $name) {
    $collections[$name] = App::storage()->all($name);
}

$psychQuestions = array_values(array_filter(
    (array) ($collections['questions'] ?? []),
    static fn(mixed $question): bool => is_array($question)
        && strtolower((string) ($question['taxonomy']['board_id'] ?? '')) === 'psychometrician'
));
$assert(count($psychQuestions) === 380, 'Psychometrician native question count changed.');

$leafCounts = SourceReadinessService::nativeLeafCounts($collections, 'psychometrician');
$assert(count($leafCounts) === 20, 'Psychometrician does not expose exactly 20 active remapped leaves.');
$assert(!array_intersect(array_keys($leafCounts), [
    'ple-valid-measurement',
    'ple-developmental-principles',
    'ple-clinical-classification',
    'ple-workplace-behavior',
]), 'A structural Psychometrician parent was still counted as an active leaf.');

$readinessValidation = SourceReadinessService::validateCollections($collections);
$psychReadiness = $readinessValidation['by_board']['psychometrician'] ?? [];
$assert(($readinessValidation['valid'] ?? false) === true, 'Psychometrician readiness contract is invalid.');
$assert(count($psychReadiness['records'] ?? []) === 20, 'Psychometrician readiness coverage is not one record per active leaf.');
$assert(count(array_unique(array_map(
    static fn(array $record): string => (string) ($record['leaf_id'] ?? ''),
    $psychReadiness['records'] ?? []
))) === 20, 'Psychometrician readiness contains duplicate leaf IDs.');

$health = RepositoryHealthEngine::analyzeCollections($collections);
$psychAreas = $health->boardSummaries['psychometrician']['official_area_balance'] ?? [];
$areaShares = [];
foreach ($psychAreas as $area) {
    if (is_array($area)) {
        $areaShares[(string) ($area['area_id'] ?? '')] = (float) ($area['actual_share'] ?? -1);
    }
}
$assert($areaShares === [
    'psychological-assessment' => 40.0,
    'developmental-psychology' => 20.0,
    'abnormal-psychology' => 20.0,
    'industrial-organizational' => 20.0,
], 'Psychometrician official-area balance did not resolve taxonomy domain IDs through the blueprint sections.');

$targetLeaves = array_fill_keys(array_keys($leafCounts), true);
$questionIds = [];
foreach ($psychQuestions as $question) {
    $questionId = (string) ($question['id'] ?? '');
    $questionIds[] = $questionId;
    $leafId = (string) ($question['taxonomy']['concept_id'] ?? '');
    $assert(isset($targetLeaves[$leafId]), "Question {$questionId} is not mapped to a target Psychometrician leaf.");
    $assert((QuestionValidationService::validate($question)['valid'] ?? false) === true, "Question {$questionId} failed taxonomy/content validation.");
}
$assert(count($questionIds) === count(array_unique($questionIds)), 'Psychometrician question IDs are duplicated.');

$concepts = [];
foreach ((array) ($collections['taxonomy/concepts'] ?? []) as $concept) {
    if (is_array($concept)) {
        $concepts[(string) ($concept['id'] ?? '')] = $concept;
    }
}
foreach (array_keys($targetLeaves) as $leafId) {
    $assert(isset($concepts[$leafId]), "Target leaf {$leafId} has no taxonomy concept record.");
    $parent = (string) ($concepts[$leafId]['parent_concept_id'] ?? '');
    $assert(in_array($parent, [
        'ple-valid-measurement',
        'ple-developmental-principles',
        'ple-clinical-classification',
        'ple-workplace-behavior',
    ], true), "Target leaf {$leafId} is not under a preserved structural parent.");
}

$authorableRecord = null;
foreach ($psychReadiness['records'] ?? [] as $record) {
    if (is_array($record) && in_array((string) ($record['readiness'] ?? ''), ['READY', 'READY_WITH_LIMITS'], true)
        && (int) ($record['current_question_count'] ?? 0) < (int) ($record['current_question_count'] ?? 0) + (int) ($record['max_new_questions'] ?? 0)) {
        $authorableRecord = $record;
        break;
    }
}
$assert(is_array($authorableRecord), 'No authorable Psychometrician readiness fixture was found.');
$authoringFixture = $psychQuestions[0];
$authoringFixture['id'] = 'batch642-readiness-gate-fixture';
$authoringFixture['taxonomy']['concept_id'] = (string) $authorableRecord['leaf_id'];
$authoringFixture['reuse']['required_depth'] = (int) $authorableRecord['supported_depth_min'];
$authoringFixture['source_scope'] = [(string) $authorableRecord['allowed_scope'][0]];
$authoringFixture['source_ids'] = [(string) $authorableRecord['source_ids'][0]];
$authoringFixture['study_material_ids'] = [(string) $authorableRecord['study_material_ids'][0]];
$authoringGate = SourceReadinessService::validateQuestionForCreate($authoringFixture, $collections);
$assert(($authoringGate['valid'] ?? false) === true, 'A bounded Psychometrician authoring fixture did not pass the readiness gate.');
$authoringFixture['source_scope'] = ['future-effective-material'];
$blockedGate = SourceReadinessService::validateQuestionForCreate($authoringFixture, $collections);
$assert(($blockedGate['valid'] ?? true) === false && in_array('SOURCE_READINESS_SCOPE_OUTSIDE_BOUNDARY', $blockedGate['errors'] ?? [], true), 'The Psychometrician readiness gate accepted excluded scope.');

$artifactPath = dirname(__DIR__, 2) . '/storage/batch642-psychometrician-taxonomy-readiness.json';
$artifact = json_decode((string) file_get_contents($artifactPath), true, 512, JSON_THROW_ON_ERROR);
$assert(count($artifact['question_classifications'] ?? []) === 380, 'Classification artifact does not cover all 380 questions.');
$assert(($artifact['remap_invariants']['question_ids_changed'] ?? 1) === 0, 'Classification artifact reports changed question IDs.');
$assert(($artifact['remap_invariants']['stems_changed'] ?? 1) === 0, 'Classification artifact reports changed stems.');
$assert(($artifact['remap_invariants']['choices_changed'] ?? 1) === 0, 'Classification artifact reports changed choices.');
$assert(($artifact['remap_invariants']['answer_keys_changed'] ?? 1) === 0, 'Classification artifact reports changed answer keys.');
$assert(($artifact['remap_invariants']['source_study_changed'] ?? 1) === 0, 'Classification artifact reports changed source/study semantics.');

echo "[PASS] Psychometrician structural-leaf separation, 380-question remap, readiness coverage, cross-board parent safety, and invariants verified.\n";
