<?php

declare(strict_types=1);

namespace App\Services\RepositoryHealth\Readiness;

/**
 * Deterministic validator for the per-leaf readiness contract.
 * It validates structure and cross-field invariants only; editorial
 * headroom judgments remain evidence supplied by the Batch artifact.
 */
final class SourceReadinessValidator
{
    public const BOARD_OFFICIAL_AREAS = [
        'criminologist' => [
            'criminal_jurisprudence_and_procedure',
            'law_enforcement_administration',
            'crime_detection_and_investigation',
            'forensic_science',
            'criminology',
            'correctional_administration',
            'shared_general_foundation',
        ],
        'psychometrician' => [
            'psychological-assessment',
            'developmental-psychology',
            'abnormal-psychology',
            'industrial-organizational',
        ],
        'civil-service' => [
            'general-information',
            'verbal-ability',
            'numerical-ability',
            'analytical-ability',
            'clerical-ability',
        ],
    ];

    /** @var string[] Backward-compatible Criminologist area list. */
    public const OFFICIAL_AREAS = [
        'criminal_jurisprudence_and_procedure',
        'law_enforcement_administration',
        'crime_detection_and_investigation',
        'forensic_science',
        'criminology',
        'correctional_administration',
        'shared_general_foundation',
    ];

    /** @return string[] */
    public static function officialAreasForBoard(string $boardId): array
    {
        $boardId = strtolower(trim($boardId));
        return array_values(self::BOARD_OFFICIAL_AREAS[$boardId] ?? []);
    }

    /** @return array{valid: bool, errors: array<int, array<string, mixed>>, records: array<int, array<string, mixed>>} */
    public static function validate(array $records, array $context = []): array
    {
        $errors = [];
        $normalized = [];
        $seen = [];
        $sources = self::index($context['sources'] ?? []);
        $materials = self::index($context['study_materials'] ?? []);
        $boardId = strtolower(trim((string) ($context['board_id'] ?? 'criminologist')));
        $officialAreas = array_values(array_unique(array_map('strval', (array) ($context['official_areas'] ?? self::officialAreasForBoard($boardId)))));
        $expectedBatch = (int) ($context['reviewed_at_batch'] ?? 641);
        $nativeCounts = is_array($context['native_counts'] ?? null) ? $context['native_counts'] : [];
        $activeLeaves = array_values(array_unique(array_map('strval', (array) ($context['active_leaf_ids'] ?? []))));
        sort($activeLeaves, SORT_STRING);

        foreach ($records as $index => $rawRecord) {
            if (!is_array($rawRecord)) {
                self::error($errors, 'SOURCE_READINESS.INVALID_RECORD', '', 'Readiness record must be an object.', ['index' => $index]);
                continue;
            }

            self::validateRawTypeShape($rawRecord, $errors, (string) ($rawRecord['leaf_id'] ?? ''));
            self::validateRequiredFields($rawRecord, $errors, (string) ($rawRecord['leaf_id'] ?? ''));
            $record = SourceReadinessRecord::normalize($rawRecord);
            $leafId = trim((string) ($record['leaf_id'] ?? ''));
            if ($leafId !== '') {
                if (isset($seen[$leafId])) {
                    self::error($errors, 'SOURCE_READINESS.DUPLICATE_RECORD', $leafId, 'More than one readiness record targets the same active leaf.', ['first_index' => $seen[$leafId], 'duplicate_index' => $index]);
                }
                $seen[$leafId] = $index;
            }
            $normalized[] = $record;
            self::validateRecord($record, $errors, [
                'sources' => $sources,
                'materials' => $materials,
                'native_counts' => $nativeCounts,
                'active_leaf_ids' => $activeLeaves,
                'board_id' => $boardId,
                'official_areas' => $officialAreas,
                'reviewed_at_batch' => $expectedBatch,
            ]);
        }

        foreach ($activeLeaves as $leafId) {
            if (!isset($seen[$leafId])) {
                self::error($errors, 'SOURCE_READINESS.MISSING_ACTIVE_LEAF', $leafId, 'An active examinable leaf has no source-readiness record.', ['board_id' => $boardId, 'leaf_id' => $leafId]);
            }
        }

        return [
            'valid' => $errors === [],
            'errors' => $errors,
            'records' => $normalized,
        ];
    }

    /** @param array<string, mixed> $record @param array<int, array<string, mixed>> $errors @param array<string, mixed> $context */
    private static function validateRecord(array $record, array &$errors, array $context): void
    {
        $sources = is_array($context['sources'] ?? null) ? $context['sources'] : [];
        $materials = is_array($context['materials'] ?? null) ? $context['materials'] : [];
        $leafId = trim((string) ($record['leaf_id'] ?? ''));
        $boardId = trim((string) ($record['board_id'] ?? ''));
        $expectedBoardId = strtolower(trim((string) ($context['board_id'] ?? 'criminologist')));
        if (strtolower($boardId) !== $expectedBoardId) {
            self::error($errors, 'SOURCE_READINESS.INVALID_RECORD', $leafId, 'Readiness record targets a different board than its canonical collection.', ['expected_board_id' => $expectedBoardId, 'board_id' => $boardId]);
        }
        if ($leafId === '' || ($context['active_leaf_ids'] !== [] && !in_array($leafId, $context['active_leaf_ids'], true))) {
            self::error($errors, 'SOURCE_READINESS.INVALID_LEAF', $leafId, 'LEAF_ID does not resolve to exactly one active examinable leaf.', ['board_id' => $expectedBoardId, 'leaf_id' => $leafId]);
        }
        $officialAreas = array_values(array_map('strval', (array) ($context['official_areas'] ?? self::officialAreasForBoard($expectedBoardId))));
        if (!in_array((string) ($record['official_area_id'] ?? ''), $officialAreas, true)) {
            self::error($errors, 'SOURCE_READINESS.INVALID_OFFICIAL_AREA', $leafId, 'OFFICIAL_AREA_ID does not resolve to a supported current scope area.', ['official_area_id' => $record['official_area_id'] ?? null]);
        }
        if (!is_string($record['leaf_name'] ?? null) || trim($record['leaf_name']) === '') {
            self::error($errors, 'SOURCE_READINESS.INVALID_RECORD', $leafId, 'LEAF_NAME must be a non-empty string.', []);
        }

        foreach (['source_authority' => SourceReadinessRecord::AUTHORITIES, 'source_currentness' => SourceReadinessRecord::CURRENTNESS, 'source_scope_fit' => SourceReadinessRecord::SCOPE_FIT, 'source_conflict_status' => SourceReadinessRecord::CONFLICTS, 'study_coverage' => SourceReadinessRecord::STUDY_COVERAGE, 'study_source_traceability' => SourceReadinessRecord::TRACEABILITY, 'readiness' => SourceReadinessRecord::READINESS] as $field => $allowed) {
            if (!is_string($record[$field] ?? null) || !in_array($record[$field], $allowed, true)) {
                self::error($errors, 'SOURCE_READINESS.INVALID_ENUM', $leafId, "{$field} has an unsupported value.", ['field' => $field, 'value' => $record[$field] ?? null]);
            }
        }

        self::validateUniqueStringList($record, 'source_ids', $leafId, $errors);
        self::validateUniqueStringList($record, 'study_material_ids', $leafId, $errors);
        foreach (['distinct_content_families', 'allowed_scope', 'excluded_scope', 'limitations'] as $field) {
            self::validateStringList($record, $field, $leafId, $errors);
        }
        foreach (['scope_specialization_ids', 'scope_component_ids'] as $field) {
            if (array_key_exists($field, $record)) self::validateUniqueStringList($record, $field, $leafId, $errors);
        }

        $sourceIds = is_array($record['source_ids'] ?? null) ? array_map('strval', $record['source_ids']) : [];
        $materialIds = is_array($record['study_material_ids'] ?? null) ? array_map('strval', $record['study_material_ids']) : [];
        self::validateInteger($record, 'source_count', $leafId, $errors, 0);
        self::validateInteger($record, 'primary_source_count', $leafId, $errors, 0);
        self::validateInteger($record, 'study_material_count', $leafId, $errors, 0);
        if ((int) ($record['source_count'] ?? -1) !== count($sourceIds)) {
            self::error($errors, 'SOURCE_READINESS.INVALID_RECORD', $leafId, 'SOURCE_COUNT does not equal the unique SOURCE_IDS count.', ['source_count' => $record['source_count'] ?? null, 'actual' => count($sourceIds)]);
        }
        if ((int) ($record['primary_source_count'] ?? -1) > count($sourceIds)) {
            self::error($errors, 'SOURCE_READINESS.INVALID_RECORD', $leafId, 'PRIMARY_SOURCE_COUNT cannot exceed SOURCE_COUNT.', []);
        }
        if ((int) ($record['study_material_count'] ?? -1) !== count($materialIds)) {
            self::error($errors, 'SOURCE_READINESS.INVALID_RECORD', $leafId, 'STUDY_MATERIAL_COUNT does not equal the unique STUDY_MATERIAL_IDS count.', ['study_material_count' => $record['study_material_count'] ?? null, 'actual' => count($materialIds)]);
        }
        foreach ($sourceIds as $sourceId) {
            if (!isset($sources[$sourceId])) {
                self::error($errors, 'SOURCE_READINESS.INVALID_SOURCE', $leafId, 'SOURCE_IDS contains an unresolved source record.', ['source_id' => $sourceId]);
            }
        }
        foreach ($materialIds as $materialId) {
            if (!isset($materials[$materialId])) {
                self::error($errors, 'SOURCE_READINESS.INVALID_STUDY', $leafId, 'STUDY_MATERIAL_IDS contains an unresolved study material.', ['study_material_id' => $materialId]);
                continue;
            }
            $material = $materials[$materialId];
            foreach ((array) ($material['source_ids'] ?? []) as $sourceId) {
                if (!isset($sources[(string) $sourceId])) {
                    self::error($errors, 'SOURCE_READINESS.TRACEABILITY_BROKEN', $leafId, 'A referenced study material has a broken source chain.', ['study_material_id' => $materialId, 'source_id' => $sourceId]);
                }
            }
        }

        $currentCount = self::integerValue($record, 'current_question_count', $leafId, $errors, 0);
        foreach (['deficit_to_5' => 5, 'deficit_to_10' => 10, 'deficit_to_20' => 20] as $field => $threshold) {
            $expected = max(0, $threshold - $currentCount);
            $actual = self::integerValue($record, $field, $leafId, $errors, 0);
            if ($actual !== $expected) {
                self::error($errors, 'SOURCE_READINESS.COUNT_CONTRADICTION', $leafId, strtoupper($field) . ' does not match the recomputed threshold deficit.', ['field' => $field, 'expected' => $expected, 'actual' => $actual, 'current_question_count' => $currentCount]);
            }
        }
        if (isset($context['native_counts'][$leafId]) && $currentCount !== (int) $context['native_counts'][$leafId]) {
            self::error($errors, 'SOURCE_READINESS.COUNT_CONTRADICTION', $leafId, 'CURRENT_QUESTION_COUNT does not match native canonical placement.', ['expected' => (int) $context['native_counts'][$leafId], 'actual' => $currentCount]);
        }

        $minDepth = self::integerValue($record, 'supported_depth_min', $leafId, $errors, 1);
        $maxDepth = self::integerValue($record, 'supported_depth_max', $leafId, $errors, 1);
        if ($minDepth < 1 || $minDepth > 5 || $maxDepth < 1 || $maxDepth > 5 || $minDepth > $maxDepth) {
            self::error($errors, 'SOURCE_READINESS.DEPTH_CONTRADICTION', $leafId, 'Supported depth bounds are outside the established 1–5 scale.', ['min' => $minDepth, 'max' => $maxDepth]);
        }
        $familyCount = self::integerValue($record, 'distinct_content_family_count', $leafId, $errors, 0);
        $families = is_array($record['distinct_content_families'] ?? null) ? array_values(array_unique(array_map('strval', $record['distinct_content_families']))) : [];
        if ($familyCount !== count($families)) {
            self::error($errors, 'SOURCE_READINESS.INVALID_RECORD', $leafId, 'DISTINCT_CONTENT_FAMILY_COUNT does not match the unique family list.', ['expected' => count($families), 'actual' => $familyCount]);
        }

        $headroom = [];
        foreach (['safe_headroom_min', 'safe_headroom_preferred', 'safe_headroom_max'] as $field) {
            $headroom[$field] = self::integerValue($record, $field, $leafId, $errors, 0);
        }
        if ($headroom['safe_headroom_min'] < 0 || $headroom['safe_headroom_min'] > $headroom['safe_headroom_preferred'] || $headroom['safe_headroom_preferred'] > $headroom['safe_headroom_max']) {
            self::error($errors, 'SOURCE_READINESS.HEADROOM_CONTRADICTION', $leafId, 'Safe headroom bounds are not monotonic.', $headroom);
        }
        $maxNew = self::integerValue($record, 'max_new_questions', $leafId, $errors, 0);
        $planned = self::integerValue($record, 'planned_new_questions', $leafId, $errors, 0);
        if ($maxNew > $headroom['safe_headroom_max']) {
            self::error($errors, 'SOURCE_READINESS.PLANNING_OVERFLOW', $leafId, 'MAX_NEW_QUESTIONS exceeds the evidence-bounded maximum headroom.', ['max_new_questions' => $maxNew, 'safe_headroom_max' => $headroom['safe_headroom_max']]);
        }
        if ($planned < 0 || $planned > $maxNew) {
            self::error($errors, 'SOURCE_READINESS.PLANNING_OVERFLOW', $leafId, 'PLANNED_NEW_QUESTIONS exceeds MAX_NEW_QUESTIONS.', ['planned_new_questions' => $planned, 'max_new_questions' => $maxNew]);
        }

        $readiness = (string) ($record['readiness'] ?? '');
        $authority = (string) ($record['source_authority'] ?? '');
        $currentness = (string) ($record['source_currentness'] ?? '');
        $scopeFit = (string) ($record['source_scope_fit'] ?? '');
        $conflict = (string) ($record['source_conflict_status'] ?? '');
        $coverage = (string) ($record['study_coverage'] ?? '');
        $traceability = (string) ($record['study_source_traceability'] ?? '');
        if ($readiness === 'READY') {
            if (!in_array($authority, ['STRONG', 'ADEQUATE'], true) || !in_array($currentness, ['CURRENT', 'NOT_TIME_SENSITIVE'], true) || !in_array($scopeFit, ['DIRECT', 'STRONG'], true) || !in_array($conflict, ['NONE', 'RESOLVED'], true) || !in_array($coverage, ['COMPLETE', 'SUFFICIENT'], true) || $traceability !== 'COMPLETE' || $maxNew <= 0) {
                self::error($errors, 'SOURCE_READINESS.CONTRADICTION', $leafId, 'READY record does not satisfy the full authoring contract.', ['authority' => $authority, 'currentness' => $currentness, 'scope_fit' => $scopeFit, 'conflict' => $conflict, 'coverage' => $coverage, 'traceability' => $traceability, 'max_new_questions' => $maxNew]);
            }
        } elseif ($readiness === 'READY_WITH_LIMITS') {
            if (!in_array($authority, ['STRONG', 'ADEQUATE'], true) || !in_array($currentness, ['CURRENT', 'CURRENT_WITH_LIMITS', 'NOT_TIME_SENSITIVE'], true) || !in_array($scopeFit, ['DIRECT', 'STRONG', 'PARTIAL'], true) || !in_array($conflict, ['NONE', 'RESOLVED'], true) || !in_array($coverage, ['COMPLETE', 'SUFFICIENT', 'PARTIAL'], true) || $traceability !== 'COMPLETE' || $maxNew <= 0 || ($record['limitations'] ?? []) === [] || ($record['allowed_scope'] ?? []) === []) {
                self::error($errors, 'SOURCE_READINESS.CONTRADICTION', $leafId, 'READY_WITH_LIMITS record lacks a bounded, traceable authoring scope.', ['authority' => $authority, 'currentness' => $currentness, 'scope_fit' => $scopeFit, 'coverage' => $coverage, 'traceability' => $traceability, 'limitations' => $record['limitations'] ?? [], 'allowed_scope' => $record['allowed_scope'] ?? []]);
            }
        } elseif ($readiness === 'REVIEW') {
            if (trim((string) ($record['review_reason'] ?? '')) === '' || $maxNew !== 0 || $planned !== 0) {
                self::error($errors, 'SOURCE_READINESS.CONTRADICTION', $leafId, 'REVIEW records require a reason and no automatic authoring plan.', []);
            }
        } elseif ($readiness === 'DEFER') {
            if (trim((string) ($record['defer_reason'] ?? '')) === '' || $maxNew !== 0 || $planned !== 0) {
                self::error($errors, 'SOURCE_READINESS.CONTRADICTION', $leafId, 'DEFER records require a reason and no automatic authoring plan.', []);
            }
        }

        if (($record['readiness'] ?? '') !== 'REVIEW' && trim((string) ($record['review_reason'] ?? '')) !== '') {
            self::error($errors, 'SOURCE_READINESS.CONTRADICTION', $leafId, 'REVIEW_REASON must be empty outside REVIEW.', []);
        }
        if (($record['readiness'] ?? '') !== 'DEFER' && trim((string) ($record['defer_reason'] ?? '')) !== '') {
            self::error($errors, 'SOURCE_READINESS.CONTRADICTION', $leafId, 'DEFER_REASON must be empty outside DEFER.', []);
        }
        $expectedBatch = (int) ($context['reviewed_at_batch'] ?? 641);
        if ((int) ($record['reviewed_at_batch'] ?? 0) !== $expectedBatch) {
            self::error($errors, 'SOURCE_READINESS.INVALID_RECORD', $leafId, 'REVIEWED_AT_BATCH does not match the readiness collection contract.', ['expected_batch' => $expectedBatch, 'reviewed_at_batch' => $record['reviewed_at_batch'] ?? null]);
        }
        $hash = trim((string) ($record['evidence_hash'] ?? ''));
        if ($hash === '' || !hash_equals(SourceReadinessRecord::evidenceHash($record), $hash)) {
            self::error($errors, 'SOURCE_READINESS.INVALID_EVIDENCE_HASH', $leafId, 'EVIDENCE_HASH does not match the normalized readiness evidence.', []);
        }
    }

    /** @param array<string, mixed> $record @param array<int, array<string, mixed>> $errors */
    private static function validateRequiredFields(array $record, array &$errors, string $leafId): void
    {
        foreach ([
            'leaf_id', 'board_id', 'official_area_id', 'leaf_name',
            'current_question_count', 'deficit_to_5', 'deficit_to_10', 'deficit_to_20',
            'source_ids', 'source_count', 'primary_source_count', 'source_authority',
            'source_currentness', 'source_scope_fit', 'source_conflict_status',
            'study_material_ids', 'study_material_count', 'study_coverage',
            'study_source_traceability', 'supported_depth_min', 'supported_depth_max',
            'distinct_content_families', 'distinct_content_family_count',
            'safe_headroom_min', 'safe_headroom_preferred', 'safe_headroom_max',
            'allowed_scope', 'excluded_scope', 'limitations', 'readiness',
            'readiness_reason', 'review_reason', 'defer_reason',
            'max_new_questions', 'planned_new_questions', 'evidence_hash', 'reviewed_at_batch',
        ] as $field) {
            if (!array_key_exists($field, $record)) {
                self::error($errors, 'SOURCE_READINESS.INVALID_RECORD', $leafId, "Readiness record is missing {$field}.", ['field' => $field]);
            }
        }
    }

    private static function validateUniqueStringList(array $record, string $field, string $leafId, array &$errors): void
    {
        self::validateStringList($record, $field, $leafId, $errors);
        $values = is_array($record[$field] ?? null) ? array_map('strval', $record[$field]) : [];
        if (count($values) !== count(array_unique($values))) {
            self::error($errors, 'SOURCE_READINESS.INVALID_RECORD', $leafId, strtoupper($field) . ' contains duplicate IDs.', ['field' => $field]);
        }
    }

    /** @param array<string, mixed> $record @param array<int, array<string, mixed>> $errors */
    private static function validateRawTypeShape(array $record, array &$errors, string $leafId): void
    {
        foreach (['source_ids', 'study_material_ids', 'distinct_content_families', 'allowed_scope', 'excluded_scope', 'limitations', 'scope_specialization_ids', 'scope_component_ids'] as $field) {
            if (in_array($field, ['scope_specialization_ids', 'scope_component_ids'], true) && !array_key_exists($field, $record)) continue;
            if (array_key_exists($field, $record) && (!is_array($record[$field]) || array_is_list($record[$field]) === false)) {
                self::error($errors, 'SOURCE_READINESS.INVALID_RECORD', $leafId, strtoupper($field) . ' must be a JSON array.', ['field' => $field]);
                continue;
            }
            foreach ((array) ($record[$field] ?? []) as $value) {
                if (!is_string($value)) {
                    self::error($errors, 'SOURCE_READINESS.INVALID_RECORD', $leafId, strtoupper($field) . ' must contain strings.', ['field' => $field]);
                    break;
                }
            }
        }
        foreach (['current_question_count', 'deficit_to_5', 'deficit_to_10', 'deficit_to_20', 'source_count', 'primary_source_count', 'study_material_count', 'supported_depth_min', 'supported_depth_max', 'distinct_content_family_count', 'safe_headroom_min', 'safe_headroom_preferred', 'safe_headroom_max', 'max_new_questions', 'planned_new_questions', 'reviewed_at_batch'] as $field) {
            if (array_key_exists($field, $record) && !is_int($record[$field])) {
                self::error($errors, 'SOURCE_READINESS.INVALID_RECORD', $leafId, strtoupper($field) . ' must be an integer.', ['field' => $field]);
            }
        }
        foreach (['leaf_id', 'board_id', 'official_area_id', 'leaf_name', 'source_authority', 'source_currentness', 'source_scope_fit', 'source_conflict_status', 'study_coverage', 'study_source_traceability', 'readiness', 'readiness_reason', 'review_reason', 'defer_reason', 'evidence_hash'] as $field) {
            if (array_key_exists($field, $record) && !is_string($record[$field])) {
                self::error($errors, 'SOURCE_READINESS.INVALID_RECORD', $leafId, strtoupper($field) . ' must be a string.', ['field' => $field]);
            }
        }
    }

    private static function validateStringList(array $record, string $field, string $leafId, array &$errors): void
    {
        if (!is_array($record[$field] ?? null) || array_is_list($record[$field]) === false) {
            self::error($errors, 'SOURCE_READINESS.INVALID_RECORD', $leafId, strtoupper($field) . ' must be an array.', ['field' => $field]);
            return;
        }
        foreach ($record[$field] as $value) {
            if (!is_string($value) || trim($value) === '') {
                self::error($errors, 'SOURCE_READINESS.INVALID_RECORD', $leafId, strtoupper($field) . ' must contain non-empty strings.', ['field' => $field]);
                break;
            }
        }
    }

    private static function validateInteger(array $record, string $field, string $leafId, array &$errors, int $minimum): void
    {
        if (!is_int($record[$field] ?? null) || $record[$field] < $minimum) {
            self::error($errors, 'SOURCE_READINESS.INVALID_RECORD', $leafId, strtoupper($field) . ' must be an integer within the supported range.', ['field' => $field, 'value' => $record[$field] ?? null]);
        }
    }

    private static function integerValue(array $record, string $field, string $leafId, array &$errors, int $minimum): int
    {
        self::validateInteger($record, $field, $leafId, $errors, $minimum);
        return is_int($record[$field] ?? null) ? $record[$field] : $minimum;
    }

    /** @return array<string, array<string, mixed>> */
    private static function index(array $records): array
    {
        $indexed = [];
        foreach ($records as $record) {
            if (is_array($record) && is_scalar($record['id'] ?? null)) {
                $indexed[(string) $record['id']] = $record;
            }
        }
        return $indexed;
    }

    /** @param array<int, array<string, mixed>> $errors */
    private static function error(array &$errors, string $code, string $leafId, string $message, array $evidence = []): void
    {
        $errors[] = [
            'code' => $code,
            'leaf_id' => $leafId,
            'message' => $message,
            'evidence' => $evidence,
        ];
    }
}
