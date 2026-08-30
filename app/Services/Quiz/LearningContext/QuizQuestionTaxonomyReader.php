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

        return QuizContextValueReader::first([
            $question[$field] ?? null,
            $taxonomy[$taxonomyKey] ?? null,
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
