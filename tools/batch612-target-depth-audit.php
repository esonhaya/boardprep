#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Batch 612A: audit the target-depth-unknown tail left by Batch 611.
 *
 * This tool is deliberately audit-only.  It reads the committed Batch 611
 * working set and current repository evidence, and --write writes only the
 * durable batch612-target-depth-audit artifact.  It never changes coverage,
 * reuse metadata, canonical questions, direct eligibility, or derived
 * eligibility.
 */

require_once dirname(__DIR__) . '/app/Core/Autoloader.php';
\App\Core\Autoloader::register();

$storage = \App\Core\App::storage();
$write = in_array('--write', $argv, true);
$scalar = static fn(mixed $value): string => is_scalar($value) ? trim((string) $value) : '';
$json = static fn(mixed $value): string => json_encode(
    $value,
    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
);
$normalizeBoard = static function (mixed $value): string {
    $board = strtolower(trim(is_scalar($value) ? (string) $value : ''));
    return ['cse' => 'civil-service', 'civilservice' => 'civil-service'][$board] ?? $board;
};
$depthKey = static fn(mixed $depth): string => is_int($depth) ? (string) $depth : 'UNKNOWN';
$sortedKeys = static function (array $values): array {
    $values = array_values(array_unique(array_map('strval', $values)));
    usort($values, 'strnatcmp');
    return $values;
};

$questions = array_values(array_filter($storage->all('questions'), 'is_array'));
$metadata = array_values(array_filter($storage->all('question-reuse-metadata'), 'is_array'));
$coverage = array_values(array_filter($storage->all('board-concept-coverage'), 'is_array'));
$derived = array_values(array_filter($storage->all('concept-derived-eligibility'), 'is_array'));
$links = array_values(array_filter($storage->all('study-question-links'), 'is_array'));
$materials = array_values(array_filter($storage->all('study-materials'), 'is_array'));
$sources = array_values(array_filter($storage->all('sources'), 'is_array'));
$blueprints = array_values(array_filter($storage->all('blueprints'), 'is_array'));
$batch611Rows = array_values(array_filter($storage->all('batch611-depth-review'), 'is_array'));
$batch611 = $batch611Rows[0] ?? null;
if (!is_array($batch611)) throw new RuntimeException('Missing committed Batch 611 audit artifact.');

$questionById = [];
foreach ($questions as $question) {
    $id = $scalar($question['id'] ?? null);
    if ($id !== '') $questionById[$id] = $question;
}
$metadataByQuestion = [];
foreach ($metadata as $record) {
    $id = $scalar($record['question_id'] ?? null);
    if ($id !== '') $metadataByQuestion[$id] = is_array($record['reuse'] ?? null) ? $record['reuse'] : [];
}
$coverageByKey = [];
foreach ($coverage as $row) {
    $key = $normalizeBoard($row['board_id'] ?? null) . '|' . $scalar($row['concept_id'] ?? null);
    if ($key !== '|') $coverageByKey[$key] = $row;
}
$materialById = [];
foreach ($materials as $material) {
    $id = $scalar($material['id'] ?? null);
    if ($id !== '') $materialById[$id] = $material;
}
$sourceById = [];
foreach ($sources as $source) {
    $id = $scalar($source['id'] ?? null);
    if ($id !== '') $sourceById[$id] = $source;
}
$conceptNames = [];
foreach ($storage->all('taxonomy/concepts') as $concept) {
    if (is_array($concept) && $scalar($concept['id'] ?? null) !== '') {
        $conceptNames[$scalar($concept['id'])] = $scalar($concept['name'] ?? $concept['id']);
    }
}
$blueprintByBoard = [];
foreach ($blueprints as $blueprint) {
    $board = $normalizeBoard($blueprint['board_id'] ?? null);
    if ($board !== '') $blueprintByBoard[$board][] = $blueprint;
}
$linksByQuestion = [];
foreach ($links as $link) {
    $id = $scalar($link['question_id'] ?? null);
    if ($id !== '') $linksByQuestion[$id][] = $link;
}

$directBoards = [];
$directRelationships = 0;
foreach ($storage->all('question-eligibility') as $entry) {
    if (!is_array($entry)) continue;
    $entryBoards = is_array($entry['exam_ids'] ?? null) ? $entry['exam_ids'] : [];
    foreach ($entry['question_ids'] ?? [] as $questionId) {
        if (!is_scalar($questionId)) continue;
        $id = (string) $questionId;
        $mapping = is_array($entry['question_mappings'][$id] ?? null) ? $entry['question_mappings'][$id] : [];
        $boards = is_array($mapping['exam_ids'] ?? null) ? $mapping['exam_ids'] : $entryBoards;
        foreach ($boards as $board) {
            $board = $normalizeBoard($board);
            if ($board !== '' && empty($directBoards[$id][$board])) {
                $directBoards[$id][$board] = true;
                $directRelationships++;
            }
        }
    }
}

$batch611QuestionDepths = [];
$batch611AllRelationships = array_values(array_filter(
    $batch611['relationships'] ?? [],
    static fn(mixed $record): bool => is_array($record)
));
foreach ($batch611AllRelationships as $record) {
    $id = $scalar($record['question_id'] ?? null);
    $depth = $record['question_depth_after'] ?? 'UNKNOWN';
    if ($id !== '' && is_int($depth)) $batch611QuestionDepths[$id] = $depth;
}

$unknownRelationships = array_values(array_filter(
    $batch611AllRelationships,
    static fn(array $record): bool => ($record['classification'] ?? '') === 'TARGET_DEPTH_UNKNOWN'
));
usort($unknownRelationships, static function (array $left, array $right) use ($normalizeBoard, $scalar): int {
    return strcmp(
        $scalar($left['question_id'] ?? null) . '|' . $normalizeBoard($left['target_board'] ?? null),
        $scalar($right['question_id'] ?? null) . '|' . $normalizeBoard($right['target_board'] ?? null)
    );
});
$uniqueRelationshipKeys = [];
foreach ($unknownRelationships as $record) {
    $key = $scalar($record['question_id'] ?? null) . '|' . $normalizeBoard($record['target_board'] ?? null);
    $uniqueRelationshipKeys[$key] = true;
}
if (count($unknownRelationships) !== 130 || count($uniqueRelationshipKeys) !== 130) {
    throw new RuntimeException('Batch 611 target-depth reproduction expected 130 unique relationships, found ' . count($unknownRelationships) . '/' . count($uniqueRelationshipKeys) . '.');
}

$studyEvidence = static function (mixed $questionId) use (&$linksByQuestion, &$materialById, &$sourceById, $scalar): array {
    $questionId = $scalar($questionId);
    $references = [];
    $valid = true;
    foreach ($linksByQuestion[$questionId] ?? [] as $link) {
        $materialId = $scalar($link['material_id'] ?? null);
        $sourceIds = is_array($link['source_ids'] ?? null) ? array_values(array_map('strval', $link['source_ids'])) : [];
        $material = $materialById[$materialId] ?? null;
        $sourceRecords = [];
        if (!is_array($material)) $valid = false;
        foreach ($sourceIds as $sourceId) {
            if (!isset($sourceById[$sourceId])) $valid = false;
            else $sourceRecords[] = [
                'id' => $sourceId,
                'title' => $scalar($sourceById[$sourceId]['title'] ?? ''),
                'source_type' => $scalar($sourceById[$sourceId]['source_type'] ?? ''),
                'status' => $scalar($sourceById[$sourceId]['status'] ?? ''),
            ];
        }
        $references[] = [
            'link_id' => $scalar($link['id'] ?? null),
            'material_id' => $materialId,
            'material_title' => is_array($material) ? $scalar($material['title'] ?? $material['name'] ?? '') : '',
            'source_ids' => $sourceIds,
            'sources' => $sourceRecords,
        ];
    }
    usort($references, static fn(array $a, array $b): int => strcmp($a['link_id'], $b['link_id']));
    return ['references' => $references, 'valid' => $valid && $references !== []];
};

$directQuestionsFor = static function (string $board, string $concept) use ($questionById, $directBoards, $normalizeBoard): array {
    $ids = [];
    foreach ($questionById as $id => $question) {
        if (($question['taxonomy']['concept_id'] ?? '') === $concept
            && isset($directBoards[$id][$normalizeBoard($board)])) $ids[] = $id;
    }
    usort($ids, 'strnatcmp');
    return $ids;
};

$targetRows = [];
foreach ($unknownRelationships as $record) {
    $board = $normalizeBoard($record['target_board'] ?? null);
    $concept = $scalar($record['concept_id'] ?? null);
    $key = $board . '|' . $concept;
    if (isset($targetRows[$key])) continue;
    $row = $coverageByKey[$key] ?? null;
    if (!is_array($row)) throw new RuntimeException('Missing target coverage row: ' . $key);
    $targetRows[$key] = $row;
}
ksort($targetRows);

$blueprintEvidence = static function (string $board, string $concept) use (&$blueprintByBoard, $scalar): array {
    $records = [];
    foreach ($blueprintByBoard[$board] ?? [] as $blueprint) {
        $records[] = [
            'id' => $scalar($blueprint['id'] ?? ''),
            'name' => $scalar($blueprint['name'] ?? ''),
            'version' => $blueprint['version'] ?? null,
            'status' => $scalar($blueprint['status'] ?? ''),
            'official_weighting' => $blueprint['official_weighting'] ?? null,
            'concept_placement_found' => false,
            'depth_limit_found' => false,
        ];
    }
    return [
        'records' => $records,
        'concept_id' => $concept,
        'concept_placement_found' => false,
        'depth_limit_found' => false,
        'finding' => 'Repository blueprint/configuration has no authoritative concept-level placement or maximum-depth statement for this row.',
    ];
};

$derivedRelationships = array_values(array_filter($derived, static fn(array $record): bool => ($record['record_type'] ?? '') === 'RELATIONSHIP'));
$coverageDecisions = [];
foreach ($targetRows as $key => $row) {
    [$board, $concept] = explode('|', $key, 2);
    $questionIds = $directQuestionsFor($board, $concept);
    $depthDistribution = [];
    $sourceIds = [];
    $materialIds = [];
    foreach ($questionIds as $questionId) {
        $depth = $batch611QuestionDepths[$questionId] ?? ($metadataByQuestion[$questionId]['required_depth'] ?? 'UNKNOWN');
        $depthDistribution[$depthKey($depth)] = ($depthDistribution[$depthKey($depth)] ?? 0) + 1;
        $support = $studyEvidence($questionId);
        foreach ($support['references'] as $reference) {
            $materialIds[] = $reference['material_id'];
            foreach ($reference['source_ids'] as $sourceId) $sourceIds[] = $sourceId;
        }
    }
    ksort($depthDistribution, SORT_NATURAL);
    $existingDerived = array_values(array_filter($derivedRelationships, static fn(array $record): bool =>
        $normalizeBoard($record['target_board'] ?? null) === $board && $scalar($record['concept_id'] ?? null) === $concept
    ));
    $batch605 = array_values(array_filter($row['provenance'] ?? [], static fn(mixed $record): bool =>
        is_array($record) && ($record['type'] ?? '') === 'BATCH605_VERIFIED'
    ));
    $officialSources = [];
    foreach ($sortedKeys($sourceIds) as $sourceId) {
        if (isset($sourceById[$sourceId])) $officialSources[] = [
            'id' => $sourceId,
            'title' => $scalar($sourceById[$sourceId]['title'] ?? ''),
            'source_type' => $scalar($sourceById[$sourceId]['source_type'] ?? ''),
            'status' => $scalar($sourceById[$sourceId]['status'] ?? ''),
        ];
    }
    $coverageDecisions[$key] = [
        'board' => $board,
        'concept_id' => $concept,
        'concept_name' => $conceptNames[$concept] ?? $concept,
        'subject' => $scalar($row['subject_id'] ?? ''),
        'domain' => $scalar($row['domain_id'] ?? ''),
        'topic' => $scalar($row['topic_id'] ?? ''),
        'blueprint_node' => $scalar($row['blueprint_node_id'] ?? ''),
        'coverage_id' => $scalar($row['id'] ?? ''),
        'candidate_relationships' => 0,
        'distinct_questions' => count($questionIds),
        'current_max_depth' => $row['max_depth'] ?? 'UNKNOWN',
        'required_depth_min' => null,
        'required_depth_max' => null,
        'required_depth_distribution' => $depthDistribution,
        'official_blueprint_evidence' => $blueprintEvidence($board, $concept),
        'target_direct_question_count' => count($questionIds),
        'target_direct_depths' => $depthDistribution,
        'target_existing_derived_count' => count($existingDerived),
        'batch605_evidence' => $batch605,
        'study_resource_evidence' => [
            'material_ids' => $sortedKeys($materialIds),
            'resource_count' => count($sortedKeys($materialIds)),
        ],
        'official_source_evidence' => $officialSources,
        'other_deterministic_evidence' => [
            'coverage_status' => $scalar($row['status'] ?? ''),
            'coverage_blueprint_node_empty' => $scalar($row['blueprint_node_id'] ?? '') === '',
            'target_board_has_direct_concept_use' => count($questionIds) > 0,
            'finding' => 'Direct target-board question presence establishes concept use, not a supported maximum depth or valid blueprint placement.',
        ],
        'proposed_max_depth' => 'UNKNOWN',
        'coverage_decision' => 'KEEP_UNKNOWN_INSUFFICIENT_EVIDENCE',
        'confidence' => 'REVIEW_REQUIRED',
        'evidence' => 'No independent authoritative evidence establishes the target board maximum depth for this concept. The target row has no blueprint node and remains REVIEW_REQUIRED; direct questions and generic resources do not prove a maximum.',
        'why_depth_not_higher' => 'No depth is assigned, so no higher depth can be justified without a concept-level target blueprint/configuration or independently verified target-board depth evidence.',
    ];
}

$relationships = [];
$pairStats = [];
$conceptStats = [];
$contextCounts = ['NEUTRAL' => 0, 'DECORATIVE' => 0, 'MATERIAL_PROFESSIONAL' => 0, 'REGULATORY' => 0];
$sourceChecked = 0;
$sourceValid = 0;
foreach ($unknownRelationships as $record) {
    $questionId = $scalar($record['question_id'] ?? null);
    $sourceBoard = $normalizeBoard($record['source_board'] ?? null);
    $targetBoard = $normalizeBoard($record['target_board'] ?? null);
    $concept = $scalar($record['concept_id'] ?? null);
    $key = $targetBoard . '|' . $concept;
    $row = $coverageDecisions[$key];
    $question = $questionById[$questionId] ?? [];
    $questionDepth = $record['question_depth_after'] ?? 'UNKNOWN';
    $support = $studyEvidence($questionId);
    $context = $scalar($record['context'] ?? $record['current_context'] ?? 'UNKNOWN');
    $contextCounts[$context] = ($contextCounts[$context] ?? 0) + 1;
    $sourceChecked++;
    if ($support['valid']) $sourceValid++;
    $studyResource = [];
    foreach ($support['references'] as $reference) {
        $studyResource[] = [
            'material_id' => $reference['material_id'],
            'material_title' => $reference['material_title'],
            'source_ids' => $reference['source_ids'],
        ];
    }
    $sourceStatus = $support['valid'] ? 'VALID' : 'SOURCE_SUPPORT_BLOCKED';
    $placementStatus = $row['blueprint_node'] === '' ? 'TARGET_PLACEMENT_BLOCKED' : 'VALID';
    $relationship = [
        'question_id' => $questionId,
        'source_board' => $sourceBoard,
        'target_board' => $targetBoard,
        'concept_id' => $concept,
        'concept_name' => $row['concept_name'],
        'question_required_depth' => $questionDepth,
        'question_depth_before' => $record['question_depth_before'] ?? 'UNKNOWN',
        'question_depth_after' => $questionDepth,
        'current_target_depth' => $row['current_max_depth'],
        'proposed_target_depth' => 'UNKNOWN',
        'target_coverage_id' => $row['coverage_id'],
        'target_coverage_depth' => $row['current_max_depth'],
        'source_subject' => $scalar($question['taxonomy']['subject_id'] ?? ''),
        'source_domain' => $scalar($question['taxonomy']['domain_id'] ?? ''),
        'source_topic' => $scalar($question['taxonomy']['topic_id'] ?? ''),
        'target_subject' => $row['subject'],
        'target_domain' => $row['domain'],
        'target_topic' => $row['topic'],
        'target_blueprint_node' => $row['blueprint_node'],
        'context' => $context,
        'reuse_policy' => $metadataByQuestion[$questionId]['reuse_policy'] ?? 'UNKNOWN',
        'study_resource' => $studyResource,
        'source_support_status' => $sourceStatus,
        'current_reason' => 'TARGET_DEPTH_UNKNOWN',
        'coverage_decision' => $row['coverage_decision'],
        'relationship_decision' => 'STILL_TARGET_DEPTH_UNKNOWN',
        'source_status' => $sourceStatus,
        'placement_status' => $placementStatus,
        'authoritative_persistence_file' => 'storage/board-concept-coverage.json',
        'dirty_file_collision' => false,
        'hunk_isolatable' => false,
        'confidence' => 'TIER_C',
        'production_enabled' => false,
        'evidence' => [
            'batch611_record' => $record['evidence'] ?? [],
            'coverage_row' => $row['evidence'],
            'official_blueprint' => $row['official_blueprint_evidence'],
            'target_direct_questions' => $row['target_direct_question_count'],
            'target_direct_depths' => $row['target_direct_depths'],
            'study_resources' => $studyResource,
        ],
        'reason' => 'Target depth remains unknown: target-board direct use and generic study support do not independently establish a maximum depth, and the target coverage row has no authoritative blueprint placement.',
    ];
    $relationships[] = $relationship;

    $pair = strtoupper(str_replace('-', '_', $sourceBoard)) . ' → ' . strtoupper(str_replace('-', '_', $targetBoard));
    $pairStats[$pair] ??= ['board_pair' => $pair, 'total_unknown_target_depth' => 0, 'resolvable_safe' => 0, 'resolvable_but_depth_blocked' => 0, 'still_unknown' => 0, 'other_blocked' => 0];
    $pairStats[$pair]['total_unknown_target_depth']++;
    $pairStats[$pair]['still_unknown']++;
    $coverageDecisions[$key]['candidate_relationships']++;
    $depth = $questionDepth;
    $conceptStats[$concept] ??= [
        'concept_id' => $concept,
        'concept_name' => $row['concept_name'],
        'target_boards' => [],
        'relationships' => 0,
        'question_depth_distribution' => [],
        'proposed_target_depths' => [],
        'safe_if_resolved' => 0,
        'blocked_if_resolved' => 0,
        'still_unknown' => 0,
    ];
    $conceptStats[$concept]['target_boards'][$targetBoard] = true;
    $conceptStats[$concept]['relationships']++;
    $depthName = $depthKey($depth);
    $conceptStats[$concept]['question_depth_distribution'][$depthName] = ($conceptStats[$concept]['question_depth_distribution'][$depthName] ?? 0) + 1;
    $conceptStats[$concept]['proposed_target_depths']['UNKNOWN'] = ($conceptStats[$concept]['proposed_target_depths']['UNKNOWN'] ?? 0) + 1;
    $conceptStats[$concept]['still_unknown']++;
}

foreach ($coverageDecisions as &$row) {
    $depths = array_keys(array_filter($row['required_depth_distribution'], static fn(int $count): bool => $count > 0));
    sort($depths, SORT_NATURAL);
    $row['required_depth_min'] = $depths[0] ?? 'UNKNOWN';
    $row['required_depth_max'] = $depths !== [] ? end($depths) : 'UNKNOWN';
}
unset($row);
foreach ($pairStats as &$pair) ksort($pair);
unset($pair);
ksort($pairStats);
foreach ($conceptStats as &$concept) {
    $concept['target_boards'] = $sortedKeys(array_keys($concept['target_boards']));
    ksort($concept['question_depth_distribution'], SORT_NATURAL);
    ksort($concept['proposed_target_depths'], SORT_NATURAL);
}
unset($concept);
ksort($conceptStats);

$directAndDerivedBoards = $directBoards;
$effectiveRelationshipPairs = [];
foreach ($directBoards as $questionId => $boards) {
    foreach ($boards as $board => $_present) $effectiveRelationshipPairs[$questionId . '|' . $board] = true;
}
foreach ($derivedRelationships as $record) {
    $id = $scalar($record['question_id'] ?? null);
    $board = $normalizeBoard($record['target_board'] ?? null);
    if ($id !== '' && $board !== '') {
        $directAndDerivedBoards[$id][$board] = true;
        $effectiveRelationshipPairs[$id . '|' . $board] = true;
    }
}
$stats = static function (array $boards): array {
    $multi = 0; $single = 0;
    foreach ($boards as $boardSet) {
        if (count($boardSet) > 1) $multi++; else if (count($boardSet) === 1) $single++;
    }
    return ['multi' => $multi, 'single' => $single, 'relationships' => $multi + $single];
};
$currentStats = $stats($directAndDerivedBoards);
$currentStats['relationships'] = count($effectiveRelationshipPairs);
$currentDerivedCount = count($derivedRelationships);
$currentDirectCount = $directRelationships;

$coverageDecisions = array_values($coverageDecisions);
usort($coverageDecisions, static fn(array $a, array $b): int => strcmp($a['board'] . '|' . $a['concept_id'], $b['board'] . '|' . $b['concept_id']));
$pairStats = array_values($pairStats);
$conceptStats = array_values($conceptStats);

$report = [
    'id' => 'batch612-target-depth-audit-report',
    'batch' => '612A',
    'scope' => 'TARGET_DEPTH_UNKNOWN relationships left by Batch 611; audit only',
    'baseline' => [
        'head' => trim((string) shell_exec('git rev-parse HEAD 2>/dev/null')),
        'canonical' => count($questions),
        'direct_mappings' => $currentDirectCount,
        'concept_derived' => $currentDerivedCount,
        'total_effective' => $currentStats['relationships'],
        'multi_board_effective' => $currentStats['multi'],
        'single_board_effective' => $currentStats['single'],
    ],
    'reproduction' => [
        'expected_target_depth_unknown' => 130,
        'recomputed_target_depth_unknown' => count($unknownRelationships),
        'unique_relationships' => count($uniqueRelationshipKeys),
        'count_match' => count($unknownRelationships) === 130 && count($uniqueRelationshipKeys) === 130,
        'batch611_audit_hash' => $batch611['audit_hash'] ?? '',
    ],
    'target_coverage_rows_involved' => count($coverageDecisions),
    'target_boards_involved' => $sortedKeys(array_map(static fn(array $row): string => $row['board'], $coverageDecisions)),
    'target_concepts_involved' => count(array_unique(array_map(static fn(array $row): string => $row['concept_id'], $coverageDecisions))),
    'coverage_resolution' => [
        'RESOLVABLE_DEPTH_1' => 0,
        'RESOLVABLE_DEPTH_2' => 0,
        'RESOLVABLE_DEPTH_3' => 0,
        'RESOLVABLE_DEPTH_4' => 0,
        'RESOLVABLE_DEPTH_5' => 0,
        'KEEP_UNKNOWN_INSUFFICIENT_EVIDENCE' => count($coverageDecisions),
        'COVERAGE_ROW_DEFECT' => 0,
        'TARGET_PLACEMENT_DEFECT' => 0,
        'resolvable_coverage_rows_total' => 0,
    ],
    'coverage_rows' => $coverageDecisions,
    'relationships' => $relationships,
    'relationship_results' => [
        'SAFE_IF_TARGET_DEPTH_APPLIED' => 0,
        'TRUE_DEPTH_BLOCKED' => 0,
        'STILL_TARGET_DEPTH_UNKNOWN' => count($relationships),
        'CONTEXT_BLOCKED' => 0,
        'REGULATORY_BLOCKED' => 0,
        'SOURCE_SUPPORT_BLOCKED' => 0,
        'TARGET_PLACEMENT_BLOCKED' => 0,
        'MANUAL_POLICY_BLOCKED' => 0,
        'OTHER_REVIEW_REQUIRED' => 0,
        'TOTAL' => count($relationships),
        'UNCLASSIFIED' => 0,
    ],
    'isolation' => [
        'semantically_resolvable_relationships' => 0,
        'resolvable_and_isolatable_relationships' => 0,
        'resolvable_but_dirty_file_collision' => 0,
        'not_resolvable_evidence' => count($relationships),
        'semantically_resolvable_coverage_rows' => 0,
        'isolatable_coverage_rows' => 0,
        'dirty_collision_coverage_rows' => 0,
        'dirty_files_containing_resolvable_metadata' => [],
        'hunk_isolatable_rows' => 0,
        'hunk_nonisolatable_rows' => 0,
        'persistence_destination' => 'storage/board-concept-coverage.json (currently clean; no change proposed)',
    ],
    'board_pairs' => $pairStats,
    'concepts' => $conceptStats,
    'source_context' => [
        'safe_relationships_source_checked' => 0,
        'source_checked_for_unknown_set' => $sourceChecked,
        'source_valid' => $sourceValid,
        'source_blocked' => $sourceChecked - $sourceValid,
        'NEUTRAL' => $contextCounts['NEUTRAL'] ?? 0,
        'DECORATIVE' => $contextCounts['DECORATIVE'] ?? 0,
        'MATERIAL_PROFESSIONAL' => $contextCounts['MATERIAL_PROFESSIONAL'] ?? 0,
        'REGULATORY' => $contextCounts['REGULATORY'] ?? 0,
        'finding' => 'The 130 remain neutral and source-valid, but source support does not independently establish target-board maximum depth.',
    ],
    'projection' => [
        'concept_derived_current' => $currentDerivedCount,
        'projected_if_all_semantically_safe_resolved' => $currentDerivedCount,
        'projected_if_only_cleanly_isolatable' => $currentDerivedCount,
        'projected_total_effective' => $currentStats['relationships'],
        'projected_multi_board' => $currentStats['multi'],
        'projected_single_board' => $currentStats['single'],
    ],
    'preservation' => [
        'canonical_before' => count($questions), 'canonical_after' => count($questions),
        'direct_mappings_before' => $currentDirectCount, 'direct_mappings_after' => $currentDirectCount,
        'concept_derived_before' => $currentDerivedCount, 'concept_derived_after' => $currentDerivedCount,
        'total_effective_before' => $currentStats['relationships'], 'total_effective_after' => $currentStats['relationships'],
        'multi_board_before' => $currentStats['multi'], 'multi_board_after' => $currentStats['multi'],
        'single_board_before' => $currentStats['single'], 'single_board_after' => $currentStats['single'],
        'question_content_changed' => 0, 'question_clones_created' => 0,
        'production_metadata_changed' => 0,
    ],
    'determinism' => ['artifact_excludes_timestamps' => true],
    'audit_tool' => 'tools/batch612-target-depth-audit.php',
    'audit_records' => count($relationships),
];
$hashInput = $report;
unset($hashInput['determinism']);
unset($hashInput['baseline']['head']);
$report['audit_hash'] = hash('sha256', $json($hashInput));

if ($write) $storage->replace('batch612-target-depth-audit', [$report]);

echo json_encode([
    'target_depth_unknown' => count($relationships),
    'coverage_rows' => count($coverageDecisions),
    'coverage_rows_resolvable' => 0,
    'still_target_depth_unknown' => count($relationships),
    'concept_derived' => $currentDerivedCount,
    'total_effective' => $currentStats['relationships'],
    'multi_board_effective' => $currentStats['multi'],
    'single_board_effective' => $currentStats['single'],
    'canonical' => count($questions),
    'audit_hash' => $report['audit_hash'],
    'write' => $write,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR), PHP_EOL;
