<?php

declare(strict_types=1);

namespace App\Services\RepositoryHealth\Readiness;

/**
 * Shared normalization and evidence-hash rules for per-leaf source readiness.
 * Editorial classifications live in the persisted readiness records; this
 * class only defines the stable machine contract used by health and authoring.
 */
final class SourceReadinessRecord
{
    public const AUTHORITIES = ['STRONG', 'ADEQUATE', 'WEAK', 'NONE', 'UNKNOWN'];
    public const CURRENTNESS = ['CURRENT', 'CURRENT_WITH_LIMITS', 'SUPERSEDED', 'FUTURE_EFFECTIVE', 'CONFLICTING', 'UNKNOWN', 'NOT_TIME_SENSITIVE'];
    public const SCOPE_FIT = ['DIRECT', 'STRONG', 'PARTIAL', 'INDIRECT', 'NONE', 'UNKNOWN'];
    public const CONFLICTS = ['NONE', 'RESOLVED', 'MATERIAL_UNRESOLVED', 'UNKNOWN'];
    public const STUDY_COVERAGE = ['COMPLETE', 'SUFFICIENT', 'PARTIAL', 'MISSING', 'INVALID', 'UNKNOWN'];
    public const TRACEABILITY = ['COMPLETE', 'PARTIAL', 'BROKEN', 'NONE', 'UNKNOWN'];
    public const READINESS = ['READY', 'READY_WITH_LIMITS', 'REVIEW', 'DEFER'];
    public const CLOSURE_STATES = ['OPEN', 'FOUNDATION_SUFFICIENT', 'EVIDENCE_EXHAUSTED', 'VISUAL_LIMITED', 'CURRENTNESS_LIMITED'];
    public const SCOPE_STATUSES = ['ACTIONABLE', 'STRUCTURAL_NOT_ACTIONABLE'];
    public const SCOPE_CROSSWALK_STATUSES = ['VERIFIED_PARENT_SCOPE'];
    public const VISUAL_SUITABILITY = ['TEXT_SUITABLE', 'TEXT_SUITABLE_WITH_LIMITS', 'VISUAL_PREFERRED', 'VISUAL_REQUIRED', 'MIXED', 'UNKNOWN'];
    public const REPEAT_RISK = ['LOW', 'MODERATE', 'HIGH', 'CRITICAL', 'UNKNOWN'];
    public const BLOCKER_CLASSES = [
        'OFFICIAL_SCOPE_GRANULARITY',
        'OFFICIAL_AREA_MISMATCH',
        'SOURCE_SCOPE_FIT',
        'SOURCE_AUTHORITY',
        'SOURCE_CURRENTNESS',
        'STUDY_COVERAGE',
        'STUDY_TRACEABILITY',
        'DEPTH_UNKNOWN',
        'CONTENT_FAMILY_UNPROVEN',
        'PROPOSITION_EXHAUSTED',
        'SHARED_COVERAGE_SUFFICIENT',
        'TIME_SENSITIVE_SCOPE',
        'STRUCTURAL_OR_PLACEMENT',
        'OTHER_EXPLICIT',
    ];

    /** @return array<string, mixed> */
    public static function normalize(array $record): array
    {
        foreach ([
            'source_ids',
            'study_material_ids',
            'distinct_content_families',
            'allowed_scope',
            'excluded_scope',
            'limitations',
            'blocker_classes',
            'scope_specialization_ids',
            'scope_component_ids',
        ] as $field) {
            if (in_array($field, ['scope_specialization_ids', 'scope_component_ids'], true) && !array_key_exists($field, $record)) continue;
            $values = is_array($record[$field] ?? null) ? $record[$field] : [];
            $values = array_values(array_unique(array_map(
                static fn(mixed $value): string => trim((string) $value),
                $values
            )));
            $values = array_values(array_filter($values, static fn(string $value): bool => $value !== ''));
            sort($values, SORT_STRING);
            $record[$field] = $values;
        }

        foreach ([
            'source_count',
            'primary_source_count',
            'study_material_count',
            'current_question_count',
            'deficit_to_5',
            'deficit_to_10',
            'deficit_to_20',
            'supported_depth_min',
            'supported_depth_max',
            'distinct_content_family_count',
            'safe_headroom_min',
            'safe_headroom_preferred',
            'safe_headroom_max',
            'max_new_questions',
            'planned_new_questions',
            'reviewed_at_batch',
        ] as $field) {
            if (array_key_exists($field, $record) && is_numeric($record[$field])) {
                $record[$field] = (int) $record[$field];
            }
        }

        return $record;
    }

    public static function evidenceHash(array $record): string
    {
        $record = self::normalize($record);
        $payload = [];
        foreach ([
            'board_id',
            'leaf_id',
            'official_area_id',
            'source_ids',
            'study_material_ids',
            'source_authority',
            'source_currentness',
            'source_scope_fit',
            'source_conflict_status',
            'study_coverage',
            'study_source_traceability',
            'supported_depth_min',
            'supported_depth_max',
            'distinct_content_families',
            'allowed_scope',
            'excluded_scope',
            'limitations',
            'safe_headroom_min',
            'safe_headroom_preferred',
            'safe_headroom_max',
            'readiness',
        ] as $field) {
            $payload[$field] = $record[$field] ?? null;
        }
        // Optional editorial classifications are evidence-bearing metadata,
        // not values inferred by deterministic health checks. Include them in
        // the hash only when a record explicitly stores them so existing
        // readiness collections remain backward compatible.
        foreach ([
            'visual_suitability',
            'repeat_risk',
            'evidence_exhausted',
            'shared_coverage_sufficient',
            'numerical_headroom',
            'evidence_headroom',
            'text_format_headroom',
            'high_value_headroom',
        ] as $field) {
            if (array_key_exists($field, $record)) $payload[$field] = $record[$field];
        }
        foreach (['closure_state', 'scope_status', 'scope_crosswalk_status'] as $field) {
            if (array_key_exists($field, $record)) $payload[$field] = $record[$field];
        }
        foreach (['scope_specialization_ids', 'scope_component_ids'] as $field) {
            if (array_key_exists($field, $record)) $payload[$field] = $record[$field];
        }
        if (array_key_exists('scope_component_ids', $record)) {
            foreach ([
                'current_question_count', 'deficit_to_5', 'deficit_to_10', 'deficit_to_20',
                'source_count', 'primary_source_count', 'study_material_count',
                'distinct_content_family_count',
            ] as $field) {
                $payload[$field] = $record[$field] ?? null;
            }
        }

        return hash('sha256', json_encode(
            $payload,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
        ));
    }
}
