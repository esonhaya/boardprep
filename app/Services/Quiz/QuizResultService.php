<?php

class QuizResultService
{

    public static function build(): array
    {

        $questions =
            SessionService::get(
                "questions",
                []
            );

        $answers =
            SessionService::get(
                "answers",
                []
            );

        $summary =
            QuizScoringService::calculate(
                $questions,
                $answers
            );

        return [

            "summary" =>
                $summary,

            "review" =>
                $summary["results"]

        ];

    }

}
