<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/Core/Autoloader.php';
\App\Core\Autoloader::register();

use App\Core\App;
use App\Services\Question\QuestionEligibilityIndex;
use App\Services\Question\QuestionReuseEligibilityResolver;
use App\Services\Question\QuestionReuseMetadataService;
use App\Services\Shared\TaxonomyStorageService;

$dryRun = in_array('--dry-run', array_slice($argv, 1), true);
$storage = App::storage();
$questions = array_values(array_filter($storage->all('questions'), 'is_array'));
$boards = array_values(array_filter($storage->all('boards'), 'is_array'));
$eligibility = array_values(array_filter($storage->all('question-eligibility'), 'is_array'));
$blueprints = array_values(array_filter($storage->all('blueprints'), 'is_array'));
$taxonomy = new TaxonomyStorageService();
$index = new QuestionEligibilityIndex($eligibility, $blueprints);

$boardIds = [];
foreach ($boards as $board) {
    if (is_scalar($board['id'] ?? null)) {
        $boardIds[] = strtolower(trim((string) $board['id']));
    }
}
$boardIds = array_values(array_unique($boardIds));

$batch605ByQuestion = [];
$placementsByQuestion = [];
$subjectsByQuestionBoard = [];
foreach ($eligibility as $entry) {
    $entryId = (string) ($entry['id'] ?? 'question-eligibility');
    foreach (($entry['question_ids'] ?? []) as $questionId) {
        if (!is_scalar($questionId)) continue;
        $id = (string) $questionId;
        $override = is_array($entry['question_mappings'][$id] ?? null) ? $entry['question_mappings'][$id] : [];
        $exams = is_array($override['exam_ids'] ?? null) ? $override['exam_ids'] : ($entry['exam_ids'] ?? []);
        $subjectIds = is_array($override['subject_ids'] ?? null) ? $override['subject_ids'] : ($entry['subject_ids'] ?? []);
        foreach ($exams as $exam) {
            if (!is_scalar($exam)) continue;
            $board = strtolower(trim((string) $exam));
            $subject = $subjectIds[$exam] ?? $subjectIds[$board] ?? '';
            if (is_scalar($subject) && trim((string) $subject) !== '') {
                $subjectsByQuestionBoard[$id][$board] = trim((string) $subject);
            }
        }
    }
    foreach (($entry['question_mappings'] ?? []) as $questionId => $mapping) {
        if (!is_array($mapping)) {
            continue;
        }
        $id = (string) $questionId;
        if (($mapping['audit']['batch'] ?? null) === 605
            && ($mapping['audit']['classification'] ?? null) === 'A') {
            $batch605ByQuestion[$id] = true;
        }
        if (is_array($mapping['placements'] ?? null)) {
            foreach ($mapping['placements'] as $boardId => $placement) {
                if (is_array($placement)) {
                    $placementsByQuestion[$id][strtolower(trim((string) $boardId))] = $placement;
                }
            }
        }
        if (!isset($placementsByQuestion[$id])) {
            $placementsByQuestion[$id] = [];
        }
        $placementsByQuestion[$id]['_entry:' . $entryId] = true;
    }
}

$metadataRecords = [];
$existingMetadataByQuestion = [];
foreach ($storage->all('question-reuse-metadata') as $record) {
    if (!is_array($record) || !is_scalar($record['question_id'] ?? null)) continue;
    $reuse = $record['reuse'] ?? null;
    if (is_array($reuse) && QuestionReuseMetadataService::validate($reuse) === []) {
        $existingMetadataByQuestion[(string)$record['question_id']] = $reuse;
    }
}
$coverage = [];
$coverageKeys = [];
$coverageKey = static function (array $row): string {
    return implode('|', [
        (string)($row['board_id'] ?? ''),
        (string)($row['concept_id'] ?? ''),
        (string)($row['subject_id'] ?? ''),
        (string)($row['domain_id'] ?? ''),
        (string)($row['topic_id'] ?? ''),
        (string)($row['blueprint_node_id'] ?? ''),
    ]);
};
foreach ($storage->all('board-concept-coverage') as $row) {
    if (is_array($row) && QuestionReuseEligibilityResolver::validateCoverage($row) === []) {
        $key = $coverageKey($row);
        if (isset($coverageKeys[$key])) {
            continue;
        }
        $id = (string)($row['id'] ?? hash('sha256', json_encode($row)));
        $coverage[$id] = $row;
        $coverageKeys[$key] = $id;
    }
}
$questionBoardCounts = [];
$boardSubjectIds = [];
foreach ($boards as $board) {
    $boardId = strtolower(trim((string) ($board['id'] ?? '')));
    foreach ($taxonomy->subjectsByBoard($boardId) as $relation) {
        if (is_scalar($relation['subject_id'] ?? null)) $boardSubjectIds[$boardId][] = (string) $relation['subject_id'];
    }
}
foreach ($questions as $question) {
    $questionId = (string) ($question['id'] ?? '');
    $effectiveBoards = [];
    foreach ($boardIds as $boardId) {
        if ($index->forExam($question, $boardId) !== null) {
            $effectiveBoards[] = $boardId;
        }
    }
    $questionBoardCounts[$questionId] = count($effectiveBoards);
    $reuse = $existingMetadataByQuestion[$questionId]
        ?? QuestionReuseMetadataService::migrate(
            $question,
            count($effectiveBoards) > 1,
            isset($batch605ByQuestion[$questionId])
        );
    $metadataRecords[] = [
        'id' => 'question-reuse-' . $questionId,
        'question_id' => $questionId,
        'reuse' => $reuse,
    ];

    $canonicalTaxonomy = is_array($question['taxonomy'] ?? null) ? $question['taxonomy'] : [];
    foreach ($effectiveBoards as $boardId) {
        $effective = $index->forExam($question, $boardId) ?? [];
        $placement = $placementsByQuestion[$questionId][$boardId] ?? [];
        $resolvedPlacement = (string) ($effective['subject_id'] ?? '');
        $targetSubject = (string) ($placement['subject_id'] ?? $subjectsByQuestionBoard[$questionId][$boardId] ?? '');
        if (!in_array($targetSubject, $boardSubjectIds[$boardId] ?? [], true)
            && $boardId === strtolower(trim((string) ($canonicalTaxonomy['board_id'] ?? '')))
            && in_array((string) ($canonicalTaxonomy['subject_id'] ?? ''), $boardSubjectIds[$boardId] ?? [], true)) {
            $targetSubject = (string) ($canonicalTaxonomy['subject_id'] ?? '');
        }
        if ($targetSubject === '' || !in_array($targetSubject, $boardSubjectIds[$boardId] ?? [], true)) {
            foreach ($blueprints as $blueprint) {
                if (($blueprint['board_id'] ?? $blueprint['board'] ?? '') !== $boardId) continue;
                foreach (array_merge($blueprint['sections'] ?? [], $blueprint['official_areas'] ?? []) as $node) {
                    if (!is_array($node) || (string) ($node['id'] ?? '') !== $resolvedPlacement) continue;
                    $candidate = (string) ($node['taxonomy_subject'] ?? '');
                    if ($candidate === '' && is_scalar($node['domain_id'] ?? null)) {
                        $domain = $taxonomy->find('taxonomy/domains', (string) $node['domain_id']);
                        $candidate = (string) ($domain['subject_id'] ?? '');
                    }
                    if (!in_array($candidate, $boardSubjectIds[$boardId] ?? [], true)) {
                        foreach ($boardSubjectIds[$boardId] ?? [] as $configuredSubject) {
                            if ($candidate !== '' && ($resolvedPlacement === $configuredSubject || str_ends_with($configuredSubject, '-' . $resolvedPlacement))) {
                                $candidate = $configuredSubject;
                                break;
                            }
                        }
                    }
                    if (in_array($candidate, $boardSubjectIds[$boardId] ?? [], true)) $targetSubject = $candidate;
                }
            }
        }
        $sameTaxonomySubject = $targetSubject !== '' && $targetSubject === (string) ($canonicalTaxonomy['subject_id'] ?? '');
        $domainId = (string) ($placement['domain_id'] ?? ($sameTaxonomySubject ? ($canonicalTaxonomy['domain_id'] ?? '') : ''));
        $topicId = (string) ($placement['topic_id'] ?? ($sameTaxonomySubject ? ($canonicalTaxonomy['topic_id'] ?? '') : ''));
        $conceptId = (string) ($placement['concept_id'] ?? ($sameTaxonomySubject ? ($canonicalTaxonomy['concept_id'] ?? '') : ''));
        $blueprintNode = (string) ($placement['blueprint_node'] ?? ($resolvedPlacement !== '' ? $resolvedPlacement : $targetSubject));
        if ($targetSubject === '') {
            continue;
        }
        if (!QuestionReuseEligibilityResolver::validBlueprintPlacement($boardId, $blueprintNode, $targetSubject)) {
            $blueprintNode = '';
        }
        $key = $coverageKey([
            'board_id' => $boardId,
            'concept_id' => (string) ($canonicalTaxonomy['concept_id'] ?? ''),
            'subject_id' => $targetSubject,
            'domain_id' => $domainId,
            'topic_id' => $topicId,
            'blueprint_node_id' => $blueprintNode,
        ]);
        $coverageId = 'coverage-' . hash('sha256', $key);
        if (isset($coverageKeys[$key])) {
            continue;
        }
        $provenance = [[
            'type' => 'EXISTING_VERIFIED_MAPPING',
            'reference' => 'question:' . $questionId,
            'note' => 'Existing direct eligibility establishes that the board consumes this canonical concept placement.',
            'verified_status' => 'VERIFIED',
        ]];
        if (isset($batch605ByQuestion[$questionId])) {
            $provenance[] = [
                'type' => 'BATCH605_VERIFIED',
                'reference' => 'question-eligibility.audit.batch=605',
                'note' => 'Batch 605 direct cross-board verification was preserved.',
                'verified_status' => 'VERIFIED',
            ];
        }
        $coverage[$coverageId] = [
            'id' => $coverageId,
            'board_id' => $boardId,
            'concept_id' => (string) ($canonicalTaxonomy['concept_id'] ?? ''),
            'subject_id' => $targetSubject,
            'domain_id' => $domainId,
            'topic_id' => $topicId,
            'blueprint_node_id' => $blueprintNode,
            'max_depth' => 'UNKNOWN',
            'reuse_policy' => 'REVIEW',
            'status' => $blueprintNode === '' ? 'REVIEW_REQUIRED' : 'VERIFIED',
            'provenance' => $provenance,
            'evidence' => 'Existing direct question eligibility',
            'confidence' => 'HIGH_FOR_DIRECT_MAPPING',
        ];
        $coverageKeys[$key] = $coverageId;
    }
}
$coverage = array_values($coverage);

$foundations = [
    'concept-equivalence' => array_values(array_filter($storage->all('concept-equivalence'), 'is_array')),
    'question-reuse-overrides' => array_values(array_filter($storage->all('question-reuse-overrides'), 'is_array')),
    'question-required-concepts' => array_values(array_filter($storage->all('question-required-concepts'), 'is_array')),
];

$stable = static fn(array $records): string => json_encode(array_values($records), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
$changed = [];
$write = static function (string $collection, array $records) use ($storage, $stable, $dryRun, &$changed): void {
    $before = $storage->all($collection);
    if ($stable($before) !== $stable($records)) {
        $changed[] = $collection;
        if (!$dryRun) {
            $storage->replace($collection, $records);
        }
    }
};
$write('question-reuse-metadata', $metadataRecords);
$write('board-concept-coverage', $coverage);
foreach ($foundations as $collection => $records) {
    $write($collection, $records);
}

$counts = ['shared_questions' => 0, 'batch605_questions' => count($batch605ByQuestion), 'depth_unknown' => 0, 'coverage_unknown_depth' => 0];
foreach ($metadataRecords as $record) {
    $reuse = $record['reuse'];
    if ($reuse['metadata_status'] === 'VERIFIED') $counts['shared_questions']++;
    if ($reuse['required_depth'] === 'UNKNOWN') $counts['depth_unknown']++;
}
foreach ($coverage as $row) if ($row['max_depth'] === 'UNKNOWN') $counts['coverage_unknown_depth']++;

$hashInput = [];
foreach (['question-reuse-metadata' => $metadataRecords, 'board-concept-coverage' => $coverage] + $foundations as $name => $records) {
    $hashInput[] = $name . ':' . $stable($records);
}
$report = [
    'dry_run' => $dryRun,
    'questions_analyzed' => count($questions),
    'new_format_questions' => count($metadataRecords),
    'coverage_rows' => count($coverage),
    'equivalence_rows' => 0,
    'override_rows' => 0,
    'required_concept_rows' => 0,
    'changed_collections' => $changed,
    'migration_hash' => hash('sha256', implode('|', $hashInput)),
    'counts' => $counts,
    'production_auto_inheritance_enabled' => false,
    'canonical_content_changed' => 0,
];
echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR), PHP_EOL;
