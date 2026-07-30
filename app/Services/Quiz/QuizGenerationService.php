<?php

class QuizGenerationService
{

    public static function generate(
        array $questions,
        array $options = []
    ): array
    {

        $options =
            QuizBlueprintService::apply(
                $options
            );


        $questions =
            QuizHistoryService::filterUnused(
                $questions
            );


        /*
         * Apply blueprint filters before
         * adaptive/shuffle logic.
         */
        $questions =
            QuestionFilterService::filter(
                $questions,
                $options
            );


        if (
            !empty(
                $options["adaptive"]
            )
        ) {

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
                    strtolower(
                        $adaptive["recommendedDifficulty"]
                    );


                usort(

                    $questions,

                    function (
                        $a,
                        $b
                    ) use (
                        $recommended
                    ) {

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
                            $bScore
                            <=>
                            $aScore;

                    }

                );

            }

        }
        elseif (
            !empty(
                $options["shuffle"]
            )
        ) {

            shuffle(
                $questions
            );

        }


        if (
            !empty(
                $options["limit"]
            )
        ) {

            $questions =
                array_slice(
                    $questions,
                    0,
                    (int) $options["limit"]
                );

        }


        $questions =
            array_values(
                $questions
            );


        QuizHistoryService::remember(
            $questions
        );


        return
            $questions;

    }

}
