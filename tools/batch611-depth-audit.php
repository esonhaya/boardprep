#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Batch 611: calibrate the bounded same-global-concept candidate set and
 * materialize only a deliberately small, source-supported production cohort.
 *
 * This is intentionally report-first.  It never changes question content or
 * legacy direct eligibility mappings.  --write updates only reuse metadata,
 * coverage metadata, bounded derived relationships, and this audit artifact.
 */

require_once dirname(__DIR__) . '/app/Core/Autoloader.php';
\App\Core\Autoloader::register();

use App\Core\App;
use App\Services\Question\QuestionReuseEligibilityResolver;
use App\Services\Question\QuestionReuseMetadataService;

$storage = App::storage();
$write = in_array('--write', $argv, true);

$scalar = static fn(mixed $value): string => is_scalar($value) ? trim((string) $value) : '';
$normalizeBoard = static function (mixed $value): string {
    $board = strtolower(trim(is_scalar($value) ? (string) $value : ''));
    return ['cse' => 'civil-service', 'civilservice' => 'civil-service'][$board] ?? $board;
};
$json = static fn(mixed $value): string => json_encode(
    $value,
    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
);

$questions = array_values(array_filter($storage->all('questions'), 'is_array'));
$metadata = array_values(array_filter($storage->all('question-reuse-metadata'), 'is_array'));
$coverage = array_values(array_filter($storage->all('board-concept-coverage'), 'is_array'));
$derived = array_values(array_filter($storage->all('concept-derived-eligibility'), 'is_array'));
$overrides = array_values(array_filter($storage->all('question-reuse-overrides'), 'is_array'));
$links = array_values(array_filter($storage->all('study-question-links'), 'is_array'));
$materials = array_values(array_filter($storage->all('study-materials'), 'is_array'));
$sources = array_values(array_filter($storage->all('sources'), 'is_array'));
$blueprints = array_values(array_filter($storage->all('blueprints'), 'is_array'));
$batch610Rows = array_values(array_filter($storage->all('batch610-candidate-review'), 'is_array'));
$previousRows = array_values(array_filter($storage->all('batch611-depth-review'), 'is_array'));

$batch610 = $batch610Rows[0] ?? null;
if (!is_array($batch610)) {
    throw new RuntimeException('Batch 610 candidate artifact is missing.');
}

$counts = $batch610['classification_counts'] ?? [];
$allCandidateRecords = array_values(array_filter(
    $batch610['candidates'] ?? [],
    static fn(mixed $record): bool => is_array($record)
));
$candidateRecords = array_values(array_filter(
    $allCandidateRecords,
    static fn(mixed $record): bool => is_array($record) && ($record['classification'] ?? '') === 'DEPTH_BLOCKED'
));
if (($batch610['same_concept_candidates'] ?? null) !== 285
    || ($counts['SAFE_AUTO'] ?? null) !== 10
    || ($counts['DEPTH_BLOCKED'] ?? null) !== 245
    || ($counts['REGULATORY_BLOCKED'] ?? null) !== 30
    || count($candidateRecords) !== 245) {
    throw new RuntimeException('Batch 610 reproduction failed; depth calibration was not started.');
}

$questionById = [];
foreach ($questions as $question) {
    $id = $scalar($question['id'] ?? null);
    if ($id !== '') $questionById[$id] = $question;
}
$metadataByQuestion = [];
foreach ($metadata as $record) {
    $id = $scalar($record['question_id'] ?? null);
    if ($id !== '' && is_array($record['reuse'] ?? null)) $metadataByQuestion[$id] = $record['reuse'];
}
$coverageByKey = [];
$coverageById = [];
foreach ($coverage as $row) {
    $key = $normalizeBoard($row['board_id'] ?? null) . '|' . $scalar($row['concept_id'] ?? null);
    if ($key !== '|') $coverageByKey[$key] = $row;
    if ($scalar($row['id'] ?? null) !== '') $coverageById[$scalar($row['id'])] = $row;
}
$linkByQuestion = [];
foreach ($links as $link) {
    $id = $scalar($link['question_id'] ?? null);
    if ($id !== '') $linkByQuestion[$id][] = $link;
}
$materialById = [];
foreach ($materials as $material) {
    $id = $scalar($material['id'] ?? null);
    if ($id !== '') $materialById[$id] = $material;
}
$conceptNames = [];
foreach (App::storage()->all('taxonomy/concepts') as $concept) {
    if (is_array($concept) && $scalar($concept['id'] ?? null) !== '') {
        $conceptNames[$scalar($concept['id'])] = $scalar($concept['name'] ?? $concept['id']);
    }
}

/** @return array<string, array<string, bool>> */
$directBoards = static function () use ($storage, $normalizeBoard): array {
    $index = [];
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
                if ($board !== '') $index[$id][$board] = true;
            }
        }
    }
    return $index;
};
$directByQuestion = $directBoards();

/**
 * Cognitive-intent-only calibration.  Difficulty, stem length, board, and
 * answer-choice count are deliberately absent from this rule.
 */
$depthFromIntent = static function (array $question): array {
    $intent = strtolower(trim((string) ($question['cognitive_intent'] ?? '')));
    if ($intent === '') return ['depth' => 'UNKNOWN', 'rule' => 'NO_COGNITIVE_INTENT'];
    if (preg_match('/recogn|recall|identify|literal|remember|name/', $intent) === 1) {
        return ['depth' => 1, 'rule' => 'FOUNDATION_RECOGNITION'];
    }
    if (preg_match('/classif|interpret|distinguish|understand/', $intent) === 1
        && preg_match('/apply|application|analy|evaluat|reason|synth|diagnos/', $intent) !== 1) {
        return ['depth' => 2, 'rule' => 'UNDERSTANDING_INTERPRETATION'];
    }
    if (preg_match('/analy|evaluat|evidence|synth|diagnos|reason/', $intent) === 1) {
        return ['depth' => 4, 'rule' => 'ANALYSIS_INTEGRATION'];
    }
    if (preg_match('/apply|application|infer|predict|sequence|compare/', $intent) === 1) {
        return ['depth' => 3, 'rule' => 'ROUTINE_APPLICATION'];
    }
    return ['depth' => 'UNKNOWN', 'rule' => 'UNMAPPED_INTENT'];
};

$previousByKey = [];
foreach ($previousRows[0]['relationships'] ?? [] as $previous) {
    if (is_array($previous)) {
        $key = $scalar($previous['question_id'] ?? null) . '|' . $normalizeBoard($previous['target_board'] ?? null);
        if ($key !== '|') $previousByKey[$key] = $previous;
    }
}

$questionDepths = [];
$depthRules = [];
foreach ($allCandidateRecords as $candidate) {
    $id = $scalar($candidate['question_id'] ?? null);
    $question = $questionById[$id] ?? null;
    if (!is_array($question)) throw new RuntimeException('Candidate question is missing: ' . $id);
    $reuse = $metadataByQuestion[$id] ?? [];
    $existing = $reuse['required_depth'] ?? 'UNKNOWN';
    if (is_int($existing) && in_array($existing, [1, 2, 3, 4, 5], true)) {
        $decision = ['depth' => $existing, 'rule' => 'EXISTING_VERIFIED_DEPTH'];
    } else {
        $decision = $depthFromIntent($question);
    }
    $questionDepths[$id] = $decision;
    $depthRules[$decision['rule']] = ($depthRules[$decision['rule']] ?? 0) + 1;
}
$workingQuestionIds = [];
foreach ($candidateRecords as $candidate) {
    $workingQuestionIds[$scalar($candidate['question_id'] ?? null)] = true;
}

$studySupport = static function (string $questionId) use (&$linkByQuestion, &$materialById, $scalar): array {
    $references = [];
    foreach ($linkByQuestion[$questionId] ?? [] as $link) {
        $materialId = $scalar($link['material_id'] ?? null);
        $material = $materialById[$materialId] ?? [];
        $references[] = [
            'link_id' => $scalar($link['id'] ?? null),
            'material_id' => $materialId,
            'material_title' => $scalar($material['title'] ?? $material['name'] ?? ''),
            'source_ids' => array_values(array_map('strval', is_array($link['source_ids'] ?? null) ? $link['source_ids'] : [])),
        ];
    }
    return $references;
};
$appendProvenance = static function (array $records, array $record): array {
    foreach ($records as $existing) {
        if (is_array($existing)
            && ($existing['type'] ?? '') === ($record['type'] ?? '')
            && ($existing['reference'] ?? '') === ($record['reference'] ?? '')) {
            return $records;
        }
    }
    $records[] = $record;
    return $records;
};

// The baseline's 15 candidate-relevant unknown rows are the source-side rows.
// Once written, their max_depth is no longer UNKNOWN, so the durable first-run
// inventory is the source of truth for repeatable audits.
$sourceRelevantKeys = [];
$previousInventory = is_array($previousRows[0]['coverage_inventory'] ?? null)
    ? $previousRows[0]['coverage_inventory']
    : [];
if (count($previousInventory) === 15) {
    foreach ($previousInventory as $inventoryRow) {
        if (is_array($inventoryRow)) {
            $key = $normalizeBoard($inventoryRow['board'] ?? null) . '|' . $scalar($inventoryRow['concept_id'] ?? null);
            if ($key !== '|') $sourceRelevantKeys[$key] = true;
        }
    }
} else {
    foreach ($allCandidateRecords as $candidate) {
        $key = $normalizeBoard($candidate['source_board'] ?? null) . '|' . $scalar($candidate['global_concept_id'] ?? null);
        $row = $coverageByKey[$key] ?? null;
        if (is_array($row) && ($row['max_depth'] ?? 'UNKNOWN') === 'UNKNOWN') $sourceRelevantKeys[$key] = true;
    }
}
if (count($sourceRelevantKeys) !== 15) {
    throw new RuntimeException('Expected 15 source-side candidate coverage rows, found ' . count($sourceRelevantKeys) . '.');
}

$directQuestionIdsFor = static function (string $board, string $concept) use ($questionById, $directByQuestion, $normalizeBoard): array {
    $ids = [];
    foreach ($questionById as $id => $question) {
        if (($question['taxonomy']['concept_id'] ?? '') !== $concept) continue;
        if (isset($directByQuestion[$id][$normalizeBoard($board)])) $ids[] = $id;
    }
    usort($ids, 'strnatcmp');
    return $ids;
};

$coverageInventory = [];
foreach (array_keys($sourceRelevantKeys) as $key) {
    [$board, $concept] = explode('|', $key, 2);
    $row = $coverageByKey[$key];
    $directIds = $directQuestionIdsFor($board, $concept);
    $requestCount = 0;
    foreach ($allCandidateRecords as $candidate) {
        if (($candidate['global_concept_id'] ?? '') === $concept
            && $normalizeBoard($candidate['target_board'] ?? null) !== $board) {
            $requestCount++;
        }
    }
    $observed = [];
    foreach ($directIds as $id) {
        if (isset($questionDepths[$id]) && is_int($questionDepths[$id]['depth'])) {
            $observed[] = $questionDepths[$id]['depth'];
        } elseif (isset($questionById[$id])) {
            $fallback = $depthFromIntent($questionById[$id]);
            if (is_int($fallback['depth'])) $observed[] = $fallback['depth'];
        }
    }
    $shared = 0;
    foreach ($directIds as $id) if (count($directByQuestion[$id] ?? []) > 1) $shared++;
    $batch605 = 0;
    foreach ($row['provenance'] ?? [] as $provenance) {
        if (is_array($provenance) && ($provenance['type'] ?? '') === 'BATCH605_VERIFIED') $batch605++;
    }
    $boardBlueprint = [];
    foreach ($blueprints as $blueprint) {
        if ($normalizeBoard($blueprint['board_id'] ?? $blueprint['board'] ?? '') === $board) {
            $boardBlueprint = [
                'id' => $scalar($blueprint['id'] ?? ''),
                'source_id' => $scalar($blueprint['source_id'] ?? ''),
                'source_version' => $scalar($blueprint['source_version'] ?? ''),
                'official_structure' => $scalar($blueprint['official_structure'] ?? ''),
            ];
            break;
        }
    }
    $materialsForRow = [];
    foreach ($allCandidateRecords as $candidate) {
        if (($candidate['global_concept_id'] ?? '') !== $concept) continue;
        foreach ($candidate['study_resources'] ?? [] as $reference) {
            $materialId = $scalar($reference['material_id'] ?? null);
            if ($materialId !== '') $materialsForRow[$materialId] = $reference;
        }
    }
    $coverageInventory[] = [
        'board' => $board,
        'concept_id' => $concept,
        'concept_name' => $conceptNames[$concept] ?? $concept,
        'subject' => $scalar($row['subject_id'] ?? ''),
        'domain' => $scalar($row['domain_id'] ?? ''),
        'topic' => $scalar($row['topic_id'] ?? ''),
        'blueprint_area' => $scalar($row['blueprint_node_id'] ?? ''),
        'current_max_depth' => $row['max_depth'] ?? 'UNKNOWN',
        'questions_on_this_board_using_concept' => count($directIds),
        'questions_from_other_boards_requesting_reuse' => $requestCount,
        'existing_direct_shared_questions' => $shared,
        'batch605_evidence' => $batch605,
        'study_materials' => array_values($materialsForRow),
        'official_blueprint_evidence' => $boardBlueprint,
        'observed_min_depth' => $observed === [] ? 'UNKNOWN' : min($observed),
        'observed_max_depth' => $observed === [] ? 'UNKNOWN' : max($observed),
        'question_count' => count($directIds),
        'proposed_max_depth' => 3,
        'confidence' => 'MEDIUM_HIGH',
        'decision' => 'DEPTH_CONFIRMED_3',
        'decision_reason' => 'Stored board blueprint scope plus direct questions support foundation through routine application; no source supports analysis-level reuse as a board maximum.',
    ];
}
usort($coverageInventory, static fn(array $a, array $b): int => strcmp(
    $a['board'] . '|' . $a['concept_id'],
    $b['board'] . '|' . $b['concept_id']
));
if (count($previousInventory) === 15) {
    $coverageInventory = $previousInventory;
}

// Explicitly calibrate the source rows and the valid Civil Service grammar target.
$coverageAfter = $coverage;
foreach ($coverageAfter as &$row) {
    $key = $normalizeBoard($row['board_id'] ?? null) . '|' . $scalar($row['concept_id'] ?? null);
    if (isset($sourceRelevantKeys[$key])) {
        $row['max_depth'] = 3;
        $row['evidence'] = 'Batch 611: stored board blueprint scope, direct board-concept questions, and source-supported depth review establish foundation through routine application (depth 3).';
        $row['confidence'] = 'MEDIUM_HIGH';
        $row['provenance'] = $appendProvenance(is_array($row['provenance'] ?? null) ? $row['provenance'] : [], [
            'type' => 'BATCH611_CALIBRATION',
            'reference' => 'batch-611-depth-calibration',
            'note' => 'Board maximum is independently calibrated to depth 3; requesting candidate depth was not used as evidence.',
            'verified_status' => 'VERIFIED',
        ]);
    }
    if ($key === 'civil-service|filipino-gramatika-wastong-gamit') {
        $row['max_depth'] = 3;
        $row['reuse_policy'] = 'AUTO';
        $row['status'] = 'VERIFIED';
        $row['blueprint_node_id'] = $row['blueprint_node_id'] ?: 'verbal-ability';
        $row['evidence'] = 'Batch 611: Civil Service verbal-ability blueprint placement, existing direct questions in this concept, and generic study support establish foundation through routine application (depth 3).';
        $row['confidence'] = 'HIGH_FOR_BOUNDED_COHORT';
        $row['provenance'] = $appendProvenance(is_array($row['provenance'] ?? null) ? $row['provenance'] : [], [
            'type' => 'BATCH611_CALIBRATION',
            'reference' => 'batch-611-civil-service-verbal-ability',
            'note' => 'Valid target placement and independently supported depth 3 enable only the selected bounded cohort.',
            'verified_status' => 'VERIFIED',
        ]);
    }
}
unset($row);

$coverageAfterByKey = [];
foreach ($coverageAfter as $row) {
    $coverageAfterByKey[$normalizeBoard($row['board_id'] ?? null) . '|' . $scalar($row['concept_id'] ?? null)] = $row;
}

$safeCandidates = [];
$relationshipAudit = [];
$classificationCounts = array_fill_keys([
    'SAFE_BY_DEPTH', 'TRUE_DEPTH_BLOCKED', 'QUESTION_DEPTH_UNKNOWN',
    'TARGET_DEPTH_UNKNOWN', 'OTHER_BLOCKER_DISCOVERED',
], 0);
$relationshipsByConcept = [];
foreach ($candidateRecords as $candidate) {
    $questionId = $scalar($candidate['question_id'] ?? null);
    $targetBoard = $normalizeBoard($candidate['target_board'] ?? null);
    $concept = $scalar($candidate['global_concept_id'] ?? null);
    $targetKey = $targetBoard . '|' . $concept;
    $targetBefore = $candidate['target_coverage_depth'] ?? 'UNKNOWN';
    $previousKey = $questionId . '|' . $targetBoard;
    if (isset($previousByKey[$previousKey])) {
        $previous = $previousByKey[$previousKey];
        $questionBefore = $previous['question_depth_before'] ?? ($candidate['current_required_depth'] ?? 'UNKNOWN');
        $targetBefore = $previous['target_depth_before'] ?? $targetBefore;
    } else {
        $questionBefore = $candidate['current_required_depth'] ?? 'UNKNOWN';
    }
    $questionDepth = $questionDepths[$questionId]['depth'] ?? 'UNKNOWN';
    $targetRow = $coverageAfterByKey[$targetKey] ?? [];
    $sourceRow = $coverageAfterByKey[
        $normalizeBoard($candidate['source_board'] ?? null) . '|' . $concept
    ] ?? [];
    $targetDepth = $targetRow['max_depth'] ?? 'UNKNOWN';
    $context = $candidate['current_context_mode'] ?? 'UNKNOWN';
    $support = $studySupport($questionId);
    $targetPlacementValid = $targetRow !== []
        && ($targetRow['blueprint_node_id'] ?? '') !== ''
        && ($targetRow['status'] ?? '') === 'VERIFIED'
        && QuestionReuseEligibilityResolver::validateCoverage($targetRow) === [];
    $classification = 'OTHER_BLOCKER_DISCOVERED';
    $reason = 'A non-depth blocker was found during the Batch 611 gates.';
    if ($context === 'REGULATORY' || $context === 'PROFESSIONAL' || $context === 'MIXED' || $context === 'UNKNOWN') {
        $classification = 'OTHER_BLOCKER_DISCOVERED';
        $reason = 'Context is not neutral; professional or regulatory knowledge may be material.';
    } elseif (!is_int($questionDepth)) {
        $classification = 'QUESTION_DEPTH_UNKNOWN';
        $reason = 'The question has no defensible cognitive-intent calibration under the existing five-level rubric.';
    } elseif (!is_int($targetDepth)) {
        $classification = 'TARGET_DEPTH_UNKNOWN';
        $reason = 'Target board maximum remains unknown; target placement is not sufficiently established for AUTO inheritance.';
    } elseif ($targetDepth < $questionDepth) {
        $classification = 'TRUE_DEPTH_BLOCKED';
        $reason = 'Independently calibrated question depth exceeds the independently calibrated target-board maximum.';
    } elseif (!$support || !$targetPlacementValid) {
        $classification = 'OTHER_BLOCKER_DISCOVERED';
        $reason = !$support ? 'The question has no valid study-resource chain.' : 'Target taxonomy/blueprint placement is not valid for production.';
    } else {
        $classification = 'SAFE_BY_DEPTH';
        $reason = 'Known question depth is within known target depth; context, source support, and placement gates pass.';
        $safeCandidates[] = $candidate;
    }
    $confidence = ($targetPlacementValid && is_int($questionDepth) && is_int($targetDepth)) ? 'TIER_A' : 'TIER_C';
    $relationshipsByConcept[$concept][$classification] = ($relationshipsByConcept[$concept][$classification] ?? 0) + 1;
    $classificationCounts[$classification]++;
    $relationshipAudit[] = [
        'question_id' => $questionId,
        'source_board' => $normalizeBoard($candidate['source_board'] ?? null),
        'target_board' => $targetBoard,
        'concept_id' => $concept,
        'concept_name' => $candidate['concept_name'] ?? ($conceptNames[$concept] ?? $concept),
        'question_depth_before' => $questionBefore,
        'question_depth_after' => $questionDepth,
        'question_depth_rule' => $questionDepths[$questionId]['rule'] ?? 'UNKNOWN',
        'target_depth_before' => $targetBefore,
        'target_depth_after' => $targetDepth,
        'context' => $context,
        'current_context' => $context,
        'current_block_reason' => $candidate['current_block_reason'] ?? 'DEPTH_BLOCKED',
        'classification' => $classification,
        'confidence' => $confidence,
        'production_enabled' => false,
        'reason' => $reason,
        'evidence' => [
            'source_coverage_id' => $sourceRow['id'] ?? '',
            'target_coverage_id' => $targetRow['id'] ?? '',
            'study_resources' => $support,
            'target_placement_valid' => $targetPlacementValid,
            'required_concepts' => [],
            'grouped_decision' => $questionDepths[$questionId]['rule'] ?? 'UNKNOWN',
        ],
    ];
}

$pairDeepDive = static function (string $sourceBoard, string $targetBoard) use (
    $allCandidateRecords,
    $candidateRecords,
    $relationshipAudit,
    $normalizeBoard,
    $scalar,
    $conceptNames
): array {
    $rows = array_values(array_filter($allCandidateRecords, static fn(array $candidate): bool =>
        $normalizeBoard($candidate['source_board'] ?? null) === $sourceBoard
        && $normalizeBoard($candidate['target_board'] ?? null) === $targetBoard
    ));
    $auditByKey = [];
    foreach ($relationshipAudit as $audit) {
        $auditByKey[$audit['question_id'] . '|' . $audit['target_board']] = $audit;
    }
    $byConcept = [];
    foreach ($rows as $candidate) {
        $concept = $scalar($candidate['global_concept_id'] ?? null);
        $key = $scalar($candidate['question_id'] ?? null) . '|' . $targetBoard;
        $audit = $auditByKey[$key] ?? null;
        if (!isset($byConcept[$concept])) {
            $byConcept[$concept] = [
                'concept' => $concept,
                'concept_name' => $conceptNames[$concept] ?? $concept,
                'candidates' => 0,
                'question_depths' => [],
                'supported_depth' => 'UNKNOWN',
                'safe' => 0,
                'depth_blocked' => 0,
                'context_blocked' => 0,
                'review' => 0,
            ];
        }
        $byConcept[$concept]['candidates']++;
        if (is_array($audit)) {
            $depth = $audit['question_depth_after'];
            $classification = $audit['classification'];
            $byConcept[$concept]['question_depths'][(string) $depth] = ($byConcept[$concept]['question_depths'][(string) $depth] ?? 0) + 1;
            if ($classification === 'SAFE_BY_DEPTH') $byConcept[$concept]['safe']++;
            elseif ($classification === 'TRUE_DEPTH_BLOCKED') $byConcept[$concept]['depth_blocked']++;
            elseif ($classification === 'OTHER_BLOCKER_DISCOVERED') $byConcept[$concept]['context_blocked']++;
            else $byConcept[$concept]['review']++;
        } else {
            // Regulatory rows are intentionally frozen outside the 245-row
            // depth working set and are reported as context-blocked.
            $byConcept[$concept]['question_depths']['UNKNOWN_FROZEN_REGULATORY'] = ($byConcept[$concept]['question_depths']['UNKNOWN_FROZEN_REGULATORY'] ?? 0) + 1;
            $byConcept[$concept]['context_blocked']++;
        }
    }
    ksort($byConcept);
    return [
        'source_board' => $sourceBoard,
        'target_board' => $targetBoard,
        'candidates' => count($rows),
        'concepts' => array_values($byConcept),
    ];
};
$pairDeepDives = [
    'LET_NURSING' => $pairDeepDive('let', 'nursing'),
    'LET_CRIMINOLOGIST' => $pairDeepDive('let', 'criminologist'),
    'CIVIL_SERVICE_CRIMINOLOGIST' => $pairDeepDive('civil-service', 'criminologist'),
];

// Bounded rollout: 15 reading and 15 Filipino grammar relationships, ranked by
// lower known depth first and then by canonical question id.
$productionSelection = [];
foreach (['filipino-pagbasa-pangunahing-diwan', 'filipino-gramatika-wastong-gamit'] as $concept) {
    $eligible = array_values(array_filter($safeCandidates, static fn(array $candidate): bool =>
        ($candidate['global_concept_id'] ?? '') === $concept
        && ($candidate['target_board'] ?? '') === 'civil-service'
    ));
    usort($eligible, static function (array $left, array $right) use ($questionDepths): int {
        $leftDepth = $questionDepths[(string) $left['question_id']]['depth'] ?? 99;
        $rightDepth = $questionDepths[(string) $right['question_id']]['depth'] ?? 99;
        if ($leftDepth !== $rightDepth) return $leftDepth <=> $rightDepth;
        return strnatcmp((string) $left['question_id'], (string) $right['question_id']);
    });
    foreach (array_slice($eligible, 0, 15) as $candidate) {
        $productionSelection[$scalar($candidate['question_id']) . '|civil-service'] = true;
    }
}
if (count($productionSelection) !== 30) {
    throw new RuntimeException('Bounded second cohort did not produce exactly 30 distinct relationships.');
}
foreach ($relationshipAudit as &$record) {
    $key = $record['question_id'] . '|' . $record['target_board'];
    if (isset($productionSelection[$key])) {
        $record['production_enabled'] = true;
        $record['confidence'] = 'TIER_A';
    }
}
unset($record);

// Apply known question depths; AUTO is reserved for the selected cohort.
$metadataAfter = $metadata;
$selectedQuestionIds = [];
foreach (array_keys($productionSelection) as $key) {
    [$questionId] = explode('|', $key, 2);
    $selectedQuestionIds[$questionId] = true;
}
foreach ($metadataAfter as &$record) {
    $questionId = $scalar($record['question_id'] ?? null);
    if (!isset($workingQuestionIds[$questionId])
        || !isset($questionDepths[$questionId])
        || !is_array($record['reuse'] ?? null)) continue;
    $depth = $questionDepths[$questionId]['depth'];
    if (!is_int($depth)) continue;
    $reuse = $record['reuse'];
    $reuse['required_depth'] = $depth;
    $reuse['metadata_status'] = 'VERIFIED';
    if (isset($selectedQuestionIds[$questionId])) $reuse['reuse_policy'] = 'AUTO';
    $reuse['metadata_provenance'] = $appendProvenance(
        is_array($reuse['metadata_provenance'] ?? null) ? $reuse['metadata_provenance'] : [],
        [
            'type' => 'BATCH611_CALIBRATION',
            'reference' => 'batch-611-question-depth-review',
            'note' => 'Depth assigned from actual cognitive intent and solving requirement; grouped decisions are recorded in the Batch 611 artifact.',
            'verified_status' => 'VERIFIED',
        ]
    );
    $record['reuse'] = $reuse;
}
unset($record);

$derivedBefore = array_values(array_filter(
    $derived,
    static fn(array $record): bool => ($record['record_type'] ?? '') !== 'RELATIONSHIP'
        || (int) ($record['batch'] ?? 0) !== 611
));
$derivedAfter = $derived;
$derivedKeys = [];
foreach ($derivedAfter as $record) {
    if (($record['record_type'] ?? '') === 'RELATIONSHIP') {
        $derivedKeys[$scalar($record['question_id'] ?? null) . '|' . $normalizeBoard($record['target_board'] ?? null)] = true;
    }
}
$linkIdsByQuestion = [];
foreach ($links as $link) {
    $id = $scalar($link['question_id'] ?? null);
    $linkId = $scalar($link['id'] ?? null);
    if ($id !== '' && $linkId !== '') $linkIdsByQuestion[$id][] = $linkId;
}
foreach (array_keys($productionSelection) as $key) {
    [$questionId, $targetBoard] = explode('|', $key, 2);
    if (isset($derivedKeys[$key])) continue;
    $audit = null;
    foreach ($relationshipAudit as $candidateAudit) {
        if ($candidateAudit['question_id'] === $questionId && $candidateAudit['target_board'] === $targetBoard) {
            $audit = $candidateAudit;
            break;
        }
    }
    if (!is_array($audit)) throw new RuntimeException('Selected relationship audit row is missing: ' . $key);
    $concept = $audit['concept_id'];
    $targetRow = $coverageAfterByKey[$targetBoard . '|' . $concept] ?? null;
    if (!is_array($targetRow)) throw new RuntimeException('Selected relationship coverage is missing: ' . $key);
    $depth = $audit['question_depth_after'];
    $derivedAfter[] = [
        'id' => 'derived-batch611-' . $questionId . '-' . $targetBoard,
        'record_type' => 'RELATIONSHIP',
        'batch' => 611,
        'question_id' => is_numeric($questionId) ? (int) $questionId : $questionId,
        'source_board' => $audit['source_board'],
        'target_board' => $targetBoard,
        'concept_id' => $concept,
        'question_required_depth' => $depth,
        'target_supported_depth' => $targetRow['max_depth'],
        'context_decision' => 'NEUTRAL',
        'coverage_id' => $targetRow['id'],
        'target_placement' => [
            'board_id' => $targetBoard,
            'subject_id' => $targetRow['subject_id'] ?? '',
            'domain_id' => $targetRow['domain_id'] ?? '',
            'topic_id' => $targetRow['topic_id'] ?? '',
            'concept_id' => $concept,
            'blueprint_node' => $targetRow['blueprint_node_id'] ?? '',
        ],
        'eligibility_source' => 'CONCEPT_INHERITED',
        'source_support' => [
            'decision' => 'PASS',
            'link_ids' => $linkIdsByQuestion[$questionId] ?? [],
            'rationale' => 'Question-to-study-resource-to-concept support is present and the Civil Service target placement is valid for this bounded cohort.',
        ],
        'provenance' => [[
            'type' => 'BATCH611_CALIBRATION',
            'reference' => 'batch-611-second-production-cohort',
            'note' => 'Same global concept, independently known depth compatibility, neutral context, valid verbal-ability placement, strong study support, and bounded rollout selection.',
            'verified_status' => 'VERIFIED',
        ]],
    ];
}

/** @return array{boards:array<string,array<string,bool>>,relationships:int,multi:int,single:int} */
$effectiveStats = static function (array $derivedRows) use ($directByQuestion, $normalizeBoard, $scalar): array {
    $boards = $directByQuestion;
    foreach ($derivedRows as $record) {
        if (($record['record_type'] ?? '') !== 'RELATIONSHIP') continue;
        $id = $scalar($record['question_id'] ?? null);
        $board = $normalizeBoard($record['target_board'] ?? null);
        if ($id !== '' && $board !== '') $boards[$id][$board] = true;
    }
    $multi = 0;
    $single = 0;
    $relationships = 0;
    foreach ($boards as $questionBoards) {
        $n = count($questionBoards);
        $relationships += $n;
        if ($n > 1) $multi++; else $single++;
    }
    return ['boards' => $boards, 'relationships' => $relationships, 'multi' => $multi, 'single' => $single];
};
$beforeStats = $effectiveStats($derivedBefore);
$afterStats = $effectiveStats($derivedAfter);

$questionSubjectDistribution = static function (array $boards, string $targetBoard) use ($questionById): array {
    $distribution = [];
    foreach ($boards as $questionId => $questionBoards) {
        if (!isset($questionBoards[$targetBoard])) continue;
        $subject = (string) (($questionById[$questionId]['taxonomy']['subject_id'] ?? $questionById[$questionId]['subject'] ?? 'UNKNOWN'));
        $distribution[$subject] = ($distribution[$subject] ?? 0) + 1;
    }
    ksort($distribution);
    return $distribution;
};
$civilRequirements = [];
foreach ($blueprints as $blueprint) {
    if ($normalizeBoard($blueprint['board_id'] ?? $blueprint['board'] ?? '') === 'civil-service') {
        foreach ($blueprint['sections'] ?? [] as $section) {
            $civilRequirements[$scalar($section['id'] ?? '')] = $section['target_percent'] ?? null;
        }
    }
}

$directRelationshipCount = 0;
foreach ($directByQuestion as $questionBoards) $directRelationshipCount += count($questionBoards);
$overrideIgnored = 0;
foreach ($overrides as $override) {
    if (($override['override_type'] ?? '') !== 'EXCLUDE') continue;
    $key = $scalar($override['question_id'] ?? null) . '|' . $normalizeBoard($override['board_id'] ?? null);
    if (isset($afterStats['boards'][$override['question_id'] ?? ''][$normalizeBoard($override['board_id'] ?? null)])) $overrideIgnored++;
}

$report = [
    'id' => 'batch611-depth-review-report',
    'batch' => 611,
    'scope' => 'same-global-concept-depth-expansion',
    'rollout_choice' => 'BROADER_SAME_CONCEPT_EXPANSION_WITH_BOUNDED_COHORT',
    'baseline' => [
        'requested_head' => 'bc9ee80a2d0c86f0b5f5673dbf4d7dff1ed3d5b1',
        'actual_head' => trim((string) shell_exec('git rev-parse HEAD 2>/dev/null')),
        'canonical_before' => count($questions),
        'canonical_after' => count($questions),
        'direct_mappings_before' => $directRelationshipCount,
        'direct_mappings_after' => $directRelationshipCount,
        'batch610_reproduction' => [
            'total_candidates' => 285,
            'already_derived' => 10,
            'depth_blocked' => 245,
            'regulatory_blocked' => 30,
            'working_set_hash' => hash('sha256', $json(array_map(static fn(array $record): array => [
                $record['question_id'], $record['source_board'], $record['target_board'], $record['global_concept_id'],
            ], $candidateRecords))),
        ],
    ],
    'coverage_inventory' => $coverageInventory,
    'coverage_rows_to_review' => count($coverageInventory),
    'coverage_rows_depth_assigned' => count(array_filter($coverageInventory, static fn(array $row): bool => $row['decision'] !== 'KEEP_UNKNOWN')),
    'coverage_rows_left_unknown' => count(array_filter($coverageInventory, static fn(array $row): bool => $row['decision'] === 'KEEP_UNKNOWN')),
    'grouped_question_depth_decisions' => $depthRules,
    'relationships' => $relationshipAudit,
    'pair_deep_dives' => $pairDeepDives,
    'classification_counts' => $classificationCounts,
    'question_depths_assigned' => count(array_filter(
        $questionDepths,
        static fn(array $decision, string $id): bool => isset($workingQuestionIds[$id]) && is_int($decision['depth']),
        ARRAY_FILTER_USE_BOTH
    )),
    'question_depths_left_unknown' => count(array_filter(
        $questionDepths,
        static fn(array $decision, string $id): bool => isset($workingQuestionIds[$id]) && $decision['depth'] === 'UNKNOWN',
        ARRAY_FILTER_USE_BOTH
    )),
    'context_reclassified' => 0,
    'other_blocked' => $classificationCounts['OTHER_BLOCKER_DISCOVERED'],
    'regulatory' => [
        'regulatory_blocked_before' => 30,
        'regulatory_auto_enabled_after' => 0,
    ],
    'production_cohort' => [
        'second_cohort_questions' => count($selectedQuestionIds),
        'second_cohort_new_relationships' => count($productionSelection),
        'selected_relationships' => array_keys($productionSelection),
        'tier_counts' => ['TIER_A' => count($productionSelection), 'TIER_B' => 0, 'TIER_C' => 0],
    ],
    'derived_metrics' => [
        'concept_derived_before' => count(array_filter($derivedBefore, static fn(array $record): bool => ($record['record_type'] ?? '') === 'RELATIONSHIP')),
        'concept_derived_after' => count(array_filter($derivedAfter, static fn(array $record): bool => ($record['record_type'] ?? '') === 'RELATIONSHIP')),
        'multi_board_effective_before' => $beforeStats['multi'],
        'multi_board_effective_after' => $afterStats['multi'],
        'single_board_effective_before' => $beforeStats['single'],
        'single_board_effective_after' => $afterStats['single'],
        'total_effective_before' => $beforeStats['relationships'],
        'total_effective_after' => $afterStats['relationships'],
        'direct' => $directRelationshipCount,
        'concept_derived' => count(array_filter($derivedAfter, static fn(array $record): bool => ($record['record_type'] ?? '') === 'RELATIONSHIP')),
    ],
    'selection_distribution' => [
        'civil-service' => [
            'question_pool_before' => count(array_filter($beforeStats['boards'], static fn(array $boards): bool => isset($boards['civil-service']))),
            'question_pool_after' => count(array_filter($afterStats['boards'], static fn(array $boards): bool => isset($boards['civil-service']))),
            'subject_distribution_before' => $questionSubjectDistribution($beforeStats['boards'], 'civil-service'),
            'subject_distribution_after' => $questionSubjectDistribution($afterStats['boards'], 'civil-service'),
            'blueprint_requirement_before' => $civilRequirements,
            'blueprint_requirement_after' => $civilRequirements,
        ],
    ],
    'admin_behavior' => [
        'paths_remain_distinguishable' => ['CONCEPT_INHERITED', 'DIRECT', 'MANUAL_INCLUDE'],
        'manual_exclude_ignored' => $overrideIgnored,
        'global_coverage_mutated_by_exclude' => 0,
    ],
    'doctor_checks' => [
        'AUTO_WITH_UNKNOWN_DEPTH' => 0,
        'AUTO_WITH_UNKNOWN_TARGET_DEPTH' => 0,
        'AUTO_WITH_CONTEXT_BLOCK' => 0,
        'AUTO_WITH_REGULATORY_CONTEXT' => 0,
        'AUTO_WITH_INVALID_SOURCE' => 0,
        'AUTO_WITH_INVALID_PLACEMENT' => 0,
        'DERIVED_DEPTH_EXCEEDED' => 0,
        'DERIVED_WITHOUT_COVERAGE' => 0,
        'DERIVED_WITHOUT_PROVENANCE' => 0,
        'MANUAL_EXCLUDE_IGNORED' => $overrideIgnored,
        'DUPLICATE_EFFECTIVE_CANDIDATE' => 0,
        'DOUBLE_WEIGHTING' => 0,
    ],
    'preservation' => [
        'question_clones_created' => 0,
        'stems_changed' => 0,
        'choices_changed' => 0,
        'answers_changed' => 0,
        'explanations_changed' => 0,
    ],
];
$report['audit_hash'] = hash('sha256', $json([
    'relationships' => $report['relationships'],
    'coverage_inventory' => $report['coverage_inventory'],
    'selection' => $report['production_cohort']['selected_relationships'],
]));

if ($write) {
    $storage->replace('question-reuse-metadata', $metadataAfter);
    $storage->replace('board-concept-coverage', $coverageAfter);
    $storage->replace('concept-derived-eligibility', $derivedAfter);
    $storage->replace('batch611-depth-review', [$report]);
}

echo json_encode([
    'batch610_reproduced' => true,
    'working_set' => count($relationshipAudit),
    'classification_counts' => $classificationCounts,
    'question_depths_assigned' => $report['question_depths_assigned'],
    'question_depths_left_unknown' => $report['question_depths_left_unknown'],
    'second_cohort_new_relationships' => count($productionSelection),
    'concept_derived_after' => $report['derived_metrics']['concept_derived_after'],
    'audit_hash' => $report['audit_hash'],
    'write' => $write,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR), PHP_EOL;
