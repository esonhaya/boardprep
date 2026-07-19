<?php

class QuestionFilterService
{
    public static function filter(array $questions, array $options = []): array
    {

        $filtered = $questions;


        if (
            !empty($options["difficulty"]) &&
            $options["difficulty"] !== "mixed"
        ) {

            $filtered = array_filter(
                $filtered,
                fn($q) =>
                    strtolower($q["difficulty"] ?? "") ===
                    strtolower($options["difficulty"])
            );

        }



        if (!empty($options["topic"])) {

            $filtered = array_filter(
                $filtered,
                fn($q) =>
                    strtolower($q["topic"] ?? "") ===
                    strtolower($options["topic"])
            );

        }


        return array_values($filtered);

    }
}
