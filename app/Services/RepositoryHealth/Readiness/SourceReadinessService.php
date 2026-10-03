<?php

declare(strict_types=1);

namespace App\Services\RepositoryHealth\Readiness;

use App\Core\App;
use App\Services\RepositoryHealth\CivilService\CivilServiceCoverageModel;

/** Permanent source-readiness facade used by health, authoring, and UI. */
final class SourceReadinessService
{
    public static function collectionNameForBoard(string $boardId): string
    {
        $boardId = strtolower(trim($boardId));
        return $boardId === 'criminologist'
            ? 'source-readiness'
            : 'source-readiness-' . $boardId;
    }

    /** @return array<int, array<string, mixed>> */
    public static function records(?array $collections = null, ?string $boardId = null): array
    {
        $collectionName = $boardId === null
            ? 'source-readiness'
            : self::collectionNameForBoard($boardId);
        if ($collections !== null) {
            return array_values(array_filter($collections[$collectionName] ?? [], 'is_array'));
        }
        return array_values(array_filter(App::storage()->all($collectionName), 'is_array'));
    }

    /** @param array<string, array> $collections @param array<string, array> $boardSummaries */
    public static function validateCollections(array $collections, array $boardSummaries = []): array
    {
        $validations = [];
        $allErrors = [];
        $allRecords = [];
        foreach (self::readinessCollections($collections) as $collectionName => $records) {
            if ($records === []) {
                continue;
            }
            $boardId = self::boardIdForCollection($collectionName, $records);
            if ($boardId === '') {
                $validations[$collectionName] = ['valid' => false, 'errors' => [[
                    'code' => 'SOURCE_READINESS.INVALID_RECORD',
                    'leaf_id' => '',
                    'message' => 'A source-readiness collection has no resolvable board ID.',
                    'evidence' => ['collection' => $collectionName],
                ]], 'records' => $records, 'board_id' => ''];
                $allErrors = array_merge($allErrors, $validations[$collectionName]['errors']);
                $allRecords = array_merge($allRecords, $records);
                continue;
            }

            $nativeCounts = self::nativeLeafCounts($collections, $boardId);
            $activeLeaves = array_keys($nativeCounts);
            if (isset($boardSummaries[$boardId]['leaf_counts']) && is_array($boardSummaries[$boardId]['leaf_counts'])) {
                $activeLeaves = array_keys($boardSummaries[$boardId]['leaf_counts']);
                foreach ($activeLeaves as $leafId) {
                    $nativeCounts[$leafId] = (int) ($boardSummaries[$boardId]['leaf_counts'][$leafId] ?? 0);
                }
            }

            $validation = SourceReadinessValidator::validate(
                $records,
                [
                    'sources' => $collections['sources'] ?? [],
                    'study_materials' => $collections['study-materials'] ?? [],
                    'native_counts' => $nativeCounts,
                    'active_leaf_ids' => $activeLeaves,
                    'board_id' => $boardId,
                    'official_areas' => self::officialAreas($collections, $boardId, $records),
                    'reviewed_at_batch' => self::reviewedAtBatch($records),
                ]
            );
            $validation['board_id'] = $boardId;
            $validation['collection'] = $collectionName;
            $validations[$collectionName] = $validation;
            foreach ((array) ($validation['errors'] ?? []) as $error) {
                if (is_array($error)) {
                    $error['board_id'] = $boardId;
                    $error['collection'] = $collectionName;
                    $allErrors[] = $error;
                }
            }
            $allRecords = array_merge($allRecords, (array) ($validation['records'] ?? $records));
        }

        return [
            'valid' => $allErrors === [],
            'errors' => $allErrors,
            'records' => $allRecords,
            'by_collection' => $validations,
            'by_board' => self::indexValidationsByBoard($validations),
        ];
    }

    /** @return array<string, array<int, array<string, mixed>>> */
    private static function readinessCollections(array $collections): array
    {
        $result = [];
        foreach ($collections as $name => $records) {
            if ($name !== 'source-readiness' && !str_starts_with((string) $name, 'source-readiness-')) {
                continue;
            }
            if (!is_array($records)) {
                continue;
            }
            $result[(string) $name] = array_values(array_filter($records, 'is_array'));
        }
        return $result;
    }

    /** @param array<int, array<string, mixed>> $records */
    private static function boardIdForCollection(string $collectionName, array $records): string
    {
        foreach ($records as $record) {
            $boardId = strtolower(trim((string) ($record['board_id'] ?? '')));
            if ($boardId !== '') {
                return $boardId;
            }
        }
        return $collectionName === 'source-readiness'
            ? 'criminologist'
            : strtolower(trim(substr($collectionName, strlen('source-readiness-'))));
    }

    /** @param array<int, array<string, mixed>> $records */
    private static function reviewedAtBatch(array $records): int
    {
        foreach ($records as $record) {
            if (is_int($record['reviewed_at_batch'] ?? null)) {
                return (int) $record['reviewed_at_batch'];
            }
        }
        return 641;
    }

    /** @param array<string, array> $collections @param array<int, array<string, mixed>> $records @return string[] */
    private static function officialAreas(array $collections, string $boardId, array $records): array
    {
        $areas = SourceReadinessValidator::officialAreasForBoard($boardId);
        foreach ((array) ($collections['blueprints'] ?? []) as $blueprint) {
            if (!is_array($blueprint) || strtolower((string) ($blueprint['board_id'] ?? $blueprint['board'] ?? '')) !== strtolower($boardId)) {
                continue;
            }
            foreach ((array) ($blueprint['sections'] ?? []) as $section) {
                if (is_array($section) && trim((string) ($section['id'] ?? '')) !== '') {
                    $areas[] = trim((string) $section['id']);
                }
            }
            foreach ((array) ($blueprint['official_areas'] ?? []) as $area) {
                if (is_array($area) && trim((string) ($area['id'] ?? '')) !== '') {
                    $areas[] = trim((string) $area['id']);
                }
            }
            foreach ((array) ($blueprint['variants'] ?? []) as $variant) {
                if (!is_array($variant)) continue;
                foreach ((array) ($variant['official_weights'] ?? []) as $component => $_weight) {
                    if (trim((string) $component) !== '') $areas[] = trim((string) $component);
                }
            }
        }
        return array_values(array_unique(array_filter(array_map('strval', $areas))));
    }

    /** @param array<string, array> $validations @return array<string, array<string, mixed>> */
    private static function indexValidationsByBoard(array $validations): array
    {
        $result = [];
        foreach ($validations as $validation) {
            $boardId = strtolower(trim((string) ($validation['board_id'] ?? '')));
            if ($boardId === '') continue;
            $result[$boardId] = $validation;
        }
        ksort($result, SORT_STRING);
        return $result;
    }

    /** @param array<int, array<string, mixed>> $records @param array<string, mixed> $validation */
    public static function summary(array $records, array $validation = []): array
    {
        $counts = ['READY' => 0, 'READY_WITH_LIMITS' => 0, 'REVIEW' => 0, 'DEFER' => 0];
        $authorities = array_fill_keys(SourceReadinessRecord::AUTHORITIES, 0);
        $coverage = array_fill_keys(SourceReadinessRecord::STUDY_COVERAGE, 0);
        $byLeaf = [];
        $headroom = ['min' => 0, 'preferred' => 0, 'max' => 0];
        $currentness = [];
        $traceability = [];
        foreach ($records as $record) {
            if (!is_array($record)) continue;
            $readiness = (string) ($record['readiness'] ?? '');
            if (isset($counts[$readiness])) $counts[$readiness]++;
            $authority = (string) ($record['source_authority'] ?? '');
            if (isset($authorities[$authority])) $authorities[$authority]++;
            $studyCoverage = (string) ($record['study_coverage'] ?? '');
            if (isset($coverage[$studyCoverage])) $coverage[$studyCoverage]++;
            $headroom['min'] += (int) ($record['safe_headroom_min'] ?? 0);
            $headroom['preferred'] += (int) ($record['safe_headroom_preferred'] ?? 0);
            $headroom['max'] += (int) ($record['safe_headroom_max'] ?? 0);
            $currentnessValue = (string) ($record['source_currentness'] ?? 'UNKNOWN');
            $currentness[$currentnessValue] = ($currentness[$currentnessValue] ?? 0) + 1;
            $traceabilityValue = (string) ($record['study_source_traceability'] ?? 'UNKNOWN');
            $traceability[$traceabilityValue] = ($traceability[$traceabilityValue] ?? 0) + 1;
            $leafId = (string) ($record['leaf_id'] ?? '');
            if ($leafId !== '') $byLeaf[$leafId] = $record;
        }
        ksort($byLeaf, SORT_STRING);
        return [
            'record_count' => count($records),
            'valid' => ($validation['valid'] ?? false) === true,
            'error_count' => count($validation['errors'] ?? []),
            'readiness_counts' => $counts,
            'source_authority_counts' => $authorities,
            'study_coverage_counts' => $coverage,
            'source_currentness_counts' => $currentness,
            'study_traceability_counts' => $traceability,
            'safe_headroom' => $headroom,
            'records' => array_values($byLeaf),
        ];
    }

    /** @param array<string, array> $collections */
    public static function validateQuestionForCreate(array $question, ?array $collections = null): array
    {
        $taxonomy = is_array($question['taxonomy'] ?? null) ? $question['taxonomy'] : [];
        $boardId = strtolower(trim((string) ($taxonomy['board_id'] ?? $question['board'] ?? '')));
        if ($boardId === '') {
            return ['valid' => true, 'errors' => []];
        }

        $collections ??= self::collections();
        $records = self::records($collections, $boardId);
        if ($records === []) {
            return ['valid' => true, 'errors' => []];
        }
        $byLeaf = [];
        foreach ($records as $record) {
            if (is_array($record)) $byLeaf[(string) ($record['leaf_id'] ?? '')] = $record;
        }
        $leafId = (string) ($taxonomy['concept_id'] ?? '');
        $record = $byLeaf[$leafId] ?? null;
        $errors = [];
        if (!is_array($record)) {
            return ['valid' => false, 'errors' => ['SOURCE_READINESS_MISSING_RECORD']];
        }
        if (!in_array((string) ($record['readiness'] ?? ''), ['READY', 'READY_WITH_LIMITS'], true)) {
            $errors[] = 'SOURCE_READINESS_NOT_AUTHORABLE';
        }
        $currentCount = self::nativeLeafCounts($collections, $boardId)[$leafId] ?? 0;
        $maxNew = (int) ($record['max_new_questions'] ?? 0);
        $recordCount = (int) ($record['current_question_count'] ?? 0);
        if ($currentCount >= $recordCount + $maxNew) {
            $errors[] = 'SOURCE_READINESS_HEADROOM_EXHAUSTED';
        }

        $depth = $question['reuse']['required_depth'] ?? $question['depth'] ?? null;
        if (!is_int($depth) || $depth < (int) ($record['supported_depth_min'] ?? 1) || $depth > (int) ($record['supported_depth_max'] ?? 5)) {
            $errors[] = 'SOURCE_READINESS_DEPTH_OUT_OF_RANGE';
        }
        $scope = $question['source_scope'] ?? $question['scope'] ?? null;
        if (!is_array($scope) || $scope === []) {
            $errors[] = 'SOURCE_READINESS_SCOPE_REQUIRED';
        } else {
            foreach ($scope as $value) {
                $value = (string) $value;
                if (!in_array($value, (array) ($record['allowed_scope'] ?? []), true) || in_array($value, (array) ($record['excluded_scope'] ?? []), true)) {
                    $errors[] = 'SOURCE_READINESS_SCOPE_OUTSIDE_BOUNDARY';
                    break;
                }
            }
        }
        $sourceIds = is_array($question['source_ids'] ?? null) ? array_map('strval', $question['source_ids']) : [];
        if ($sourceIds === []) {
            $errors[] = 'SOURCE_READINESS_SOURCE_CHAIN_REQUIRED';
        } elseif (array_diff($sourceIds, array_map('strval', (array) ($record['source_ids'] ?? []))) !== []) {
            $errors[] = 'SOURCE_READINESS_SOURCE_OUTSIDE_BOUNDARY';
        }
        $studyIds = $question['study_material_ids'] ?? null;
        if ($studyIds === null && isset($question['study_material_id'])) $studyIds = [$question['study_material_id']];
        if (!is_array($studyIds) || $studyIds === []) {
            $errors[] = 'SOURCE_READINESS_STUDY_CHAIN_REQUIRED';
        } elseif (array_diff(array_map('strval', $studyIds), array_map('strval', (array) ($record['study_material_ids'] ?? []))) !== []) {
            $errors[] = 'SOURCE_READINESS_STUDY_OUTSIDE_BOUNDARY';
        }

        return ['valid' => $errors === [], 'errors' => array_values(array_unique($errors))];
    }

    /** @param array<string, array> $collections */
    public static function nativeLeafCounts(array $collections, string $boardId): array
    {
        $counts = [];
        $structuralConcepts = [];
        $civilServiceCoreLeaves = [];
        foreach ((array) ($collections['taxonomy/concepts'] ?? []) as $concept) {
            if (!is_array($concept)) {
                continue;
            }
            $parent = trim((string) ($concept['parent_concept_id'] ?? ''));
            $conceptId = trim((string) ($concept['id'] ?? ''));
            if ($parent !== '' && $conceptId !== '') {
                $structuralConcepts[$parent] = true;
            }
        }
        foreach ((array) ($collections['board-concept-coverage'] ?? []) as $coverage) {
            if (!is_array($coverage) || strtolower((string) ($coverage['board_id'] ?? '')) !== strtolower($boardId)) continue;
            if (strtolower($boardId) === 'civil-service') {
                if (!CivilServiceCoverageModel::isCoreExamLeaf($coverage)) continue;
                $civilServiceCoreLeaves[trim((string) ($coverage['concept_id'] ?? ''))] = true;
            }
            $leafId = trim((string) ($coverage['concept_id'] ?? ''));
            if ($leafId !== '' && !isset($structuralConcepts[$leafId])) $counts[$leafId] = 0;
        }
        foreach ((array) ($collections['questions'] ?? []) as $question) {
            if (!is_array($question) || strtolower((string) ($question['taxonomy']['board_id'] ?? '')) !== strtolower($boardId)) continue;
            if (strtolower((string) ($question['status'] ?? 'active')) === 'archived') continue;
            $leafId = trim((string) ($question['taxonomy']['concept_id'] ?? ''));
            if (strtolower($boardId) === 'civil-service' && !isset($civilServiceCoreLeaves[$leafId])) continue;
            if ($leafId !== '') $counts[$leafId] = ($counts[$leafId] ?? 0) + 1;
        }
        ksort($counts, SORT_STRING);
        return $counts;
    }

    /** @return array<string, array> */
    private static function collections(): array
    {
        $names = ['questions', 'boards', 'subjects', 'board-subjects', 'blueprints', 'taxonomy/domains', 'taxonomy/topics', 'taxonomy/concepts', 'question-eligibility', 'concept-derived-eligibility', 'question-reuse-overrides', 'question-reuse-metadata', 'board-concept-coverage', 'study-materials', 'study-question-links', 'sources', 'content-fit-audits', 'flashcard-exclusions', 'source-readiness', 'source-readiness-psychometrician', 'source-readiness-mechanical-engineering', 'source-readiness-civil-service'];
        $collections = [];
        foreach ($names as $name) $collections[$name] = App::storage()->all($name);
        return $collections;
    }
}
