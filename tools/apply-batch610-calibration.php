#!/usr/bin/env php
<?php

declare(strict_types=1);

/** Apply the deliberately small, hand-audited Batch 610 calibration cohort. */

require_once dirname(__DIR__) . '/app/Core/Autoloader.php';
\App\Core\Autoloader::register();

use App\Core\App;
use App\Services\Question\QuestionReuseEligibilityResolver;
use App\Services\Question\QuestionReuseMetadataService;

$storage = App::storage();
$questions = array_values(array_filter($storage->all('questions'), 'is_array'));
$metadata = array_values(array_filter($storage->all('question-reuse-metadata'), 'is_array'));
$coverage = array_values(array_filter($storage->all('board-concept-coverage'), 'is_array'));
$derived = array_values(array_filter($storage->all('concept-derived-eligibility'), 'is_array'));

// These questions are unchanged in the user's pre-existing dirty question file.
// The depth is assigned from the explicit cognitive intent, not difficulty.
$cohortDepths = [
    '422' => 2, // classify a directly stated thesis
    '423' => 3, // apply a grammar relationship to a sentence
    '425' => 2, // interpret a word from context
    '428' => 2, // identify logical sequence
    '430' => 2, // classify correct usage
    '433' => 3, // apply an appropriate formal tone
    '434' => 2, // identify a connective relationship
    '438' => 2, // identify textual cohesion
    '439' => 1, // recognize literal comprehension
    '440' => 2, // identify the first step in a summary process
];

$regulatoryIds = array_merge(['221', '222'], range(246, 258), range(520, 534));
$regulatoryIds = array_values(array_map('strval', $regulatoryIds));
$questionById = [];
foreach ($questions as $question) {
    $questionById[(string) ($question['id'] ?? '')] = $question;
}
$studyLinkIdsByQuestion = [];
foreach ($storage->all('study-question-links') as $link) {
    if (is_array($link) && isset($link['question_id'], $link['id'])) {
        $studyLinkIdsByQuestion[(string) $link['question_id']][] = (string) $link['id'];
    }
}

$appendProvenance = static function (array $reuse, array $record): array {
    $provenance = is_array($reuse['metadata_provenance'] ?? null) ? $reuse['metadata_provenance'] : [];
    foreach ($provenance as $existing) {
        if (is_array($existing)
            && ($existing['type'] ?? '') === ($record['type'] ?? '')
            && ($existing['reference'] ?? '') === ($record['reference'] ?? '')) {
            return $reuse;
        }
    }
    $provenance[] = $record;
    $reuse['metadata_provenance'] = $provenance;
    return $reuse;
};

$changedMetadata = 0;
foreach ($metadata as &$record) {
    $questionId = (string) ($record['question_id'] ?? '');
    if (!isset($questionById[$questionId]) || !is_array($record['reuse'] ?? null)) {
        continue;
    }
    $reuse = $record['reuse'];
    $before = json_encode($reuse, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    if (isset($cohortDepths[$questionId])) {
        $reuse['reuse_policy'] = 'AUTO';
        $reuse['required_depth'] = $cohortDepths[$questionId];
        $reuse['context_mode'] = 'NEUTRAL';
        $reuse['metadata_status'] = 'VERIFIED';
        $reuse = $appendProvenance($reuse, [
            'type' => 'BATCH610_CALIBRATION',
            'reference' => 'batch-610-cohort-let-civil-service',
            'note' => 'Depth and generic context were manually verified for the bounded first production cohort.',
            'verified_status' => 'VERIFIED',
        ]);
    } elseif (in_array($questionId, $regulatoryIds, true)) {
        $reuse['reuse_policy'] = 'MANUAL_ONLY';
        $reuse['context_mode'] = 'REGULATORY';
        $reuse['metadata_status'] = 'VERIFIED';
        $reuse = $appendProvenance($reuse, [
            'type' => 'BATCH610_CALIBRATION',
            'reference' => 'batch-610-regulatory-context-review',
            'note' => 'RA 6713 public-service requirements materially determine the answer; cross-board inheritance remains blocked by default.',
            'verified_status' => 'VERIFIED',
        ]);
    }
    if (json_encode($reuse, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) !== $before) {
        $record['reuse'] = $reuse;
        $changedMetadata++;
    }
}
unset($record);

$targetConcept = 'filipino-pagbasa-pangunahing-diwan';
$changedCoverage = 0;
foreach ($coverage as &$row) {
    $board = strtolower(trim((string) ($row['board_id'] ?? '')));
    if ((string) ($row['concept_id'] ?? '') !== $targetConcept || !in_array($board, ['let', 'civil-service'], true)) {
        continue;
    }
    $before = json_encode($row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    $row['max_depth'] = 3;
    if ($board === 'civil-service') {
        $row['reuse_policy'] = 'AUTO';
    }
    $row['status'] = 'VERIFIED';
    $row['evidence'] = 'Batch 610 bounded calibration: explicit cognitive-intent review, existing target-board concept coverage, stable taxonomy placement, and linked study support.';
    $row['confidence'] = 'HIGH_FOR_BOUNDED_COHORT';
    $provenance = is_array($row['provenance'] ?? null) ? $row['provenance'] : [];
    $exists = false;
    foreach ($provenance as $record) {
        if (is_array($record) && ($record['type'] ?? '') === 'BATCH610_CALIBRATION') {
            $exists = true;
            break;
        }
    }
    if (!$exists) {
        $provenance[] = [
            'type' => 'BATCH610_CALIBRATION',
            'reference' => 'batch-610-cohort-let-civil-service',
            'note' => 'Supported depth 3 is limited to foundation through ordinary application for this shared Filipino reading concept.',
            'verified_status' => 'VERIFIED',
        ];
    }
    $row['provenance'] = $provenance;
    if (json_encode($row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) !== $before) {
        $changedCoverage++;
    }
}
unset($row);

$configId = 'batch610-bounded-production-config';
$hasConfig = false;
foreach ($derived as $record) {
    if (($record['record_type'] ?? '') === 'CONFIG' && ($record['id'] ?? '') === $configId) {
        $hasConfig = true;
        break;
    }
}
if (!$hasConfig) {
    $derived[] = [
        'id' => $configId,
        'record_type' => 'CONFIG',
        'batch' => 610,
        'production_enabled' => true,
        'global_inheritance_enabled' => false,
        'scope' => 'EXPLICIT_RELATIONSHIPS_ONLY',
        'provenance' => [[
            'type' => 'BATCH610_CALIBRATION',
            'reference' => 'batch-610-cohort-let-civil-service',
            'note' => 'Production derivation is enabled only for the explicit relationship records in this collection.',
            'verified_status' => 'VERIFIED',
        ]],
    ];
}

$existingRelationshipKeys = [];
foreach ($derived as $record) {
    if (($record['record_type'] ?? '') === 'RELATIONSHIP') {
        $existingRelationshipKeys[(string) ($record['question_id'] ?? '') . '|' . strtolower((string) ($record['target_board'] ?? ''))] = true;
    }
}
foreach ($cohortDepths as $questionId => $depth) {
    $question = $questionById[$questionId] ?? null;
    if (!is_array($question)) {
        throw new RuntimeException('Calibrated question is missing: ' . $questionId);
    }
    $key = $questionId . '|civil-service';
    if (isset($existingRelationshipKeys[$key])) {
        continue;
    }
    $targetCoverage = null;
    foreach ($coverage as $row) {
        if (($row['board_id'] ?? '') === 'civil-service' && ($row['concept_id'] ?? '') === $targetConcept) {
            $targetCoverage = $row;
            break;
        }
    }
    if (!is_array($targetCoverage)) {
        throw new RuntimeException('Calibrated target coverage is missing.');
    }
    $derived[] = [
        'id' => 'derived-batch610-' . $questionId . '-civil-service',
        'record_type' => 'RELATIONSHIP',
        'batch' => 610,
        'question_id' => $questionId,
        'source_board' => 'let',
        'target_board' => 'civil-service',
        'concept_id' => $targetConcept,
        'question_required_depth' => $depth,
        'target_supported_depth' => 3,
        'context_decision' => 'NEUTRAL',
        'coverage_id' => (string) ($targetCoverage['id'] ?? ''),
        'target_placement' => [
            'board_id' => 'civil-service',
            'subject_id' => (string) ($targetCoverage['subject_id'] ?? ''),
            'domain_id' => (string) ($targetCoverage['domain_id'] ?? ''),
            'topic_id' => (string) ($targetCoverage['topic_id'] ?? ''),
            'concept_id' => $targetConcept,
            'blueprint_node' => (string) ($targetCoverage['blueprint_node_id'] ?? ''),
        ],
        'eligibility_source' => 'CONCEPT_INHERITED',
        'source_support' => [
            'decision' => 'PASS',
            'link_ids' => $studyLinkIdsByQuestion[$questionId] ?? [],
            'rationale' => 'Existing linked study material and source references cover the generic reading/grammar competency independently of board ownership.',
        ],
        'provenance' => [[
            'type' => 'BATCH610_CALIBRATION',
            'reference' => 'batch-610-cohort-let-civil-service',
            'note' => 'Same global concept, known depth compatibility, neutral context, valid Civil Service placement, and validated study support.',
            'verified_status' => 'VERIFIED',
        ]],
    ];
}
foreach ($derived as &$record) {
    if (($record['record_type'] ?? '') === 'RELATIONSHIP'
        && ($record['batch'] ?? null) === 610
        && isset($studyLinkIdsByQuestion[(string) ($record['question_id'] ?? '')])) {
        $record['source_support'] = [
            'decision' => 'PASS',
            'link_ids' => $studyLinkIdsByQuestion[(string) $record['question_id']],
            'rationale' => 'Existing linked study material and source references cover the generic reading/grammar competency independently of board ownership.',
        ];
    }
}
unset($record);

$storage->replace('question-reuse-metadata', $metadata);
$storage->replace('board-concept-coverage', $coverage);
$storage->replace('concept-derived-eligibility', $derived);

echo json_encode([
    'changed_metadata_records' => $changedMetadata,
    'changed_coverage_records' => $changedCoverage,
    'derived_relationships' => count(array_filter($derived, static fn(array $record): bool => ($record['record_type'] ?? '') === 'RELATIONSHIP')),
    'production_enabled' => true,
    'global_inheritance_enabled' => false,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR), PHP_EOL;
