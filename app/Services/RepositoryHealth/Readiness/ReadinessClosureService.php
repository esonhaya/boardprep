<?php

declare(strict_types=1);

namespace App\Services\RepositoryHealth\Readiness;

/**
 * Small canonical projection for board-level closure decisions.
 *
 * Per-leaf readiness remains in SourceReadinessService.  This collection only
 * records a reviewed board-level conclusion when numeric thinness and
 * evidence-backed production headroom intentionally diverge.
 */
final class ReadinessClosureService
{
    public const OPERATIONAL_STATES = [
        'FOUNDATION_SUFFICIENT',
        'EVIDENCE_LIMITED',
        'CURRENTNESS_REVIEW_NEEDED',
        'VISUAL_LIMITED',
        'SELECTIVE_PRODUCTION_ONLY',
        'UNKNOWN_REQUIRES_MANUAL_REVIEW',
    ];

    /** @param array<int, mixed> $records @return array<string, array<string, mixed>> */
    public static function byBoard(array $records): array
    {
        $result = [];
        foreach ($records as $record) {
            if (!is_array($record)) continue;
            $boardId = strtolower(trim((string) ($record['board_id'] ?? '')));
            if ($boardId === '') continue;
            $result[$boardId] = self::normalize($record);
        }
        ksort($result, SORT_STRING);
        return $result;
    }

    /** @param array<string, mixed> $record @return array<string, mixed> */
    public static function normalize(array $record): array
    {
        $record['board_id'] = strtolower(trim((string) ($record['board_id'] ?? '')));
        $record['closure_state'] = strtoupper(trim((string) ($record['closure_state'] ?? 'OPEN')));
        $record['operational_state'] = strtoupper(trim((string) ($record['operational_state'] ?? 'UNKNOWN_REQUIRES_MANUAL_REVIEW')));
        foreach (['evidence_exhausted'] as $field) {
            if (array_key_exists($field, $record)) $record[$field] = (bool) $record[$field];
        }
        foreach (['numerical_headroom', 'evidence_headroom', 'text_format_headroom', 'high_value_headroom', 'reviewed_at_batch'] as $field) {
            if (array_key_exists($field, $record) && is_numeric($record[$field])) $record[$field] = (int) $record[$field];
        }
        return $record;
    }

    /** @param array<string, mixed>|null $closure @return array<string, mixed> */
    public static function summary(?array $closure): array
    {
        if (!is_array($closure)) return [];
        $normalized = self::normalize($closure);
        $allowedClosure = ['OPEN', 'FOUNDATION_SUFFICIENT', 'EVIDENCE_EXHAUSTED', 'VISUAL_LIMITED', 'CURRENTNESS_LIMITED'];
        if (!in_array($normalized['closure_state'], $allowedClosure, true)) {
            $normalized['closure_state'] = 'OPEN';
        }
        if (!in_array($normalized['operational_state'], self::OPERATIONAL_STATES, true)) {
            $normalized['operational_state'] = 'UNKNOWN_REQUIRES_MANUAL_REVIEW';
        }
        $fields = [
            'board_id', 'closure_state', 'operational_state', 'closure_reason',
            'evidence_exhausted', 'numerical_headroom', 'evidence_headroom',
            'text_format_headroom', 'high_value_headroom', 'reviewed_at_batch',
        ];
        $result = [];
        foreach ($fields as $field) {
            if (array_key_exists($field, $normalized)) $result[$field] = $normalized[$field];
        }
        return $result;
    }

    /** @param array<string, mixed> $closure @return array<string, mixed> */
    public static function isValid(array $closure): bool
    {
        $normalized = self::normalize($closure);
        return $normalized['board_id'] !== ''
            && in_array($normalized['closure_state'], ['OPEN', 'FOUNDATION_SUFFICIENT', 'EVIDENCE_EXHAUSTED', 'VISUAL_LIMITED', 'CURRENTNESS_LIMITED'], true)
            && in_array($normalized['operational_state'], self::OPERATIONAL_STATES, true)
            && trim((string) ($normalized['closure_reason'] ?? '')) !== '';
    }
}
