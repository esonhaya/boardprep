<?php

class QuestionStatisticsRepository
{

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
            QuestionRepository::all()
            as $existing
        ) {

            if (
                ($existing["id"] ?? 0)
                ===
                ($question["id"] ?? 0)
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

            if ($percent >= 85) {

                $duplicates[] = [

                    "question" =>
                        $existing,

                    "score" =>
                        round($percent)

                ];

            }

        }

        return $duplicates;

    }



    public static function updateStatistics(
        array $question
    ): void
    {

        $questions =
            QuestionRepository::all();

        foreach (
            $questions
            as &$current
        ) {

            if (
                $current["id"]
                ===
                $question["id"]
            ) {

                $current =
                    $question;

                break;

            }

        }

        QuestionRepository::saveAll(
            $questions
        );

    }

}
