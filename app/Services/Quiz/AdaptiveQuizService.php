<?php

class AdaptiveQuizService
{

    public static function buildOptions(): array
    {

        $weaknesses =
            WeaknessService::all();

        $attempts =
            AttemptRepository::all();

        $priorityTopics = [];


        foreach ($weaknesses as $weakness) {

            if (isset($weakness["topic"])) {

                $priorityTopics[] =
                    strtolower(
                        trim(
                            $weakness["topic"]
                        )
                    );

            }

        }


        $average = 70;

        if (!empty($attempts)) {

            $average =
                ProgressService::averageScore(
                    $attempts
                );

        }

        return [

            "priorityTopics" =>
                array_values(
                    array_unique(
                        $priorityTopics
                    )
                ),

            "recommendedDifficulty" =>
                self::recommendedDifficulty(
                    $average
                ),

            "averageScore" =>
                $average

        ];

    }


    public static function prioritize(
        array $questions,
        array $options
    ): array
    {

        $priority = [];
        $normal = [];

        foreach ($questions as $question) {

            $topic =
                strtolower(
                    trim(
                        $question["topic"] ?? ""
                    )
                );

            if (
                in_array(
                    $topic,
                    $options["priorityTopics"] ?? [],
                    true
                )
            ) {

                $priority[] = $question;

            } else {

                $normal[] = $question;

            }

        }

        shuffle($priority);
        shuffle($normal);

        return array_values(
            array_merge(
                $priority,
                $normal
            )
        );

    }


    private static function recommendedDifficulty(
        int|float $average
    ): string
    {

        if ($average >= 85) {
            return "hard";
        }

        if ($average >= 65) {
            return "medium";
        }

        return "easy";

    }

}
