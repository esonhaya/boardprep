<?php

class QuestionFilterService
{
    public static function filter(array $questions, array $options = []): array
    {
        $filtered = $questions;

        if (!empty($options["difficulty"])) {

            $filtered = array_filter(
                $filtered,
                fn($q) =>
                    ($q["difficulty"] ?? "Easy") ===
                    $options["difficulty"]
            );
        }

        if (!empty($options["topic"])) {

            $filtered = array_filter(
                $filtered,
                fn($q) =>
                    ($q["topic"] ?? "") ===
                    $options["topic"]
            );
        }

        return array_values($filtered);
    }
}
