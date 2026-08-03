<?php

declare(strict_types=1);

use App\Core\App;
use App\Repositories\QuestionRepository;

class QuestionStatisticsRepository
{
    private static function repository(): QuestionRepository
    {

        return App::container()
            ->get(
                QuestionRepository::class
            );

    }

    public static function findDuplicates(
        array $question
    ): array
    {

        $duplicates = [];

        $target =
            strtolower(
                trim(
                    $question["question"] ?? ""
                )
            );

        foreach (
            self::repository()->all()
            as $existing
        ) {

            if (

                ($existing["id"] ?? "")

                ===

                ($question["id"] ?? "")

            ) {

                continue;

            }

            $current =
                strtolower(
                    trim(
                        $existing["question"] ?? ""
                    )
                );

            similar_text(
                $target,
                $current,
                $percent
            );

            if (
                $percent >= 85
            ) {

                $duplicates[] = [

                    "question" =>
                        $existing,

                    "score" =>
                        round(
                            $percent
                        )

                ];

            }

        }

        return $duplicates;

    }

    public static function updateStatistics(
        array $question
    ): void
    {

        self::repository()->update(

            (string) $question["id"],

            $question

        );

    }

}
