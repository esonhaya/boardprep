<?php

class QuizHistoryService
{

    public static function filterUnused(
        array $questions
    ): array
    {

        $used =
            SessionService::get(
                "usedQuestions",
                []
            );

        $unused =
            array_filter(

                $questions,

                function ($question)
                use ($used)
                {

                    return !in_array(

                        $question["id"],

                        $used,

                        true

                    );

                }

            );

        /*
         Pool exhausted.
         Start a fresh cycle.
        */

        if (empty($unused)) {

            SessionService::remove(
                "usedQuestions"
            );

            return $questions;

        }

        return array_values($unused);

    }



    public static function remember(
        array $questions
    ): void
    {

        $used =
            SessionService::get(
                "usedQuestions",
                []
            );

        foreach ($questions as $question) {

            $used[] =
                $question["id"];

        }

        SessionService::set(

            "usedQuestions",

            array_unique($used)

        );

    }

}
