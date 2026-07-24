<?php

class TaxonomyStatisticsService
{

    public static function summary(): array
    {

        $subjects = [];
        $topics = [];
        $concepts = [];
        $difficulty = [];

        foreach (QuestionRepository::all() as $question) {

            self::increment(
                $subjects,
                $question["subject"] ?? "Unknown"
            );

            self::increment(
                $topics,
                $question["topic"] ?? "Unknown"
            );

            $concept =
                trim(
                    $question["concept"] ?? ""
                );

            if ($concept !== "") {

                self::increment(
                    $concepts,
                    $concept
                );

            }

            self::increment(
                $difficulty,
                $question["difficulty"] ?? "Unknown"
            );

        }

        return [

            "subjects" => $subjects,
            "topics" => $topics,
            "concepts" => $concepts,
            "difficulty" => $difficulty

        ];

    }

    private static function increment(
        array &$collection,
        string $key
    ): void
    {

        $collection[$key] =
            ($collection[$key] ?? 0) + 1;

    }

}
