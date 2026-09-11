#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Recompute and classify the bounded same-global-concept reuse cohort.
 *
 * This tool is deliberately report-first.  It never changes question content
 * or legacy eligibility.  A reviewed decision file may supply deterministic
 * depth/context/coverage decisions for a small Batch 610 cohort.
 */

require_once dirname(__DIR__) . '/app/Core/Autoloader.php';
\App\Core\Autoloader::register();

use App\Core\App;
use App\Services\Question\QuestionReuseEligibilityResolver;
use App\Services\Question\QuestionReuseMetadataService;
use App\Services\Shared\TaxonomyStorageService;

$storage = App::storage();
$write = in_array('--write', $argv, true);
$questions = array_values(array_filter($storage->all('questions'), 'is_array'));
$eligibility = array_values(array_filter($storage->all('question-eligibility'), 'is_array'));
$coverage = array_values(array_filter($storage->all('board-concept-coverage'), 'is_array'));
$metadata = array_values(array_filter($storage->all('question-reuse-metadata'), 'is_array'));
$required = array_values(array_filter($storage->all('question-required-concepts'), 'is_array'));
$overrides = array_values(array_filter($storage->all('question-reuse-overrides'), 'is_array'));
$materials = array_values(array_filter($storage->all('study-materials'), 'is_array'));
$links = array_values(array_filter($storage->all('study-question-links'), 'is_array'));

$normalizeBoard = static function (mixed $value): string {
    $board = strtolower(trim(is_scalar($value) ? (string) $value : ''));
    return ['cse' => 'civil-service', 'civilservice' => 'civil-service'][$board] ?? $board;
};
$scalar = static fn(mixed $value): string => is_scalar($value) ? trim((string) $value) : '';

$directByQuestion = [];
foreach ($eligibility as $entry) {
    $entryExams = is_array($entry['exam_ids'] ?? null) ? $entry['exam_ids'] : [];
    foreach ($entry['question_ids'] ?? [] as $questionId) {
        if (!is_scalar($questionId)) {
            continue;
        }
        $id = (string) $questionId;
        $mapping = is_array($entry['question_mappings'][$id] ?? null) ? $entry['question_mappings'][$id] : [];
        $exams = is_array($mapping['exam_ids'] ?? null) ? $mapping['exam_ids'] : $entryExams;
        foreach ($exams as $exam) {
            $board = $normalizeBoard($exam);
            if ($board !== '') {
                $directByQuestion[$id][$board] = true;
            }
        }
    }
}

$questionById = [];
foreach ($questions as $question) {
    $questionById[(string) ($question['id'] ?? '')] = $question;
}
$metadataByQuestion = [];
foreach ($metadata as $record) {
    if (is_scalar($record['question_id'] ?? null) && is_array($record['reuse'] ?? null)) {
        $metadataByQuestion[(string) $record['question_id']] = $record['reuse'];
    }
}
$coverageByConceptBoard = [];
foreach ($coverage as $row) {
    if (QuestionReuseEligibilityResolver::validateCoverage($row) !== []) {
        continue;
    }
    $concept = $scalar($row['concept_id'] ?? null);
    $board = $normalizeBoard($row['board_id'] ?? null);
    if ($concept !== '' && $board !== '') {
        $coverageByConceptBoard[$concept][$board] = $row;
    }
}
$coverageById = [];
foreach ($coverage as $row) {
    if ($scalar($row['id'] ?? null) !== '') {
        $coverageById[(string) $row['id']] = $row;
    }
}
$requiredByQuestion = [];
foreach ($required as $row) {
    $id = $scalar($row['question_id'] ?? null);
    if ($id !== '') {
        $requiredByQuestion[$id][] = $row;
    }
}
$overridesByQuestionBoard = [];
foreach ($overrides as $row) {
    $questionId = $scalar($row['question_id'] ?? null);
    $board = $normalizeBoard($row['board_id'] ?? null);
    if ($questionId !== '' && $board !== '') {
        $overridesByQuestionBoard[$questionId][$board][] = $row;
    }
}
$materialById = [];
foreach ($materials as $material) {
    if ($scalar($material['id'] ?? null) !== '') {
        $materialById[(string) $material['id']] = $material;
    }
}
$linksByQuestion = [];
foreach ($links as $link) {
    $questionId = $scalar($link['question_id'] ?? null);
    if ($questionId !== '') {
        $linksByQuestion[$questionId][] = $link;
    }
}
$conceptNames = [];
foreach (TaxonomyStorageService::concepts() as $concept) {
    if (is_scalar($concept['id'] ?? null)) {
        $conceptNames[(string) $concept['id']] = $scalar($concept['name'] ?? $concept['id']);
    }
}
$boardNames = [];
foreach (TaxonomyStorageService::boards() as $board) {
    if (is_scalar($board['id'] ?? null)) {
        $boardNames[$normalizeBoard($board['id'])] = $scalar($board['name'] ?? $board['id']);
    }
}

$classification = static function (array $question, array $reuse, array $sourceCoverage, array $targetCoverage, array $requiredRows, array $targetOverrides, array $links): string {
    foreach ($targetOverrides as $override) {
        if (($override['override_type'] ?? '') === 'EXCLUDE') {
            return 'MANUAL_ONLY';
        }
    }
    if (($reuse['context_mode'] ?? 'UNKNOWN') === 'REGULATORY') {
        return 'REGULATORY_BLOCKED';
    }
    if (($reuse['context_mode'] ?? 'UNKNOWN') === 'PROFESSIONAL') {
        return 'CONTEXT_BLOCKED';
    }
    if (($reuse['reuse_policy'] ?? 'REVIEW') === 'MANUAL_ONLY') {
        return 'MANUAL_ONLY';
    }
    if ($requiredRows !== []) {
        return 'REQUIRED_CONCEPT_BLOCKED';
    }
    if ($links === []) {
        return 'SOURCE_SUPPORT_BLOCKED';
    }
    if (($targetCoverage['max_depth'] ?? 'UNKNOWN') === 'UNKNOWN'
        || ($reuse['required_depth'] ?? 'UNKNOWN') === 'UNKNOWN') {
        return 'DEPTH_BLOCKED';
    }
    if (($targetCoverage['status'] ?? '') !== 'VERIFIED'
        || ($targetCoverage['reuse_policy'] ?? '') !== 'AUTO') {
        return 'TARGET_BLUEPRINT_BLOCKED';
    }
    if (($targetCoverage['blueprint_node_id'] ?? '') === '') {
        return 'TARGET_BLUEPRINT_BLOCKED';
    }
    if (($reuse['reuse_policy'] ?? 'REVIEW') !== 'AUTO') {
        return 'SAFE_BUT_KEEP_REVIEW';
    }
    return 'SAFE_AUTO';
};

$candidateRecords = [];
foreach ($questions as $question) {
    $questionId = $scalar($question['id'] ?? null);
    if ($questionId === '') {
        continue;
    }
    $taxonomy = is_array($question['taxonomy'] ?? null) ? $question['taxonomy'] : [];
    $conceptId = $scalar($taxonomy['concept_id'] ?? null);
    if ($conceptId === '') {
        continue;
    }
    $sourceBoard = $normalizeBoard($taxonomy['board_id'] ?? $question['board'] ?? '');
    $sourceBoards = array_keys($directByQuestion[$questionId] ?? []);
    if ($sourceBoards === []) {
        $sourceBoards = $sourceBoard === '' ? [] : [$sourceBoard];
    }
    $reuse = $metadataByQuestion[$questionId] ?? QuestionReuseMetadataService::forAuthoring($question);
    foreach (array_keys($coverageByConceptBoard[$conceptId] ?? []) as $targetBoard) {
        if (in_array($targetBoard, $sourceBoards, true)) {
            continue;
        }
        $targetCoverage = $coverageByConceptBoard[$conceptId][$targetBoard];
        $sourceCoverage = $coverageByConceptBoard[$conceptId][$sourceBoard] ?? [];
        $targetOverrides = $overridesByQuestionBoard[$questionId][$targetBoard] ?? [];
        $studyReferences = [];
        foreach ($linksByQuestion[$questionId] ?? [] as $link) {
            $materialId = $scalar($link['material_id'] ?? null);
            $studyReferences[] = [
                'link_id' => $scalar($link['id'] ?? null),
                'material_id' => $materialId,
                'material_title' => $scalar($materialById[$materialId]['title'] ?? $materialById[$materialId]['name'] ?? ''),
                'source_ids' => array_values(array_map('strval', is_array($link['source_ids'] ?? null) ? $link['source_ids'] : [])),
            ];
        }
        $classificationValue = $classification(
            $question,
            $reuse,
            $sourceCoverage,
            $targetCoverage,
            $requiredByQuestion[$questionId] ?? [],
            $targetOverrides,
            $studyReferences
        );
        $candidateRecords[] = [
            'question_id' => $questionId,
            'source_board' => $sourceBoard,
            'target_board' => $targetBoard,
            'global_concept_id' => $conceptId,
            'concept_name' => $conceptNames[$conceptId] ?? $conceptId,
            'source_taxonomy_path' => [
                'subject_id' => $scalar($taxonomy['subject_id'] ?? null),
                'domain_id' => $scalar($taxonomy['domain_id'] ?? null),
                'topic_id' => $scalar($taxonomy['topic_id'] ?? null),
                'concept_id' => $conceptId,
            ],
            'target_taxonomy_path' => [
                'subject_id' => $scalar($targetCoverage['subject_id'] ?? null),
                'domain_id' => $scalar($targetCoverage['domain_id'] ?? null),
                'topic_id' => $scalar($targetCoverage['topic_id'] ?? null),
                'concept_id' => $conceptId,
            ],
            'current_reuse_policy' => $reuse['reuse_policy'] ?? 'REVIEW',
            'current_required_depth' => $reuse['required_depth'] ?? 'UNKNOWN',
            'source_coverage_depth' => $sourceCoverage['max_depth'] ?? 'UNKNOWN',
            'target_coverage_depth' => $targetCoverage['max_depth'] ?? 'UNKNOWN',
            'current_context_mode' => $reuse['context_mode'] ?? 'UNKNOWN',
            'existing_direct_source_mapping' => in_array($sourceBoard, $sourceBoards, true),
            'existing_direct_target_mapping' => array_key_exists($targetBoard, $directByQuestion[$questionId] ?? []),
            'study_resources' => $studyReferences,
            'blueprint_target_placement' => $targetCoverage['blueprint_node_id'] ?? '',
            'coverage_id' => $targetCoverage['id'] ?? '',
            'current_block_reason' => $classificationValue,
            'classification' => $classificationValue,
            'question_stem_hash' => hash('sha256', $scalar($question['question'] ?? $question['stem'] ?? '')),
            'question_stem' => $scalar($question['question'] ?? $question['stem'] ?? ''),
            'cognitive_intent' => $scalar($question['cognitive_intent'] ?? ''),
            'difficulty' => $scalar($question['difficulty'] ?? ''),
            'content_flags' => array_values(array_filter([
                !empty($question['numeric']) ? 'NUMERIC' : null,
                !empty($question['multistep']) ? 'MULTISTEP' : null,
                !empty($question['data_interpretation']) ? 'DATA_INTERPRETATION' : null,
                !empty($question['professional_judgment']) ? 'PROFESSIONAL_JUDGMENT' : null,
                !empty($question['context_necessary']) ? 'CONTEXT_NECESSARY' : null,
                !empty($question['source_sensitivity']) ? 'SOURCE_SENSITIVE' : null,
            ])),
        ];
    }
}

usort($candidateRecords, static function (array $left, array $right): int {
    $questionCompare = strnatcmp((string) $left['question_id'], (string) $right['question_id']);
    return $questionCompare !== 0 ? $questionCompare : strcmp((string) $left['target_board'], (string) $right['target_board']);
});

$counts = array_fill_keys([
    'SAFE_AUTO', 'SAFE_BUT_KEEP_REVIEW', 'DEPTH_BLOCKED', 'CONTEXT_BLOCKED',
    'REGULATORY_BLOCKED', 'TARGET_BLUEPRINT_BLOCKED', 'REQUIRED_CONCEPT_BLOCKED',
    'SOURCE_SUPPORT_BLOCKED', 'MANUAL_ONLY', 'INSUFFICIENT_EVIDENCE',
], 0);
foreach ($candidateRecords as $record) {
    $counts[$record['classification']] = ($counts[$record['classification']] ?? 0) + 1;
}
$report = [
    'id' => 'batch610-candidate-review-report',
    'batch' => 610,
    'scope' => 'same-global-concept-cross-board-candidates',
    'candidate_key' => 'question_id + target_board',
    'audit_hash' => hash('sha256', json_encode($candidateRecords, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)),
    'questions_analyzed' => count($questions),
    'same_concept_candidates' => count($candidateRecords),
    'classification_counts' => $counts,
    'classified_total' => array_sum($counts),
    'unclassified' => count($candidateRecords) - array_sum($counts),
    'candidates' => $candidateRecords,
];
$json = json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL;
if ($write) {
    $storage->replace('batch610-candidate-review', [$report]);
}
echo $json;
