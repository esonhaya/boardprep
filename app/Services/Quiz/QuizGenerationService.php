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
        | Blueprint
        |--------------------------------------------------------------------------
        */

        if (!empty($options["blueprint"])) {

            $blueprint =
                BlueprintRepository::find(
                    $options["blueprint"]
                );

            if ($blueprint) {

                foreach (
                    $blueprint["sections"] ?? []
                    as $section
                ) {

                    if (!empty($section["topic"])) {

                        $options["topic"] =
                            $section["topic"];

                    }

                }

            }

        }



        /*
        |--------------------------------------------------------------------------
        | Question Filter
        |--------------------------------------------------------------------------
        */

        $questions =
    QuizHistoryService::filterUnused(
        $questions
    );


        /*
        |--------------------------------------------------------------------------
        | Adaptive
        |--------------------------------------------------------------------------
        */

        if (!empty($options["adaptive"])) {

            $adaptive =
                AdaptiveQuizService::buildOptions();


            $questions =
                AdaptiveQuizService::prioritize(
                    $questions,
                    $adaptive
                );


            if (
                ($options["difficulty"] ?? "mixed")
                ===
                "mixed"
            ) {

                $recommended =
                    $adaptive["recommendedDifficulty"];


                usort(

                    $questions,

                    function($a, $b)
                    use ($recommended)
                    {

                        $aScore =

                            strtolower(
                                $a["difficulty"] ?? ""
                            )

                            ===

                            $recommended;


                        $bScore =

                            strtolower(
                                $b["difficulty"] ?? ""
                            )

                            ===

                            $recommended;


                        return
                            $bScore <=> $aScore;

                    }

                );

            }

        }

        elseif (!empty($options["shuffle"])) {

            shuffle($questions);

        }



        /*
        |--------------------------------------------------------------------------
        | Limit
        |--------------------------------------------------------------------------
        */

        if (!empty($options["limit"])) {

            $questions =
                array_slice(
                    $questions,
                    0,
                    $options["limit"]
                );

        }



$questions =
    array_values(
        $questions
    );

QuizHistoryService::remember(
    $questions
);

return $questions;        


    }

}
