<?php

declare(strict_types=1);

namespace App\Services\Quiz\LearningContext;

final class QuizContextValueReader
{
    public static function text(mixed $value): string
    {
        return is_scalar($value) ? trim((string) $value) : '';
    }

    public static function first(array $values): string
    {
        foreach ($values as $value) {
            $text = self::text($value);
            if ($text !== '') {
                return $text;
            }
        }

        return '';
    }
}
