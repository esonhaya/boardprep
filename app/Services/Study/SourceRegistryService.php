<?php

declare(strict_types=1);

namespace App\Services\Study;

use App\Core\App;

final class SourceRegistryService
{
    private const VALID_TYPES = [
        'ACADEMIC_COURSE', 'BOARDPREP_EDITORIAL', 'CURRENTNESS_INDICATOR', 'CURRENTNESS_TRACKING', 'LAW',
        'OFFICIAL_EXAM_ADVISORY', 'OFFICIAL_EXAM_SPEC', 'OFFICIAL_FORM', 'OFFICIAL_GUIDANCE', 'OFFICIAL_PRB_NOTICE',
        'OFFICIAL_PRB_RESOLUTION', 'OFFICIAL_PROGRAM', 'OFFICIAL_REGULATION', 'OFFICIAL_TOS', 'OPEN_TEXTBOOK',
        'PRIMARY_LAW', 'PROFESSIONAL_STANDARD', 'REGULATORY_STANDARD', 'SAFETY_REFERENCE', 'SECONDARY_REVIEW',
        'SUPREME_COURT_DECISION',
    ];

    public static function all(): array
    {
        return array_values(array_filter(App::storage()->all('sources'), 'is_array'));
    }

    public static function find(string $id): ?array
    {
        foreach (self::all() as $source) {
            if ((string) ($source['id'] ?? '') === $id) {
                return $source;
            }
        }
        return null;
    }

    public static function validate(): array
    {
        return self::validateSnapshot(App::storage()->all('sources'));
    }

    /** Deterministic source validation shared by the live registry and isolated regressions. */
    public static function validateSnapshot(array $sources): array
    {
        $invalid = [];
        $ids = [];
        $issues = [];
        foreach (array_values(array_filter($sources, 'is_array')) as $source) {
            $id = (string) ($source['id'] ?? '');
            $recordId = $id !== '' ? $id : '<missing>';
            if ($id === '') {
                $issues[] = self::issue($recordId, 'Source record has no canonical ID.', 'Assign a stable unique source ID.');
            } elseif (isset($ids[$id])) {
                $issues[] = self::issue($recordId, 'Source ID is duplicated.', 'Retain one canonical source record and assign unique IDs to distinct sources.');
            }
            if (!in_array((string) ($source['source_type'] ?? ''), self::VALID_TYPES, true)) {
                $type = trim((string) ($source['source_type'] ?? '')) ?: '<missing>';
                $issues[] = self::issue($recordId, 'Source type is not recognized: ' . $type . '.', 'Use a supported source type that describes this source record.');
            }
            if (trim((string) ($source['title'] ?? '')) === '') {
                $issues[] = self::issue($recordId, 'Source title is missing.', 'Add the source title used for traceable citations.');
            }
            if (trim((string) ($source['authority'] ?? '')) === '') {
                $issues[] = self::issue($recordId, 'Source authority is missing.', 'Identify the publisher, issuing body, or author.');
            }
            if ($id !== '') {
                $ids[$id] = true;
            }
        }

        foreach ($issues as $issue) {
            $invalid[] = $issue['record_id'];
        }
        return [
            'valid' => $issues === [],
            'invalid' => array_values(array_unique($invalid)),
            'issues' => $issues,
            'total' => count(array_filter($sources, 'is_array')),
        ];
    }

    private static function issue(string $id, string $reason, string $recommendedAction): array
    {
        return [
            'record_type' => 'source',
            'record_id' => $id,
            'severity' => 'ERROR',
            'reason' => $reason,
            'recommended_action' => $recommendedAction,
        ];
    }
}
