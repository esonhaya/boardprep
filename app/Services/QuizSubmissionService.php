<?php

class QuizSubmissionService
{

    public static function submit(
        array $questions,
        array $answers
    ): array
    {

        $score = 0;

        foreach ($questions as $question) {

            $correct =
                QuizController::checkAnswer(
                    $question,
                    $answers[$question["id"]] ?? null
                );

            if ($correct) {

                $score++;

            }

            QuestionStatisticsService::recordAnswer(

                $question["id"],

                $correct

            );

        }

        return [

            "score" => $score,

            "total" => count($questions)

        ];

    }

}
