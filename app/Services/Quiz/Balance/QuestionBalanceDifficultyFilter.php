<?php

declare(strict_types=1);

final class QuestionBalanceDifficultyFilter
{
    /**
     * @param array<int,array<string,mixed>> $questions
     * @return array<int,array<string,mixed>>
     */
    public static function filter(array $questions, string $difficulty): array
    {
        if ($difficulty === "mixed") {
            return $questions;
        }

        return array_values(array_filter(
            $questions,
            static fn(array $question): bool =>
                strtolower((string) ($question["difficulty"] ?? "")) === $difficulty
        ));
    }
}
