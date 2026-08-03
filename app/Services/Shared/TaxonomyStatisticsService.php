<?php

declare(strict_types=1);

use App\Core\App;
use App\Repositories\QuestionRepository;

class TaxonomyStatisticsService
{
    public static function summary(): array
    {

        $questions =
            App::container()
                ->get(
                    QuestionRepository::class
                )
                ->all();

        $subjects = [];
        $domains = [];
        $topics = [];
        $concepts = [];
        $difficulty = [];

        foreach (
            $questions as $question
        ) {

            $taxonomy =
                $question["taxonomy"] ?? [];

            self::increment(
                $subjects,
                $taxonomy["subject_id"] ?? "Unknown"
            );

            self::increment(
                $domains,
                $taxonomy["domain_id"] ?? "Unknown"
            );

            self::increment(
                $topics,
                $taxonomy["topic_id"] ?? "Unknown"
            );

            $concept =
                trim(
                    $taxonomy["concept_id"] ?? ""
                );

            if (
                $concept !== ""
            ) {

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

            "subjects" =>
                $subjects,

            "domains" =>
                $domains,

            "topics" =>
                $topics,

            "concepts" =>
                $concepts,

            "difficulty" =>
                $difficulty

        ];

    }

    private static function increment(
        array &$collection,
        string $key
    ): void
    {

        $collection[$key] =
            ($collection[$key] ?? 0)
            + 1;

    }

}
