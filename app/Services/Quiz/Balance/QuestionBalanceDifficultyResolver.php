<?php

declare(strict_types=1);

final class QuestionBalanceDifficultyResolver
{
    public static function resolve(array $options): string
    {
        $difficulty = strtolower((string) ($options["difficulty"] ?? "mixed"));

        return $difficulty !== "" ? $difficulty : "mixed";
    }
}
