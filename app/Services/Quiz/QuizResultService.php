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


        $result =
            QuizScoringService::calculate(
                $questions,
                $answers
            );


        return [

            "summary" =>
                $result,

            "review" =>
                self::buildReview(
                    $questions,
                    $answers
                )

        ];

    }



    private static function buildReview(
        array $questions,
        array $answers
    ): array
    {

        $review = [];


        foreach (
            $questions
            as $question
        ) {

            $userAnswer =
                $answers[
                    $question["id"]
                ] ?? null;


            $review[] = [

                "question" =>
                    $question,

                "userAnswer" =>
                    $userAnswer,

                "correct" =>
                    QuizScoringService::checkAnswer(
                        $question,
                        $userAnswer
                    )

            ];

        }


        return $review;

    }

}
