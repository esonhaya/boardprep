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
        if (!is_array($choices) || !array_is_list($choices)
            || count($choices) < 2 || count($choices) > 26) {
            return false;
        }

        foreach ($choices as $choice) {
            if (!is_scalar($choice) || trim((string) $choice) === '') {
                return false;
            }
        }

        if (isset($question['explanation'])
            && !is_scalar($question['explanation'])) {
            return false;
        }

        $answer = trim((string) $question['answer']);
        $answerKey = strtoupper($answer);
        $validKey = preg_match('/^[A-Z]$/', $answerKey) === 1
            && (ord($answerKey) - ord('A')) < count($choices);
        $validText = in_array($answer, array_map('strval', $choices), true);
        if (!$validKey && !$validText) {
            return false;
        }

        return true;
    }
}
