<?php

declare(strict_types=1);

namespace App\Services\Question\Query;

final class QuestionValueReader
{
    public static function text(array $question, string $key): string
    {
        return self::scalarText($question[$key] ?? '');
    }

    public static function taxonomy(array $question, string $key): string
    {
        $taxonomy = $question['taxonomy'] ?? [];

        if (!is_array($taxonomy)) {
            return '';
        }

        return self::scalarText($taxonomy[$key] ?? '');
    }

    private static function scalarText(mixed $value): string
    {
        return is_scalar($value) || $value === null
            ? (string) ($value ?? '')
            : '';
    }
}
