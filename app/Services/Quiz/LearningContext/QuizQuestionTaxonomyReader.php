<?php

declare(strict_types=1);

namespace App\Services\Quiz\LearningContext;

final class QuizQuestionTaxonomyReader
{
    private const KEYS = [
        'board' => 'board_id',
        'subject' => 'subject_id',
        'domain' => 'domain_id',
        'topic' => 'topic_id',
    ];

    public static function value(array $question, string $field): string
    {
        $taxonomy = is_array($question['taxonomy'] ?? null)
            ? $question['taxonomy']
            : [];
        $taxonomyKey = self::KEYS[$field] ?? $field;

        // Canonical taxonomy IDs drive practice selection and learning
        // analytics. Legacy question fields may contain display labels or
        // stale classifications, so only use them when canonical data is
        // absent.
        return QuizContextValueReader::first([
            $taxonomy[$taxonomyKey] ?? null,
            $question[$field] ?? null,
        ]);
    }

    public static function values(array $questions, string $field): array
    {
        $values = [];

        foreach ($questions as $question) {
            if (!is_array($question)) {
                continue;
            }

            $value = self::value($question, $field);
            if ($value !== '' && !in_array($value, $values, true)) {
                $values[] = $value;
            }
        }

        return $values;
    }
}
