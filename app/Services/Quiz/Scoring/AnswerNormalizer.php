<?php

declare(strict_types=1);

final class AnswerNormalizer
{
    public static function normalize(array $question, ?string $answer): string
    {
        $answer = trim($answer ?? "");

        if (preg_match('/^[A-D]$/i', $answer)) {
            $index = ord(strtoupper($answer)) - 65;
            $choices = is_array($question["choices"] ?? null)
                ? $question["choices"]
                : [];
            return is_scalar($choices[$index] ?? null)
                ? (string) $choices[$index]
                : "";
        }

        return $answer;
    }
}
