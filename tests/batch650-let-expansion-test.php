<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/Core/Autoloader.php';

use App\Core\App;
use App\Core\Autoloader;
use App\Repositories\QuestionRepository;
use App\Services\Flashcard\FlashcardCardValidator;
use App\Services\Flashcard\FlashcardDerivationService;
use App\Services\Question\QuestionService;
use App\Services\RepositoryHealth\Engine\RepositoryContextFactory;
use App\Services\RepositoryHealth\Readiness\SourceReadinessService;

Autoloader::register();
putenv('APP_ENV=testing');
putenv('APP_DEBUG=false');
App::boot();

$assert = static function (bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
};
$root = dirname(__DIR__);
$artifact = json_decode((string) file_get_contents($root . '/storage/batch650-let-expansion.json'), true, 512, JSON_THROW_ON_ERROR);
$batch649 = json_decode((string) file_get_contents($root . '/storage/batch649-let-source-readiness.json'), true, 512, JSON_THROW_ON_ERROR);
$collections = [];
foreach (RepositoryContextFactory::collectionNames() as $name) $collections[$name] = App::storage()->all($name);
$questions = array_values(array_filter($collections['questions'], 'is_array'));
$batchQuestions = array_values(array_filter($questions, static fn(array $q): bool => ($q['batch_marker'] ?? '') === 'batch650'));
$wave1 = array_values(array_filter($batchQuestions, static fn(array $q): bool => (int) ($q['batch650']['wave'] ?? 0) === 1));
$wave2 = array_values(array_filter($batchQuestions, static fn(array $q): bool => (int) ($q['batch650']['wave'] ?? 0) === 2));
$assert(($artifact['lineage']['expected_parent'] ?? '') === '0eb9d8e09c28c386cfd3b9acab54a1330ab6e469', 'Batch650 parent is not the authoritative Batch649 checkpoint.');
$assert(count($batchQuestions) === 40 && count($wave1) === 33 && count($wave2) === 7, 'Batch650 production delta must be exactly 33 Wave1 and 7 Wave2 questions.');
$ids = array_map(static fn(array $q): int => (int) $q['id'], $batchQuestions);
sort($ids, SORT_NUMERIC);
$assert(count(array_unique($ids)) === 40 && $ids === range(8904, 8943), 'Batch650 question IDs must be unique and contiguous with no rejected-draft gaps.');
$wave1Ids = array_map(static fn(array $q): int => (int) $q['id'], $wave1);
$wave2Ids = array_map(static fn(array $q): int => (int) $q['id'], $wave2);
sort($wave1Ids, SORT_NUMERIC);
sort($wave2Ids, SORT_NUMERIC);
$assert($wave1Ids === range(8904, 8936) && $wave2Ids === range(8937, 8943), 'Wave-specific canonical ID ranges differ from the persisted plan.');
$assert(($artifact['wave1']['planned'] ?? 0) === 33 && ($artifact['wave1']['accepted'] ?? 0) === 33 && ($artifact['wave1']['candidate_leaves'] ?? 0) === 32, 'Wave1 did not execute the exact stored Batch649 plan.');
$assert(($artifact['wave2']['candidate_leaves'] ?? 0) === 95 && ($artifact['wave2']['planned'] ?? 0) === 7 && ($artifact['wave2']['accepted'] ?? 0) === 7, 'Wave2 differs from its fresh evidence-gated production record.');
$assert(($artifact['validation']['new_questions_professional_education'] ?? -1) === 0 && ($artifact['validation']['new_questions_shared_review_leaves'] ?? -1) === 0, 'Wave2 authored into blocked ProfEd or shared REVIEW scope.');
$assert(($artifact['validation']['existing_question_semantics_unchanged'] ?? false) === true && ($artifact['validation']['existing_eligibility_semantics_unchanged'] ?? false) === true, 'Existing question or eligibility semantics changed.');
$assert(($artifact['validation']['new_ids_contiguous'] ?? false) === true && ($artifact['validation']['question_deletions'] ?? -1) === 0 && ($artifact['validation']['question_semantic_rewrites'] ?? -1) === 0, 'Content-freeze/ID integrity checks failed.');
$parentCommit = '0eb9d8e09c28c386cfd3b9acab54a1330ab6e469';
$parentQuestionJson = shell_exec('git show ' . escapeshellarg($parentCommit . ':storage/questions.json'));
$parentEligibilityJson = shell_exec('git show ' . escapeshellarg($parentCommit . ':storage/question-eligibility.json'));
$assert(is_string($parentQuestionJson) && is_string($parentEligibilityJson), 'Could not load the immutable Batch649 production baseline from Git.');
$normalize = static function (mixed $node) use (&$normalize): mixed {
    if (!is_array($node)) return $node;
    if (array_is_list($node)) return array_map($normalize, $node);
    ksort($node, SORT_STRING);
    foreach ($node as &$child) $child = $normalize($child);
    unset($child);
    return $node;
};
$hash = static fn(mixed $value): string => hash('sha256', json_encode($normalize($value), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
$parentQuestions = json_decode($parentQuestionJson, true, 512, JSON_THROW_ON_ERROR);
$existingQuestions = array_values(array_filter($questions, static fn(array $q): bool => ($q['batch_marker'] ?? '') !== 'batch650'));
$assert(hash_equals($hash($parentQuestions), $hash($existingQuestions)), 'Question-store content differs semantically from the Batch649 parent.');
$batchIds = array_fill_keys(array_map('strval', array_column($batchQuestions, 'id')), true);
$existingEligibility = [];
foreach ($collections['question-eligibility'] as $row) {
    if (!is_array($row)) continue;
    $row['question_ids'] = array_values(array_filter((array) ($row['question_ids'] ?? []), static fn($id): bool => !isset($batchIds[(string) $id])));
    foreach (array_keys((array) ($row['question_mappings'] ?? [])) as $mappedId) if (isset($batchIds[(string) $mappedId])) unset($row['question_mappings'][$mappedId]);
    if ($row['question_ids'] !== []) $existingEligibility[] = $row;
}
$parentEligibility = json_decode($parentEligibilityJson, true, 512, JSON_THROW_ON_ERROR);
$assert(hash_equals($hash($parentEligibility), $hash($existingEligibility)), 'Eligibility-store content differs semantically from the Batch649 parent.');

$baselineReadiness = [];
foreach ((array) ($batch649['let']['leaves'] ?? []) as $leaf) $baselineReadiness[(string) ($leaf['leaf_id'] ?? '')] = (array) ($leaf['readiness_record'] ?? []);
$currentReadiness = [];
foreach ($collections['source-readiness-let'] as $record) if (is_array($record)) $currentReadiness[(string) ($record['leaf_id'] ?? '')] = $record;
$assert(count($currentReadiness) === 205, 'LET readiness must retain all 205 active leaves.');
$parentReadinessJson = shell_exec('git show ' . escapeshellarg($parentCommit . ':storage/source-readiness-let.json'));
$assert(is_string($parentReadinessJson), 'Could not load Batch649 readiness baseline from Git.');
$parentReadiness = json_decode($parentReadinessJson, true, 512, JSON_THROW_ON_ERROR);
$parentReadinessByLeaf = [];
foreach ($parentReadiness as $record) if (is_array($record)) $parentReadinessByLeaf[(string) ($record['leaf_id'] ?? '')] = $record;
$newByLeaf = [];
$usedFamiliesByLeaf = [];
foreach ($batchQuestions as $question) {
    $leaf = (string) ($question['taxonomy']['concept_id'] ?? '');
    $newByLeaf[$leaf] = ($newByLeaf[$leaf] ?? 0) + 1;
    $usedFamiliesByLeaf[$leaf][(string) ($question['batch650']['content_family'] ?? '')] = true;
}
$assert(count($newByLeaf) === 39, 'Exactly 39 leaf records should consume Batch650 readiness headroom.');
foreach ($parentReadinessByLeaf as $leaf => $before) {
    $after = $currentReadiness[$leaf] ?? [];
    $used = (int) ($newByLeaf[$leaf] ?? 0);
    if ($used === 0) {
        $assert($normalize($before) === $normalize($after), 'An unaffected LET readiness record changed: ' . $leaf);
        continue;
    }
    $assert((int) ($after['current_question_count'] ?? -1) === (int) ($before['current_question_count'] ?? 0) + $used, 'Readiness question count was not consumed accurately: ' . $leaf);
    foreach (['safe_headroom_min', 'safe_headroom_preferred', 'safe_headroom_max', 'max_new_questions'] as $field) {
        $assert((int) ($after[$field] ?? -1) === max(0, (int) ($before[$field] ?? 0) - $used), 'Readiness headroom was stale or overconsumed: ' . $leaf . '/' . $field);
    }
    $remainingFamilies = array_values(array_filter((array) ($before['distinct_content_families'] ?? []), static fn(string $family): bool => !isset($usedFamiliesByLeaf[$leaf][$family])));
    sort($remainingFamilies, SORT_STRING);
    $currentFamilies = array_values((array) ($after['distinct_content_families'] ?? []));
    sort($currentFamilies, SORT_STRING);
    $assert($currentFamilies === $remainingFamilies, 'Readiness retained a consumed family or lost unused evidence: ' . $leaf);
}
$readinessCheck = SourceReadinessService::validateCollections($collections);
$assert(($readinessCheck['by_board']['let']['valid'] ?? false) === true && count($readinessCheck['by_board']['let']['records'] ?? []) === 205, 'Permanent LET readiness contract failed.');
$sources = [];
foreach ($collections['sources'] as $source) if (is_array($source)) $sources[(string) ($source['id'] ?? '')] = $source;
$materials = [];
foreach ($collections['study-materials'] as $material) if (is_array($material)) $materials[(string) ($material['id'] ?? '')] = $material;
$linksByQuestion = [];
foreach ($collections['study-question-links'] as $link) if (is_array($link)) $linksByQuestion[(string) ($link['question_id'] ?? '')][] = $link;
$eligibilityByQuestion = [];
foreach ($collections['question-eligibility'] as $row) {
    if (!is_array($row)) continue;
    foreach (array_map('strval', (array) ($row['question_ids'] ?? [])) as $id) $eligibilityByQuestion[$id][] = $row;
}
$flashcards = new FlashcardDerivationService(
    App::container()->get(QuestionRepository::class),
    $collections['study-materials'],
    $collections['study-question-links'],
    $collections['flashcard-exclusions']
);
$normalizedStems = [];
$specializationNew = 0;
$genedNew = 0;
$calculationQuestions = 0;
foreach ($batchQuestions as $question) {
    $id = (string) $question['id'];
    $leaf = (string) ($question['taxonomy']['concept_id'] ?? '');
    $evidence = $baselineReadiness[$leaf] ?? [];
    $current = $currentReadiness[$leaf] ?? [];
    $materialId = (string) ($question['study_material_id'] ?? '');
    $material = $materials[$materialId] ?? [];
    $sourceIds = array_values(array_map('strval', (array) ($question['source_ids'] ?? [])));
    $family = (string) ($question['batch650']['content_family'] ?? '');
    $depth = (int) ($question['reuse']['required_depth'] ?? $question['batch650']['depth'] ?? 0);
    $assert(($evidence['readiness'] ?? '') === 'READY_WITH_LIMITS', 'Question was not authorized by a Batch649 RWL record: ' . $id);
    $assert(($question['batch650']['readiness_before'] ?? '') === 'READY_WITH_LIMITS', 'Question lacks its authorable-state record: ' . $id);
    $assert(($evidence['source_currentness'] ?? '') === 'NOT_TIME_SENSITIVE' || in_array(($evidence['source_currentness'] ?? ''), ['CURRENT', 'CURRENT_WITH_LIMITS'], true), 'Question evidence currentness is not authorable: ' . $id);
    $assert(in_array($leaf, (array) ($evidence['allowed_scope'] ?? []), true) && !in_array($leaf, (array) ($evidence['excluded_scope'] ?? []), true), 'Question escaped its RWL source scope: ' . $id);
    $assert(in_array($family, (array) ($evidence['distinct_content_families'] ?? []), true), 'Question family is outside the source-readiness evidence: ' . $id);
    $assert($depth >= (int) ($evidence['supported_depth_min'] ?? 1) && $depth <= (int) ($evidence['supported_depth_max'] ?? 5), 'Question exceeds supported depth: ' . $id);
    $assert(in_array($materialId, (array) ($evidence['study_material_ids'] ?? []), true) && ($material['status'] ?? '') === 'published', 'Question study material is not authorized/published: ' . $id);
    $assert(array_diff($sourceIds, (array) ($evidence['source_ids'] ?? [])) === [] && array_diff($sourceIds, array_keys($sources)) === [], 'Question has an invalid or unauthorized source ID: ' . $id);
    $assert(array_diff($sourceIds, array_map('strval', (array) ($material['source_ids'] ?? []))) === [], 'Question source is not represented by its study material: ' . $id);
    $assert(count($linksByQuestion[$id] ?? []) === 1, 'Question must have exactly one study/source trace link: ' . $id);
    $link = $linksByQuestion[$id][0];
    $assert((string) ($link['material_id'] ?? '') === $materialId && array_diff($sourceIds, array_map('strval', (array) ($link['source_ids'] ?? []))) === [], 'Question study trace does not resolve: ' . $id);
    $schema = QuestionService::validate($question);
    $assert(($schema['valid'] ?? false) === true, 'Canonical question schema validation failed: ' . $id);
    $stem = mb_strtolower(trim((string) preg_replace('/\s+/u', ' ', (string) ($question['question'] ?? ''))));
    $assert(!isset($normalizedStems[$stem]), 'Duplicate normalized Batch650 stem: ' . $id);
    $normalizedStems[$stem] = true;
    $card = $flashcards->derive($question);
    $assert(is_array($card) && FlashcardCardValidator::validate($card) === [], 'Flashcard derivation/validation failed: ' . $id);
    $assert(($question['batch650']['derivable'] ?? false) === true, 'Question lacks derivability evidence: ' . $id);
    $placements = [];
    foreach ($eligibilityByQuestion[$id] ?? [] as $row) {
        $mapping = (array) (($row['question_mappings'][$id]['placements']['let'] ?? []));
        if ($mapping !== []) $placements[] = $mapping;
    }
    $assert(count($placements) === 1, 'Question must have one explicit LET placement: ' . $id);
    $placement = $placements[0];
    $assert((string) ($placement['concept_id'] ?? '') === $leaf, 'Eligibility placement differs from the authored leaf: ' . $id);
    if ((int) ($question['batch650']['wave'] ?? 0) === 2 && (string) ($question['batch650']['specialization_id'] ?? '') !== '') {
        $specializationNew++;
        $specializationId = (string) $question['batch650']['specialization_id'];
        $assert((string) ($placement['specialization_id'] ?? '') === $specializationId && (string) ($placement['let_component'] ?? '') === 'SPECIALIZATION', 'Specialization item is not natively placed: ' . $id);
        $leafEvidence = [];
        foreach ((array) ($batch649['let']['leaves'] ?? []) as $row) if (($row['leaf_id'] ?? '') === $leaf) $leafEvidence = $row;
        $assert(in_array($specializationId, (array) ($leafEvidence['native_specialization_ids'] ?? []), true), 'Shared availability was mistaken for native specialization depth: ' . $id);
    } else {
        if ((int) ($question['batch650']['wave'] ?? 0) === 2) {
            $genedNew++;
            $assert((string) ($placement['blueprint_node'] ?? '') === 'general-education' && empty($placement['specialization_id']), 'General Education item acquired specialization placement: ' . $id);
        }
    }
    if (($question['calculation_required'] ?? false) === true) {
        $calculationQuestions++;
        $values = array_values((array) ($question['calculation']['values'] ?? []));
        $expected = match ((string) ($question['calculation']['family'] ?? '')) {
            'function_evaluation' => $values[0] * $values[1] + $values[2],
            'arithmetic_sequence_term' => $values[0] + ($values[2] - 1) * $values[1],
            'fraction_of_a_set' => ($values[0] / $values[1]) * $values[2],
            'linear_model_solution' => ($values[0] - $values[1]) / $values[2],
            default => null,
        };
        $assert($expected !== null && (float) $expected === (float) ($question['calculation']['expected'] ?? NAN), 'Calculation metadata failed independent recomputation: ' . $id);
        $answer = (string) ($question['answer'] ?? '');
        $assert($answer === (string) $expected || str_starts_with($answer, (string) $expected . ' '), 'Calculation answer or unit does not match recomputation: ' . $id);
    }
    $baselineState = (string) ($evidence['readiness'] ?? '');
    $assert(in_array($baselineState, ['READY', 'READY_WITH_LIMITS'], true) && ($current['current_question_count'] ?? -1) >= ($evidence['current_question_count'] ?? 0), 'Readiness/headroom consumption is stale: ' . $id);
}
$assert($specializationNew === 6 && $genedNew === 1 && $calculationQuestions === 4, 'Wave2 component/calculation allocation differs from the evidence gate.');
$assert(($artifact['final']['let_native'] ?? -1) === 1784 && ($artifact['final']['total_canonical'] ?? -1) === 8943, 'Final canonical/native counts do not include exactly the 40-question LET delta.');
$assert(($artifact['final']['authorable']['zero'] ?? -1) === 0 && ($artifact['final']['authorable']['under5'] ?? -1) === 0, 'Wave1/2 failed to close the authorable zero/under-5 cohort.');
$assert(($artifact['final']['zero_specialization_pools'] ?? -1) === 0, 'A LET specialization pool has zero native questions.');
$assert(($artifact['final']['native_by_board']['mechanical-engineering'] ?? -1) === 511 && ($artifact['final']['native_by_board']['nursing'] ?? -1) === 878 && ($artifact['final']['native_by_board']['cpa'] ?? -1) === 1132, 'Unrelated board native counts changed.');
$assert(($artifact['health_regressions_expected']['profed_unchanged'] ?? false) === true, 'Professional Education should remain untouched.');
$assert(($artifact['validation']['source_study_traceability_count'] ?? -1) === 40 && ($artifact['validation']['flashcard_derivability_count'] ?? -1) === 40 && ($artifact['validation']['calculation_recomputation_defects'] ?? -1) === 0, 'Durable artifact reports QA failures.');
$assert(($artifact['validation']['wave1_failures'] ?? ['unexpected']) === [] && ($artifact['validation']['wave2_failures'] ?? ['unexpected']) === [], 'Durable audit contains question defects.');

echo json_encode([
    'status' => 'PASS',
    'batch650_questions' => count($batchQuestions),
    'wave1' => count($wave1),
    'wave2' => count($wave2),
    'id_range' => min($ids) . '-' . max($ids),
    'readiness_records' => count($currentReadiness),
    'source_study_traceability' => count($batchQuestions),
    'flashcards' => count($batchQuestions),
    'calculation_recomputed' => $calculationQuestions,
    'wave2_specialization' => $specializationNew,
    'wave2_gened' => $genedNew,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL;
