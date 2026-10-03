<?php

declare(strict_types=1);

namespace App\Services\RepositoryHealth\CivilService;

/**
 * The CSE coverage collection contains hierarchical exam leaves and legacy
 * flat placements. This structural adapter keeps flat rows from becoming
 * actionable zero pools without changing taxonomy or eligibility.
 */
final class CivilServiceCoverageModel
{
    public const OFFICIAL_AREAS = [
        'general-information',
        'verbal-ability',
        'numerical-ability',
        'analytical-ability',
        'clerical-ability',
    ];

    private const VALID_FLAT_GENERAL_INFORMATION = [
        'science-ecology-population',
        'social-civics-public-good',
    ];

    public static function isCoreExamLeaf(array $row): bool
    {
        if (self::normalizeBoard($row['board_id'] ?? $row['board'] ?? '') !== 'civil-service') {
            return false;
        }
        $subject = strtolower(trim((string) ($row['subject_id'] ?? '')));
        return $subject !== ''
            && in_array($subject, ['general-information', 'verbal-ability', 'numerical-ability', 'analytical-logical', 'clerical-ability'], true)
            && trim((string) ($row['domain_id'] ?? '')) !== ''
            && trim((string) ($row['topic_id'] ?? '')) !== '';
    }

    public static function classifyRow(array $row): string
    {
        if (self::normalizeBoard($row['board_id'] ?? $row['board'] ?? '') !== 'civil-service') {
            return 'AMBIGUOUS_PLACEMENT';
        }
        if (self::isCoreExamLeaf($row)) {
            return 'CORE_EXAM_LEAF';
        }

        $area = self::officialAreaForRow($row);
        if (in_array($area, ['verbal-ability', 'numerical-ability', 'analytical-ability', 'clerical-ability'], true)) {
            return 'VALID_SHARED_COMPETENCY';
        }
        if ($area === 'general-information') {
            return in_array(trim((string) ($row['concept_id'] ?? '')), self::VALID_FLAT_GENERAL_INFORMATION, true)
                ? 'VALID_SHARED_COMPETENCY'
                : 'OUT_OF_SCOPE';
        }

        return 'AMBIGUOUS_PLACEMENT';
    }

    public static function officialAreaForRow(array $row): string
    {
        foreach ([
            strtolower(trim((string) ($row['blueprint_node_id'] ?? ''))),
            strtolower(trim((string) ($row['official_area_id'] ?? ''))),
            strtolower(trim((string) ($row['subject_id'] ?? ''))),
        ] as $candidate) {
            if ($candidate === 'analytical-logical') return 'analytical-ability';
            if (in_array($candidate, self::OFFICIAL_AREAS, true)) return $candidate;
        }
        return '';
    }

    public static function officialAreaForQuestion(array $question, ?array $placement, array $coverageByConcept): string
    {
        $placement = is_array($placement) ? $placement : [];
        $taxonomy = is_array($question['taxonomy'] ?? null) ? $question['taxonomy'] : [];
        $conceptId = trim((string) ($placement['concept_id'] ?? $taxonomy['concept_id'] ?? ''));
        if ($conceptId !== '' && isset($coverageByConcept[$conceptId])) {
            $area = self::officialAreaForRow($coverageByConcept[$conceptId]);
            if ($area !== '') return $area;
        }
        foreach ([
            strtolower(trim((string) ($placement['blueprint_node'] ?? ''))),
            strtolower(trim((string) ($placement['subject_id'] ?? ''))),
            strtolower(trim((string) ($taxonomy['subject_id'] ?? $question['subject'] ?? ''))),
        ] as $candidate) {
            if ($candidate === 'analytical-logical') return 'analytical-ability';
            if (in_array($candidate, self::OFFICIAL_AREAS, true)) return $candidate;
        }
        return '';
    }

    public static function normalizeBoard(mixed $value): string
    {
        $id = strtolower(trim((string) $value));
        return $id === 'cse' ? 'civil-service' : $id;
    }
}
