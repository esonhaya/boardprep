<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/Core/Autoloader.php';

use App\Core\App;
use App\Core\Autoloader;
use App\Services\RepositoryHealth\Engine\RepositoryContextFactory;
use App\Services\RepositoryHealth\Readiness\SourceReadinessService;

Autoloader::register();
putenv('APP_ENV=testing');
putenv('APP_DEBUG=false');
App::boot();

$assert = static function (bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
};

$collections = [];
foreach (RepositoryContextFactory::collectionNames() as $name) {
    $collections[$name] = App::storage()->all($name);
}
$artifact = json_decode((string) file_get_contents(dirname(__DIR__) . '/storage/batch644-mechanical-engineering-taxonomy-readiness.json'), true, 512, JSON_THROW_ON_ERROR)[0] ?? [];
$batch646 = json_decode((string) file_get_contents(dirname(__DIR__) . '/storage/batch646-me-source-readiness-closure.json'), true, 512, JSON_THROW_ON_ERROR);
$readiness = $collections['source-readiness-mechanical-engineering'] ?? [];
$validation = SourceReadinessService::validateCollections($collections);
$meValidation = $validation['by_board']['mechanical-engineering'] ?? [];
$assert(count($readiness) === 35, 'ME must retain exactly 35 readiness records.');
$assert(($meValidation['valid'] ?? false) === true, 'Permanent readiness validation failed for ME.');
$assert(count($meValidation['records'] ?? []) === 35, 'Readiness validation did not resolve all 35 active leaves.');

$byLeaf = [];
foreach ($readiness as $record) $byLeaf[(string) ($record['leaf_id'] ?? '')] = $record;
$assert(count($byLeaf) === 35, 'ME readiness leaves are duplicated.');
$counts = ['READY' => 0, 'READY_WITH_LIMITS' => 0, 'REVIEW' => 0, 'DEFER' => 0];
foreach ($byLeaf as $leafId => $record) {
    $status = (string) ($record['readiness'] ?? '');
    if (isset($counts[$status])) $counts[$status]++;
    if (!in_array($status, ['READY', 'READY_WITH_LIMITS'], true)) continue;
    $allowed = (array) ($record['allowed_scope'] ?? []);
    $sources = (array) ($record['source_ids'] ?? []);
    $studies = (array) ($record['study_material_ids'] ?? []);
    $sample = [
        'taxonomy' => ['board_id' => 'mechanical-engineering', 'concept_id' => $leafId],
        'reuse' => ['required_depth' => (int) $record['supported_depth_min']],
        'source_scope' => [$allowed[0] ?? ''],
        'source_ids' => [$sources[0] ?? ''],
        'study_material_ids' => [$studies[0] ?? ''],
    ];
    $gate = SourceReadinessService::validateQuestionForCreate($sample, $collections);
    $assert(($gate['valid'] ?? false) === true, 'Permanent authoring gate rejected bounded sample for ' . $leafId . ': ' . implode(',', $gate['errors'] ?? []));
}
$assert($counts === ['READY' => 0, 'READY_WITH_LIMITS' => 34, 'REVIEW' => 1, 'DEFER' => 0], 'Unexpected final readiness-state counts: ' . json_encode($counts));

$hvacId = 'b644-me-hvac-refrigeration';
$hvac = $byLeaf[$hvacId] ?? [];
$hvacSample = [
    'taxonomy' => ['board_id' => 'mechanical-engineering', 'concept_id' => $hvacId],
    'reuse' => ['required_depth' => (int) ($hvac['supported_depth_min'] ?? 2)],
    'source_scope' => [(string) (($hvac['allowed_scope'][0] ?? ''))],
    'source_ids' => [(string) (($hvac['source_ids'][0] ?? ''))],
    'study_material_ids' => [(string) (($hvac['study_material_ids'][0] ?? ''))],
];
$hvacGate = SourceReadinessService::validateQuestionForCreate($hvacSample, $collections);
$assert(($hvac['readiness'] ?? '') === 'REVIEW' && in_array('SOURCE_READINESS_NOT_AUTHORABLE', $hvacGate['errors'] ?? [], true), 'HVAC REVIEW must remain blocked by the permanent authoring gate.');

$mutated = $collections;
foreach ($mutated['source-readiness-mechanical-engineering'] as &$record) {
    if (($record['leaf_id'] ?? '') === $hvacId) {
        $record['source_ids'][] = 'batch646-missing-source-fixture';
        break;
    }
}
unset($record);
$invalid = SourceReadinessService::validateCollections($mutated);
$assert(($invalid['by_board']['mechanical-engineering']['valid'] ?? true) === false, 'A missing source ID must remain detectable.');
$fresh = [];
foreach (RepositoryContextFactory::collectionNames() as $name) $fresh[$name] = App::storage()->all($name);
$freshValidation = SourceReadinessService::validateCollections($fresh);
$assert(($freshValidation['by_board']['mechanical-engineering']['valid'] ?? false) === true, 'A sequential fresh validation observed stale in-memory state.');

$questionHash = hash_file('sha256', dirname(__DIR__) . '/storage/questions.json');
$assert($questionHash !== false && hash_equals((string) ($batch646['taxonomy_and_questions']['question_store_sha256_before'] ?? ''), $questionHash), 'Question store changed during Batch646.');
$assert(($batch646['taxonomy_and_questions']['question_store_unchanged'] ?? false) === true, 'Audit artifact does not attest the frozen question bank.');
$assert(($batch646['validation']['traceability'] ?? '') === 'PASS', 'Audit traceability result must reflect resolved study/source chains.');
$assert((int) ($batch646['readiness_revalidation']['broken_traceability'] ?? -1) === 0, 'All readiness study/source chains must resolve.');
$readinessEvidence = (array) ($batch646['readiness_records'] ?? []);
$assert((int) ($readinessEvidence['record_count'] ?? 0) === 35 && count($readinessEvidence['records'] ?? []) === 35, 'Durable audit artifact must include all 35 ME readiness records.');
$sourceInventory = (array) ($batch646['source_inventory'] ?? []);
$studyInventory = (array) ($batch646['study_inventory'] ?? []);
$assert(($sourceInventory['all_readiness_and_study_source_ids_resolve'] ?? false) === true, 'Durable source inventory contains an unresolved source ID.');
$assert(($studyInventory['all_source_ids_resolve'] ?? false) === true && ($studyInventory['all_taxonomy_ids_resolve'] ?? false) === true, 'Durable study inventory contains a broken source or taxonomy link.');
$unapprovedStudySourceRefs = (int) ($batch646['study_changes']['study_sources_outside_leaf_readiness_allowlist'] ?? -1);
$unapprovedStudySourceEvidence = (array) ($batch646['study_changes']['study_sources_outside_leaf_readiness_allowlist_evidence'] ?? []);
$assert($unapprovedStudySourceRefs >= 0 && $unapprovedStudySourceRefs === count($unapprovedStudySourceEvidence), 'Out-of-allowlist references must be reported separately, not mislabeled as broken traceability.');
$assert(count((array) ($artifact['question_classifications'] ?? [])) === 464, 'Batch644 classification baseline no longer covers all 464 ME questions.');

echo json_encode([
    'status' => 'PASS',
    'readiness_records' => count($byLeaf),
    'readiness_counts' => $counts,
    'authorable_gate_samples' => 34,
    'review_gate_blocked' => $hvacId,
    'missing_id_detection' => true,
    'fresh_sequential_validation' => true,
    'question_bank_unchanged' => true,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL;
