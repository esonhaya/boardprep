<?php

declare(strict_types=1);

namespace App\Services\Quiz\Session;

final class QuizSessionQuestion
{
    public static function isRenderable(mixed $question): bool
    {
        if (!is_array($question)) {
            return false;
        }

        foreach (['id', 'question', 'answer'] as $field) {
            if (!is_scalar($question[$field] ?? null)
                || trim((string) $question[$field]) === '') {
                return false;
            }
        }

        $choices = $question['choices'] ?? null;
        if (!is_array($choices) || count($choices) < 2) {
            return false;
        }

        foreach ($choices as $choice) {
            if (!is_scalar($choice) || trim((string) $choice) === '') {
                return false;
            }
        }

        return true;
    }
}
