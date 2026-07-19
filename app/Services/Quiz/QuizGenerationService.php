<?php

class QuizGenerationService
{

    public static function generate(
        array $questions,
        array $options = []
    ): array
    {

        /*
        |--------------------------------------------------------------------------
        | Difficulty
        |--------------------------------------------------------------------------
        */

        if (
            !empty($options["difficulty"]) &&
            $options["difficulty"] !== "mixed"
        ) {

            $questions = array_filter(

                $questions,

                fn($question) =>

                    strtolower(
                        $question["difficulty"] ?? ""
                    )

                    ===

                    strtolower(
                        $options["difficulty"]
                    )

            );

        }

        /*
        |--------------------------------------------------------------------------
        | Topic
        |--------------------------------------------------------------------------
        */

        if (!empty($options["topic"])) {

            $questions = array_filter(

                $questions,

                fn($question) =>

                    strtolower(
                        $question["topic"] ?? ""
                    )

                    ===

                    strtolower(
                        $options["topic"]
                    )

            );

        }

        $questions =
            array_values($questions);

        /*
        |--------------------------------------------------------------------------
        | Shuffle
        |--------------------------------------------------------------------------
        */

        shuffle($questions);

        /*
        |--------------------------------------------------------------------------
        | Limit
        |--------------------------------------------------------------------------
        */

        $limit =
            $options["limit"]
            ?? 10;

        return array_slice(

            $questions,

            0,

            min(
                $limit,
                count($questions)
            )

        );

    }

}
