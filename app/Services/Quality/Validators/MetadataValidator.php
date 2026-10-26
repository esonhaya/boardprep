<?php

declare(strict_types=1);

namespace App\Services\Quality\Validators;

final class MetadataValidator
{
    private const VALID_DIFFICULTIES = [
        'easy',
        'medium',
        'hard',
    ];

    private const VALID_STATUSES = [
        'draft',
        'approved',
        'archived',
        'active',
    ];

    public static function validate(
        array $question
    ): array {

        return array_merge(
            self::validateIdentity($question),
            self::validateTaxonomy($question),
            self::validateDifficulty($question),
            self::validateStatus($question)
        );

    }

    private static function validateIdentity(
        array $question
    ): array {

        if (
            !empty($question['id'])
        ) {
            return [];
        }

        return [
            [
                'severity' => 'error',
                'type' => 'missing-id',
                'message' => 'Question has no ID.',
            ],
        ];

    }

    private static function validateTaxonomy(
        array $question
    ): array {

        $issues = [];

        $taxonomy = is_array($question['taxonomy'] ?? null)
            ? $question['taxonomy']
            : [];

        foreach (
            [
                'board_id',
                'subject_id',
                'domain_id',
                'topic_id',
                'concept_id',
            ] as $field
        ) {

            $legacyField = str_replace('_id', '', $field);
            $value = $taxonomy[$field]
                ?? $question[$field]
                ?? $question[$legacyField]
                ?? '';

            if (is_scalar($value) && trim((string) $value) !== '') {
                continue;
            }

            $issues[] = [
                'severity' => 'error',
                'type' => 'missing-' . $field,
                'message' =>
                    ucfirst(
                        str_replace(
                            '_',
                            ' ',
                            $field
                        )
                    ) . ' is missing.',
            ];

        }

        return $issues;

    }

    private static function validateDifficulty(
        array $question
    ): array {

        $rawDifficulty = $question['difficulty'] ?? '';
        if (!is_scalar($rawDifficulty)) {
            return [[
                'severity' => 'warning',
                'type' => 'invalid-difficulty',
                'message' => 'Difficulty value must be a scalar.',
            ]];
        }
        $difficulty = strtolower(trim((string) $rawDifficulty));

        if (
            $difficulty === ''
            ||
            in_array(
                $difficulty,
                self::VALID_DIFFICULTIES,
                true
            )
        ) {
            return [];
        }

        return [
            [
                'severity' => 'warning',
                'type' => 'invalid-difficulty',
                'message' => 'Difficulty value is invalid.',
            ],
        ];

    }

    private static function validateStatus(
        array $question
    ): array {

        $rawStatus = $question['status'] ?? '';
        $statusIsScalar = is_scalar($rawStatus);
        $status = $statusIsScalar ? strtolower(trim((string) $rawStatus)) : '';

        $issues = [];

        if (!$statusIsScalar || ($status !== '' && !in_array($status, self::VALID_STATUSES, true))) {

            $issues[] = [
                'severity' => 'warning',
                'type' => 'invalid-status',
                'message' => 'Status value must be a supported scalar value.',
            ];

        }

        if (
            $status === 'draft'
        ) {

            $issues[] = [
                'severity' => 'info',
                'type' => 'draft',
                'message' => 'Question is still a draft.',
            ];

        }

        return $issues;

    }
}
