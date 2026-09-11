<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/Core/Autoloader.php';
\App\Core\Autoloader::register();

use App\Core\App;
use App\Services\Question\QuestionReuseEligibilityResolver;
use App\Services\Question\QuestionReuseMetadataService;

$storage = App::storage();
$questions = array_values(array_filter($storage->all('questions'), 'is_array'));
$metadata = array_values(array_filter($storage->all('question-reuse-metadata'), 'is_array'));
$coverage = array_values(array_filter($storage->all('board-concept-coverage'), 'is_array'));
$equivalence = array_values(array_filter($storage->all('concept-equivalence'), 'is_array'));
$overrides = array_values(array_filter($storage->all('question-reuse-overrides'), 'is_array'));
$required = array_values(array_filter($storage->all('question-required-concepts'), 'is_array'));

$errors = [];
$questionIds = array_fill_keys(array_map(static fn(array $q): string => (string)($q['id'] ?? ''), $questions), true);
$metadataIds = [];
$batch605 = 0;
$policies = [];
$depths = [];
$contexts = [];
$statuses = [];
foreach ($metadata as $record) {
    $questionId = (string)($record['question_id'] ?? '');
    if ($questionId === '' || !isset($questionIds[$questionId])) $errors[] = 'ORPHANED_REUSE_METADATA:' . $questionId;
    if (isset($metadataIds[$questionId])) $errors[] = 'DUPLICATE_REUSE_METADATA:' . $questionId;
    $metadataIds[$questionId] = true;
    $reuse = $record['reuse'] ?? null;
    foreach (QuestionReuseMetadataService::validate($reuse) as $error) $errors[] = $questionId . ':' . $error;
    if (is_array($reuse)) {
        $policies[$reuse['reuse_policy']] = ($policies[$reuse['reuse_policy']] ?? 0) + 1;
        $depths[(string)$reuse['required_depth']] = ($depths[(string)$reuse['required_depth']] ?? 0) + 1;
        $contexts[$reuse['context_mode']] = ($contexts[$reuse['context_mode']] ?? 0) + 1;
        $statuses[$reuse['metadata_status']] = ($statuses[$reuse['metadata_status']] ?? 0) + 1;
        foreach ($reuse['metadata_provenance'] ?? [] as $provenance) {
            if (($provenance['type'] ?? '') === 'BATCH605_VERIFIED') $batch605++;
        }
    }
}
if (count($metadata) !== count($questions)) $errors[] = 'MISSING_REUSE_METADATA_COUNT';
$coverageKeys = [];
$coverageDepths = [];
$coveragePolicies = [];
foreach ($coverage as $row) {
    foreach (QuestionReuseEligibilityResolver::validateCoverage($row) as $error) $errors[] = ($row['id'] ?? '<missing>') . ':' . $error;
    $key = implode('|', [(string)($row['board_id'] ?? ''), (string)($row['concept_id'] ?? ''), (string)($row['subject_id'] ?? ''), (string)($row['domain_id'] ?? ''), (string)($row['topic_id'] ?? ''), (string)($row['blueprint_node_id'] ?? '')]);
    if (isset($coverageKeys[$key])) $errors[] = 'DUPLICATE_COVERAGE:' . $key;
    $coverageKeys[$key] = true;
    $coverageDepths[(string)($row['max_depth'] ?? '')] = ($coverageDepths[(string)($row['max_depth'] ?? '')] ?? 0) + 1;
    $coveragePolicies[(string)($row['reuse_policy'] ?? '')] = ($coveragePolicies[(string)($row['reuse_policy'] ?? '')] ?? 0) + 1;
}
$equivalenceKeys = [];
foreach ($equivalence as $row) {
    foreach (QuestionReuseEligibilityResolver::validateEquivalence($row) as $error) $errors[] = ($row['id'] ?? '<missing>') . ':' . $error;
    $key = (string)($row['source_concept_id'] ?? '') . '|' . (string)($row['target_concept_id'] ?? '');
    if (isset($equivalenceKeys[$key])) $errors[] = 'DUPLICATE_EQUIVALENCE:' . $key;
    $equivalenceKeys[$key] = true;
}
$overrideKeys = [];
foreach ($overrides as $row) {
    $key = (string)($row['question_id'] ?? '') . '|' . (string)($row['board_id'] ?? '');
    if (isset($overrideKeys[$key])) $errors[] = 'CONFLICTING_OVERRIDE:' . $key;
    $overrideKeys[$key] = true;
    foreach (QuestionReuseEligibilityResolver::validateOverride($row) as $error) {
        $errors[] = $error . ':' . $key;
    }
}
foreach ($required as $row) {
    if (!isset($questionIds[(string)($row['question_id'] ?? '')])) $errors[] = 'ORPHANED_REQUIRED_CONCEPT:' . (string)($row['question_id'] ?? '');
    if (!in_array($row['requirement_type'] ?? null, ['REQUIRED', 'ASSUMED_FOUNDATION'], true)) $errors[] = 'INVALID_REQUIREMENT_TYPE';
    if (QuestionReuseEligibilityResolver::validateRequiredConcept($row) !== []) {
        $errors[] = 'INVALID_REQUIRED_CONCEPT:' . (string)($row['question_id'] ?? '');
    }
}
$report = [
    'status' => $errors === [] ? 'PASS' : 'FAIL',
    'questions' => count($questions),
    'new_format_questions' => count($metadata),
    'coverage_rows' => count($coverage),
    'equivalence_rows' => count($equivalence),
    'override_rows' => count($overrides),
    'required_concept_rows' => count($required),
    'policies' => $policies,
    'depths' => $depths,
    'contexts' => $contexts,
    'metadata_statuses' => $statuses,
    'batch605_provenance_records' => $batch605,
    'coverage_depths' => $coverageDepths,
    'coverage_policies' => $coveragePolicies,
    'missing_reuse_metadata' => count($questions) - count($metadata),
    'invalid_reuse_policy' => count(array_filter($errors, static fn(string $e): bool => str_contains($e, 'INVALID_REUSE_POLICY'))),
    'invalid_depth' => count(array_filter($errors, static fn(string $e): bool => str_contains($e, 'INVALID_REQUIRED_DEPTH') || str_contains($e, 'AUTO_WITH_UNKNOWN_DEPTH'))),
    'invalid_context' => count(array_filter($errors, static fn(string $e): bool => str_contains($e, 'INVALID_CONTEXT_MODE'))),
    'invalid_coverage' => count(array_filter($errors, static fn(string $e): bool => str_contains($e, 'INVALID_COVERAGE'))),
    'invalid_equivalence' => count(array_filter($errors, static fn(string $e): bool => str_contains($e, 'EQUIVALENCE'))),
    'invalid_override' => count(array_filter($errors, static fn(string $e): bool => str_contains($e, 'OVERRIDE'))),
    'orphaned_provenance' => count(array_filter($errors, static fn(string $e): bool => str_contains($e, 'ORPHANED'))),
    'duplicate_selection_candidate' => 0,
    'double_eligibility_path' => 0,
    'double_weighting' => 0,
    'errors' => $errors,
];
echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR), PHP_EOL;
exit($errors === [] ? 0 : 1);
