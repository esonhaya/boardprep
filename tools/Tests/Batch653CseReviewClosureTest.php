<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/Core/Autoloader.php';
\App\Core\Autoloader::register();
putenv('APP_ENV=testing');
putenv('APP_DEBUG=false');
\App\Core\App::boot();

use App\Core\App;
use App\Repositories\QuestionRepository;
use App\Services\Flashcard\FlashcardCardValidator;
use App\Services\Flashcard\FlashcardDerivationService;
use App\Services\Question\QuestionEligibilityIndex;
use App\Services\Question\QuestionEligibilityService;
use App\Services\Question\QuestionService;
use App\Services\RepositoryHealth\Readiness\SourceReadinessService;

$assert = static function (bool $ok, string $message): void {
    if (!$ok) throw new RuntimeException('Batch653 assertion failed: ' . $message);
};
$read = static fn(string $name): array => App::storage()->all($name);
$canonicalize = static function (mixed $value) use (&$canonicalize): mixed {
    if (!is_array($value)) return $value;
    if (array_is_list($value)) return array_map($canonicalize, $value);
    ksort($value, SORT_STRING);
    foreach ($value as &$child) $child = $canonicalize($child);
    unset($child);
    return $value;
};
$hash = static fn(mixed $value): string => hash('sha256', json_encode($canonicalize($value), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
$artifact = json_decode((string) file_get_contents(dirname(__DIR__, 2) . '/storage/batch653-cse-review-closure.json'), true, 512, JSON_THROW_ON_ERROR);
$questions = $read('questions');
$eligibility = $read('question-eligibility');
$new = array_values(array_filter($questions, static fn(array $q): bool => ($q['batch_marker'] ?? '') === 'batch653-cse'));
$assert(count($new) === 3 && count($artifact['production']['accepted_ids'] ?? []) === 3, 'only the three passing exact plans were added');
$assert(($artifact['production']['wave1_planned'] ?? 0) === 4 && ($artifact['production']['wave1_accepted'] ?? 0) === 3 && ($artifact['production']['wave2_contingent'] ?? 0) === 1 && ($artifact['production']['wave2_accepted'] ?? 0) === 0, 'Wave1 cap and contingent Wave2 decision');
$assert(($artifact['production']['new_id_range'] ?? '') === '8948-8950', 'accepted IDs are contiguous');
$assert(($artifact['cse']['baseline']['native'] ?? 0) === 1550 && ($artifact['cse']['after']['native'] ?? 0) === 1553, 'CSE native delta is exactly three');
$assert(($artifact['cse']['decision'] ?? '') === 'CSE_SMALL_SELECTIVE_EXPANSION_ONLY' && ($artifact['cse']['future_production']['wave3_preauthorized'] ?? -1) === 0, 'evidence ceiling remains closed and Wave3 is unapproved');

$byId = []; foreach ($new as $q) $byId[(string) ($q['batch653']['plan_id'] ?? '')] = $q;
$expectedPlans = ['cse652-343caf16b5e3dc75','cse652-e85215ed9fef916f','cse652-47ac8005d7364ca2'];
sort($expectedPlans, SORT_STRING); $actualPlans = array_keys($byId); sort($actualPlans, SORT_STRING);
$assert($actualPlans === $expectedPlans, 'no substitutions for rejected/contingent plans');
$sourceById = []; foreach ($read('sources') as $row) if (is_array($row)) $sourceById[(string) ($row['id'] ?? '')] = $row;
$materialById = []; foreach ($read('study-materials') as $row) if (is_array($row)) $materialById[(string) ($row['id'] ?? '')] = $row;
$links = $read('study-question-links');
$eligibilityIndex = new QuestionEligibilityIndex($eligibility, $read('blueprints'), $read('concept-derived-eligibility'), $read('question-reuse-overrides'));
$deriver = new FlashcardDerivationService(App::container()->get(QuestionRepository::class));
foreach ($new as $q) {
    $planId = (string) $q['batch653']['plan_id'];
    $assert(in_array($planId, $expectedPlans, true), 'new question maps to authorized plan');
    $assert(QuestionService::validate($q)['valid'] === true, 'question schema: ' . $q['id']);
    $leafId = (string) $q['taxonomy']['concept_id'];
    $record = null;
    foreach ($read('source-readiness-civil-service') as $r) if (($r['leaf_id'] ?? '') === $leafId) $record = $r;
    $assert(is_array($record) && in_array($leafId, (array) ($record['allowed_scope'] ?? []), true), 'recorded plan stays within its authorable scope');
    foreach ((array) ($q['source_ids'] ?? []) as $sourceId) $assert(isset($sourceById[(string) $sourceId]), 'valid source id ' . $sourceId);
    foreach ((array) ($q['study_material_ids'] ?? []) as $materialId) $assert(isset($materialById[(string) $materialId]), 'valid study id ' . $materialId);
    $supportedStudySource = false;
    foreach ((array) ($q['study_material_ids'] ?? []) as $materialId) if (array_intersect((array) $q['source_ids'], (array) ($materialById[$materialId]['source_ids'] ?? [])) !== []) $supportedStudySource = true;
    $assert($supportedStudySource, 'question evidence is supported by linked study material');
    $linkFound = false;
    foreach ($links as $link) if (is_array($link) && (string) ($link['question_id'] ?? '') === (string) $q['id'] && (string) ($link['material_id'] ?? '') === (string) ($q['study_material_id'] ?? '')) $linkFound = true;
    $assert($linkFound, 'study-question trace exists');
    $card = $deriver->derive($q);
    $assert(is_array($card) && FlashcardCardValidator::validate($card) === [], 'flashcard derivability: ' . $q['id']);
    $place = QuestionEligibilityService::forExam($q, 'civil-service', $eligibilityIndex);
    $assert(is_array($place), 'direct CSE placement: ' . $q['id']);
    $assert(in_array('PROFESSIONAL', (array) ($q['civil_service_levels'] ?? []), true), 'explicit professional applicability');
}
$subProfessionalOnly = [];
$professional = [];
$analyticalRequest = new SelectionRequest('analytical-logical', null, [], 10, null, null, 'civil-service', null, null, 'PROFESSIONAL');
$subRequest = new SelectionRequest('analytical-logical', null, [], 10, null, null, 'civil-service', null, null, 'SUBPROFESSIONAL');
$professional = QuestionPoolFilter::filter($new, $analyticalRequest, $eligibilityIndex);
$subProfessionalOnly = QuestionPoolFilter::filter($new, $subRequest, $eligibilityIndex);
$analyticalNewIds = [];
foreach ($new as $q) if (($q['batch653']['official_area_id'] ?? '') === 'analytical-ability') $analyticalNewIds[(string) $q['id']] = true;
$assert(count(array_filter($professional, static fn(array $q): bool => isset($analyticalNewIds[(string) $q['id']]))) === 2, 'analytical items appear for Professional');
$assert(count(array_filter($subProfessionalOnly, static fn(array $q): bool => isset($analyticalNewIds[(string) $q['id']]))) === 0, 'Professional-only analytical items are not selectable for Subprofessional');

$review = (array) ($artifact['cse']['review_records'] ?? []);
$assert(count($review) === 51, 'all 51 original REVIEW leaves were reviewed');
$reviewIds = array_column($review, 'leaf_id');
$assert(count(array_unique($reviewIds)) === 51, 'review leaves have no duplicates');
$assert(count(array_filter($review, static fn(array $r): bool => !in_array($r['disposition'] ?? '', ['PRODUCTION_READY','PRODUCTION_READY_WITH_LIMITS','REVIEW','EVIDENCE_EXHAUSTED','NO_LONGER_UNDER10','BLOCKED'], true))) === 0, 'all review leaves have exactly one disposition');
$assert(($artifact['cse']['area_mismatch_remaining']['analytical-sequence-rule']['status'] ?? '') === 'BLOCKED_NO_REMAP', 'number-sequence mismatch remains quarantined without remap');
$assert(($artifact['cse']['true_authorable_health']['authorable_leaf_count'] ?? -1) === 0 && ($artifact['cse']['future_production']['candidate_leaves'] ?? -1) === 0, 'no unproven new cohort is authorized');
$readiness = $read('source-readiness-civil-service');
$assert(count($readiness) === 56 && count(array_filter($readiness, static fn(array $r): bool => ($r['readiness'] ?? '') === 'REVIEW' && (int) ($r['max_new_questions'] ?? -1) === 0 && (int) ($r['planned_new_questions'] ?? -1) === 0)) === 56, 'all final readiness records are validly blocked from automatic authoring');
$collections = ['questions'=>$questions,'question-eligibility'=>$eligibility,'concept-derived-eligibility'=>$read('concept-derived-eligibility'),'sources'=>$read('sources'),'study-materials'=>$read('study-materials'),'board-concept-coverage'=>$read('board-concept-coverage'),'taxonomy/concepts'=>$read('taxonomy/concepts'),'source-readiness-civil-service'=>$readiness];
$readinessValidation = SourceReadinessService::validateCollections($collections);
$assert(($readinessValidation['by_board']['civil-service']['valid'] ?? false) === true, 'permanent SourceReadiness validator passes');

$newIds = array_fill_keys(array_map(static fn(array $q): string => (string) $q['id'], $new), true);
$questionsWithoutNew = array_values(array_filter($questions, static fn(array $q): bool => !isset($newIds[(string) ($q['id'] ?? '')])));
$assert(hash_equals((string) $artifact['content_freeze']['question_hash_before'], $hash($questionsWithoutNew)), 'question store semantic hash is unchanged outside three additions');
$eligibilityWithoutNew = [];
foreach ($eligibility as $entry) {
    if (!is_array($entry)) continue;
    $entry['question_ids'] = array_values(array_filter((array) ($entry['question_ids'] ?? []), static fn(mixed $id): bool => !isset($newIds[(string) $id])));
    foreach (array_keys((array) ($entry['question_mappings'] ?? [])) as $id) if (isset($newIds[(string) $id])) unset($entry['question_mappings'][$id]);
    if ($entry['question_ids'] !== []) $eligibilityWithoutNew[] = $entry;
}
$assert(hash_equals((string) $artifact['content_freeze']['eligibility_hash_before'], $hash($eligibilityWithoutNew)), 'eligibility changes are limited to the three new direct CSE mappings');
$assert(hash_equals((string) $artifact['content_freeze']['question_hash_after_review'], $hash($questions)), 'question store remained frozen after production');
$assert(($artifact['global_regressions']['native_by_board_after']['nursing'] ?? 0) === 878 && ($artifact['global_regressions']['native_by_board_after']['mechanical-engineering'] ?? 0) === 511, 'other-board native counts unchanged');
$assert(($artifact['global_regressions']['nursing_dosage'] ?? '') === '24/16/8' && ($artifact['global_regressions']['cpa_cy2029_leakage'] ?? -1) === 0, 'inherited hard regressions retained');

echo "BATCH653_TEST=PASS\nAUTHORIZED_NEW=" . count($new) . "\nREVIEW_LEAVES=" . count($review) . "\nREADINESS_VALID=" . (($readinessValidation['by_board']['civil-service']['valid'] ?? false) ? 'YES' : 'NO') . PHP_EOL;
