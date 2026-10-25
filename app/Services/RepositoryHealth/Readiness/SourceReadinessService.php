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

    /**
     * Summarize the persisted readiness contract without inferring semantic
     * claims from question wording. The optional board summary is supplied by
     * RepositoryHealthEngine so native/effective/runtime counts and structural
     * leaf handling remain owned by the existing health path.
     *
     * @param array<int, array<string, mixed>> $records
     * @param array<string, mixed> $validation
     * @param array<string, mixed>|null $boardSummary
     */
    public static function summary(array $records, array $validation = [], ?array $boardSummary = null, ?array $closure = null): array
    {
        $counts = ['READY' => 0, 'READY_WITH_LIMITS' => 0, 'REVIEW' => 0, 'DEFER' => 0];
        $authorities = array_fill_keys(SourceReadinessRecord::AUTHORITIES, 0);
        $coverage = array_fill_keys(SourceReadinessRecord::STUDY_COVERAGE, 0);
        $visualSuitability = array_fill_keys(SourceReadinessRecord::VISUAL_SUITABILITY, 0);
        $repeatRisk = array_fill_keys(SourceReadinessRecord::REPEAT_RISK, 0);
        $blockerCounts = array_fill_keys(SourceReadinessRecord::BLOCKER_CLASSES, 0);
        $byLeaf = [];
        $headroom = ['min' => 0, 'preferred' => 0, 'max' => 0];
        $currentness = [];
        $traceability = [];
        $thinRecords = [];
        $authorableRecords = [];
        $textSuitableRecords = [];
        $visualLimitedRecords = [];
        $evidenceExhaustedRecords = [];
        $headroomDimensions = ['numerical' => 0, 'evidence' => 0, 'text_format' => 0, 'high_value' => 0];
        $hasHeadroomDimensions = false;
        $evidenceExhaustedCount = 0;
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
            $visualValue = (string) ($record['visual_suitability'] ?? 'UNKNOWN');
            if (isset($visualSuitability[$visualValue])) $visualSuitability[$visualValue]++;
            $repeatValue = (string) ($record['repeat_risk'] ?? 'UNKNOWN');
            if (isset($repeatRisk[$repeatValue])) $repeatRisk[$repeatValue]++;
            foreach (self::blockerClasses($record) as $blocker) {
                $blockerCounts[$blocker] = ($blockerCounts[$blocker] ?? 0) + 1;
            }
            if (array_key_exists('evidence_exhausted', $record)) {
                $evidenceExhaustedRecords[] = $record;
                if ($record['evidence_exhausted'] === true) $evidenceExhaustedCount++;
            }
            foreach ([
                'numerical' => 'numerical_headroom',
                'evidence' => 'evidence_headroom',
                'text_format' => 'text_format_headroom',
                'high_value' => 'high_value_headroom',
            ] as $dimension => $field) {
                if (array_key_exists($field, $record) && is_int($record[$field])) {
                    $hasHeadroomDimensions = true;
                    $headroomDimensions[$dimension] += $record[$field];
                }
            }
            $leafId = (string) ($record['leaf_id'] ?? '');
            if ($leafId !== '') {
                $byLeaf[$leafId] = $record;
                $thinRecords[] = $record;
                if (in_array($readiness, ['READY', 'READY_WITH_LIMITS'], true) && (int) ($record['max_new_questions'] ?? 0) > 0) {
                    $authorableRecords[] = $record;
                }
                if ($visualValue === 'TEXT_SUITABLE') $textSuitableRecords[] = $record;
                if (in_array($visualValue, ['VISUAL_PREFERRED', 'VISUAL_REQUIRED', 'MIXED'], true)) $visualLimitedRecords[] = $record;
            }
        }
        ksort($byLeaf, SORT_STRING);
        $rawThinness = self::thinness($thinRecords);
        $authorableThinness = self::thinness($authorableRecords);
        $textSuitableThinness = self::thinness($textSuitableRecords);
        $visualLimitedThinness = self::thinness($visualLimitedRecords);
        $evidenceExhaustedThinness = self::thinness(array_values(array_filter(
            $evidenceExhaustedRecords,
            static fn(array $record): bool => ($record['evidence_exhausted'] ?? false) === true
        )));
        if (is_array($boardSummary)) {
            $rawThinness = [
                'zero' => (int) ($boardSummary['zero'] ?? $rawThinness['zero']),
                'under5' => (int) ($boardSummary['under5'] ?? $rawThinness['under5']),
                'under10' => (int) ($boardSummary['under10'] ?? $rawThinness['under10']),
                'under20' => (int) ($boardSummary['under20'] ?? $rawThinness['under20']),
            ];
        }
        foreach ([$currentness, $traceability, $blockerCounts] as &$map) ksort($map, SORT_STRING);
        unset($map);
        $sourceStudyDefects = self::defectCounts($records, $validation);
        $sourceStudyReviews = self::reviewCounts($records);
        $currentnessValues = array_keys(array_filter($currentness, static fn(int $count): bool => $count > 0));
        $currentnessStatus = $currentnessValues === [] ? null : (count($currentnessValues) === 1 ? $currentnessValues[0] : 'MIXED');
        $boardMetrics = null;
        if (is_array($boardSummary)) {
            $boardMetrics = [
                'native' => isset($boardSummary['canonical']) ? (int) $boardSummary['canonical'] : null,
                'effective' => isset($boardSummary['effective_declared']) ? (int) $boardSummary['effective_declared'] : null,
                'runtime' => isset($boardSummary['runtime']) ? (int) $boardSummary['runtime'] : null,
                'active_leaves' => isset($boardSummary['active_leaves']) ? (int) $boardSummary['active_leaves'] : null,
            ];
        }
        $hasAnyVisualMetadata = array_sum($visualSuitability) - $visualSuitability['UNKNOWN'] > 0;
        $hasAnyRepeatMetadata = array_sum($repeatRisk) - $repeatRisk['UNKNOWN'] > 0;
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
            'board_counts' => $boardMetrics,
            'raw_thinness' => $rawThinness,
            'authorable_thinness' => $authorableThinness,
            'text_suitable_thinness' => $textSuitableThinness,
            'visual_limited_thinness' => $visualLimitedThinness,
            'evidence_exhausted_thinness' => $evidenceExhaustedThinness,
            'visual_suitability_counts' => $visualSuitability,
            'visual_suitability_metadata' => $hasAnyVisualMetadata,
            'repeat_risk_counts' => $repeatRisk,
            'repeat_risk_metadata' => $hasAnyRepeatMetadata,
            'evidence_exhausted_count' => $evidenceExhaustedCount,
            'headroom_dimensions' => $hasHeadroomDimensions ? $headroomDimensions : null,
            'high_value_headroom' => $hasHeadroomDimensions ? $headroomDimensions['high_value'] : (is_int($closure['high_value_headroom'] ?? null) ? (int) $closure['high_value_headroom'] : null),
            'source_study_defects' => $sourceStudyDefects,
            'source_study_reviews' => $sourceStudyReviews,
            'currentness_status' => $currentnessStatus,
            'blocker_counts' => $blockerCounts,
            'review_blocker_counts' => $blockerCounts,
            'records' => array_values($byLeaf),
        ] + self::closureFields($closure);
    }

    /** @param array<int, array<string, mixed>> $records @return array{zero:int,under5:int,under10:int,under20:int} */
    private static function thinness(array $records): array
    {
        $counts = [];
        foreach ($records as $record) {
            if (is_array($record) && array_key_exists('current_question_count', $record)) {
                $counts[] = (int) $record['current_question_count'];
            }
        }
        return [
            'zero' => count(array_filter($counts, static fn(int $count): bool => $count === 0)),
            'under5' => count(array_filter($counts, static fn(int $count): bool => $count < 5)),
            'under10' => count(array_filter($counts, static fn(int $count): bool => $count < 10)),
            'under20' => count(array_filter($counts, static fn(int $count): bool => $count < 20)),
        ];
    }

    /** @param array<string, mixed> $record @return string[] */
    private static function blockerClasses(array $record): array
    {
        $found = [];
        $walk = static function (mixed $value) use (&$walk, &$found): void {
            if (!is_array($value)) return;
            foreach ($value as $key => $child) {
                if (in_array((string) $key, ['blocker_classes', 'blocker_categories', 'blockers'], true)) {
                    foreach ((array) $child as $candidate) {
                        $candidate = strtoupper(trim((string) $candidate));
                        if (in_array($candidate, SourceReadinessRecord::BLOCKER_CLASSES, true)) $found[$candidate] = true;
                        elseif ($candidate !== '') $found['OTHER_EXPLICIT'] = true;
                    }
                } else {
                    $walk($child);
                }
            }
        };
        $walk($record);
        $classes = array_keys($found);
        sort($classes, SORT_STRING);
        return $classes;
    }

    /** @param array<int, array<string, mixed>> $records @param array<string, mixed> $validation */
    private static function defectCounts(array $records, array $validation): array
    {
        $counts = ['source' => 0, 'study' => 0, 'traceability' => 0, 'currentness' => 0];
        foreach ($records as $record) {
            if (!is_array($record)) continue;
            if (self::isStructuralException($record)) continue;
            // A REVIEW/DEFER leaf with zero authorization can carry an
            // intentional evidence limitation. It is a review signal, not a
            // broken production chain. Validator errors below remain hard.
            $actionable = in_array((string) ($record['readiness'] ?? ''), ['READY', 'READY_WITH_LIMITS'], true)
                || (int) ($record['max_new_questions'] ?? 0) > 0;
            if ($actionable && (in_array((string) ($record['source_authority'] ?? ''), ['WEAK', 'NONE', 'UNKNOWN'], true) || (string) ($record['source_scope_fit'] ?? '') === 'NONE')) $counts['source']++;
            if ($actionable && in_array((string) ($record['study_coverage'] ?? ''), ['MISSING', 'INVALID', 'UNKNOWN'], true)) $counts['study']++;
            if ($actionable && in_array((string) ($record['study_source_traceability'] ?? ''), ['BROKEN', 'NONE', 'UNKNOWN'], true)) $counts['traceability']++;
            if ($actionable && in_array((string) ($record['source_currentness'] ?? ''), ['SUPERSEDED', 'FUTURE_EFFECTIVE', 'CONFLICTING', 'UNKNOWN'], true)) $counts['currentness']++;
        }
        foreach ((array) ($validation['errors'] ?? []) as $error) {
            if (!is_array($error)) continue;
            $code = (string) ($error['code'] ?? '');
            if (str_contains($code, 'INVALID_SOURCE')) $counts['source']++;
            if (str_contains($code, 'INVALID_STUDY')) $counts['study']++;
            if (str_contains($code, 'TRACEABILITY')) $counts['traceability']++;
        }
        return $counts;
    }

    /** @param array<int, array<string, mixed>> $records @return array<string, int> */
    private static function reviewCounts(array $records): array
    {
        $counts = [
            'source_scope_fit_unknown' => 0,
            'source_authority_unknown' => 0,
            'study_coverage_unknown' => 0,
            'traceability_unknown' => 0,
            'currentness_unknown' => 0,
            'structural_not_actionable' => 0,
        ];
        foreach ($records as $record) {
            if (!is_array($record)) continue;
            if (self::isStructuralException($record)) {
                if (($record['scope_crosswalk_status'] ?? '') === 'VERIFIED_PARENT_SCOPE') continue;
                $counts['structural_not_actionable']++;
                continue;
            }
            if (($record['source_scope_fit'] ?? '') === 'UNKNOWN') $counts['source_scope_fit_unknown']++;
            if (($record['source_authority'] ?? '') === 'UNKNOWN') $counts['source_authority_unknown']++;
            if (($record['study_coverage'] ?? '') === 'UNKNOWN') $counts['study_coverage_unknown']++;
            if (($record['study_source_traceability'] ?? '') === 'UNKNOWN') $counts['traceability_unknown']++;
            if (($record['source_currentness'] ?? '') === 'UNKNOWN') $counts['currentness_unknown']++;
        }
        return $counts;
    }

    /** @param array<string, mixed> $record */
    private static function isStructuralException(array $record): bool
    {
        return ($record['scope_status'] ?? '') === 'STRUCTURAL_NOT_ACTIONABLE';
    }

    /** @return array<string, mixed> */
    private static function closureFields(?array $closure): array
    {
        if (!is_array($closure)) return [];
        $fields = [];
        foreach (['board_id', 'closure_state', 'operational_state', 'closure_reason', 'evidence_exhausted', 'numerical_headroom', 'evidence_headroom', 'text_format_headroom', 'high_value_headroom', 'reviewed_at_batch'] as $field) {
            if (array_key_exists($field, $closure)) $fields[$field] = $closure[$field];
        }
        // Keep the compact object for API consumers while also exposing the
        // normalized fields at the summary level used by the indexed
        // portfolio projection. Both views are derived from the same record.
        return $fields === [] ? [] : array_merge(['closure' => $fields], $fields);
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
            $batchMetadata = is_array($concept['batch633'] ?? null) ? $concept['batch633'] : [];
            $parent = trim((string) ($concept['parent_concept_id'] ?? $batchMetadata['parent_concept_id'] ?? ''));
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
        $names = ['questions', 'boards', 'subjects', 'board-subjects', 'blueprints', 'taxonomy/domains', 'taxonomy/topics', 'taxonomy/concepts', 'question-eligibility', 'concept-derived-eligibility', 'question-reuse-overrides', 'question-reuse-metadata', 'board-concept-coverage', 'study-materials', 'study-question-links', 'sources', 'content-fit-audits', 'flashcard-exclusions', 'source-readiness', 'source-readiness-psychometrician', 'source-readiness-mechanical-engineering', 'source-readiness-civil-service', 'source-readiness-architecture'];
        $collections = [];
        foreach ($names as $name) $collections[$name] = App::storage()->all($name);
        return $collections;
    }
}
